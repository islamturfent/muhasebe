<?php

declare(strict_types=1);

namespace Muh\Services;

use Muh\Core\Auth;
use Muh\Core\DB;

/**
 * Notifications center (Phase 11). Creates notifications, generates automatic
 * ones (approaching due dates, unpaid invoices, critical stock,...) and tracks
 * read state.
 */
final class NotificationService
{
    private const TYPES = ['due_date', 'unpaid_invoice', 'critical_stock', 'efatura_error', 'subscription', 'user', 'document', 'system', 'tax', 'approval'];

    /**
     * Return active user IDs in a tenant who hold a given permission code
     * (reverse of Auth::loadPermissions) — used to notify approvers.
     *
     * @return int[]
     */
    public static function approverIds(int $tenantId, string $permission): array
    {
        $rows = DB::select(
            'SELECT DISTINCT u.id AS user_id
               FROM permissions p
               JOIN role_permission rp ON rp.permission_id = p.id
               JOIN user_role ur ON ur.role_id = rp.role_id
               JOIN users u ON u.id = ur.user_id
              WHERE p.`key` = :k AND u.tenant_id = :t AND u.status = :st
                AND u.deleted_at IS NULL',
            ['k' => $permission, 't' => $tenantId, 'st' => 'active']
        );
        return array_map('intval', array_column($rows, 'user_id'));
    }

    /**
     * Notify every active approver (users holding $permission) in a tenant with
     * a per-user notification and a single summary e-mail to the tenant inbox.
     */
    public static function notifyApprovers(
        int $tenantId,
        string $permission,
        int $companyId,
        string $title,
        string $body,
        ?string $url = null,
        string $subject = ''
    ): void {
        $svc = new self();
        foreach (self::approverIds($tenantId, $permission) as $userId) {
            $svc->create('approval', $title, $body, 'warning', $userId, $companyId, $url);
        }
        if ($subject !== '') {
            self::maybeMail($tenantId, 'approval', $subject, $body . ($url ? ' — <a href="' . url($url) . '">' . __('common.details') . '</a>' : ''));
        }
    }

    /**
     * Send an e-mail for a notification type if enabled in the tenant mail
     * settings and an SMTP/log target exists.
     */
    public static function maybeMail(int $tenantId, string $type, string $subject, string $body): bool
    {
        $flag = 'notify_' . $type;
        $opts = MailSettingService::mailerOptions($tenantId);
        if (empty($opts[$flag])) {
            return false;
        }
        $to = MailSettingService::tenantEmail($tenantId);
        if (!$to) {
            return false;
        }
        $locale = \Muh\Services\EmailTemplateService::norm((string) DB::scalar('SELECT locale FROM tenants WHERE id = :t', ['t' => $tenantId]));
        $html = \Muh\Services\EmailTemplateService::layout($locale, $subject, '<p>' . $body . '</p>');
        return (new Mailer())->send($to, $subject, $html, $opts);
    }

    public function unreadCount(?int $userId = null): int
    {
        $tenantId = Auth::tenantId();
        $userId = $userId ?? Auth::id();
        $sql = 'SELECT COUNT(*) FROM notifications WHERE tenant_id = :t AND is_read = 0';
        $params = ['t' => $tenantId];
        if ($userId) {
            $sql .= ' AND (user_id = :u OR user_id IS NULL)';
            $params['u'] = $userId;
        }
        return (int) DB::scalar($sql, $params);
    }

    public function list(int $page = 1, int $perPage = 25): array
    {
        $tenantId = Auth::tenantId();
        $userId = Auth::id();
        return paginate(
            'SELECT * FROM notifications
              WHERE tenant_id = :t AND (user_id = :u OR user_id IS NULL)
              ORDER BY id DESC',
            ['t' => $tenantId, 'u' => $userId],
            $perPage,
            'page'
        );
    }

    public function create(string $type, string $title, string $body = '', string $level = 'info', ?int $userId = null, ?int $companyId = null, ?string $actionUrl = null, array $payload = [], ?int $tenantId = null): int
    {
        if (!in_array($type, self::TYPES, true)) {
            $type = 'system';
        }
        return (int) DB::insert('notifications', [
            'tenant_id' => $tenantId ?? Auth::tenantId(),
            'user_id' => $userId,
            'company_id' => $companyId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'level' => $level,
            'is_read' => 0,
            'action_url' => $actionUrl,
            'payload' => $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function markRead(int $id): bool
    {
        $tenantId = Auth::tenantId();
        return DB::update(
            'notifications',
            ['is_read' => 1],
            'id = :id AND tenant_id = :t',
            ['id' => $id, 't' => $tenantId]
        ) > 0;
    }

    public function markAllRead(): void
    {
        DB::update('notifications', ['is_read' => 1], 'tenant_id = :t AND is_read = 0', ['t' => Auth::tenantId()]);
    }

    /**
     * Live counts for the notifications overview panel (Bildirim Merkezi özeti).
     *
     * @return array<string,int> keys: pending_approvals, upcoming_tax,
     *                           upcoming_due, overdue, critical_stock
     */
    public function summary(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? Auth::tenantId();
        $today = date('Y-m-d');
        $taxFuture = date('Y-m-d', strtotime('+14 days'));
        $dueFuture = date('Y-m-d', strtotime('+7 days'));

        $pendingEntries = (int) DB::scalar(
            "SELECT COUNT(*) FROM accounting_entries WHERE tenant_id = :t AND deleted_at IS NULL AND approval_status = 'pending'",
            ['t' => $tenantId]
        );
        $pendingInvoices = (int) DB::scalar(
            "SELECT COUNT(*) FROM invoices WHERE tenant_id = :t AND deleted_at IS NULL AND approval_status = 'pending'",
            ['t' => $tenantId]
        );
        $pendingApprovals = $pendingEntries + $pendingInvoices;

        $upcomingTax = (int) DB::scalar(
            "SELECT COUNT(*) FROM tax_obligations
              WHERE tenant_id = :t AND deleted_at IS NULL AND status = 'pending'
                AND due_date IS NOT NULL AND due_date <= :future",
            ['t' => $tenantId, 'future' => $taxFuture]
        );

        $upcomingDue = 0;
        $overdue = 0;
        $invoices = DB::select(
            "SELECT due_date FROM invoices
              WHERE tenant_id = :t AND status = 'posted' AND deleted_at IS NULL
                AND paid < total AND due_date <= :future",
            ['t' => $tenantId, 'future' => $dueFuture]
        );
        foreach ($invoices as $inv) {
            if (isset($inv['due_date']) && $inv['due_date'] < $today) {
                $overdue++;
            } else {
                $upcomingDue++;
            }
        }

        $criticalStock = (int) DB::scalar(
            "SELECT COUNT(*) FROM products
              WHERE tenant_id = :t AND type = :prod AND deleted_at IS NULL
                AND stock_quantity <= critical_stock",
            ['t' => $tenantId, 'prod' => 'product']
        );

        return [
            'pending_approvals' => $pendingApprovals,
            'upcoming_tax' => $upcomingTax,
            'upcoming_due' => $upcomingDue,
            'overdue' => $overdue,
            'critical_stock' => $criticalStock,
        ];
    }

    /**
     * Scan for noteworthy events and create notifications (idempotent per day).
     */
    public function generate(?int $tenantId = null): int
    {
        $tenantId = $tenantId ?? Auth::tenantId();
        $created = 0;
        $todayKey = date('Y-m-d');

        $future = date('Y-m-d', strtotime('+7 days'));
        // 1) Unpaid / approaching-due invoices
        $invoices = DB::select(
            "SELECT i.id, i.number, i.total, i.paid, i.due_date, c.name AS company_name
               FROM invoices i
               JOIN companies c ON c.id = i.company_id
              WHERE i.tenant_id = :t AND i.status = :posted AND i.deleted_at IS NULL
                AND i.paid < i.total AND i.due_date <= :future",
            ['t' => $tenantId, 'posted' => 'posted', 'future' => $future]
        );
        foreach ($invoices as $inv) {
            $key = 'due_' . $inv['id'] . '_' . $todayKey;
            if (DB::scalar('SELECT COUNT(*) FROM notifications WHERE tenant_id = :t AND payload = :p', ['t' => $tenantId, 'p' => json_encode(['key' => $key])]) > 0) {
                continue;
            }
            $overdue = $inv['due_date'] < date('Y-m-d');
            $this->create(
                $overdue ? 'due_date' : 'unpaid_invoice',
                $overdue ? __('notify.overdue_title', ['no' => $inv['number']]) : __('notify.due_title', ['no' => $inv['number']]),
                __('notify.due_body', ['company' => $inv['company_name'], 'amount' => money($inv['total'] - $inv['paid']), 'date' => format_date($inv['due_date'])]),
                $overdue ? 'danger' : 'warning',
                null, null, '/app/invoices/' . $inv['id'],
                ['key' => $key],
                $tenantId
            );
            $created++;
            static::maybeMail($tenantId, 'due', __('notify.due_mail_subject', ['no' => $inv['number']]), __('notify.due_body', ['company' => $inv['company_name'], 'amount' => money($inv['total'] - $inv['paid']), 'date' => format_date($inv['due_date'])]) . ' — <a href="' . url('/app/invoices/' . $inv['id']) . '">' . __('common.view') . '</a>');
        }

        // 2) Critical stock
        $products = DB::select(
            "SELECT p.id, p.name, p.stock_quantity, p.critical_stock, c.name AS company_name
               FROM products p JOIN companies c ON c.id = p.company_id
              WHERE p.tenant_id = :t AND p.type = :prod AND p.deleted_at IS NULL
                AND p.stock_quantity <= p.critical_stock",
            ['t' => $tenantId, 'prod' => 'product']
        );
        foreach ($products as $p) {
            $key = 'stock_' . $p['id'] . '_' . $todayKey;
            if (DB::scalar('SELECT COUNT(*) FROM notifications WHERE tenant_id = :t AND payload = :p', ['t' => $tenantId, 'p' => json_encode(['key' => $key])]) > 0) {
                continue;
            }
            $this->create('critical_stock', __('notify.stock_title', ['name' => $p['name']]), __('notify.stock_body', ['company' => $p['company_name'], 'stock' => (float) $p['stock_quantity']]), 'danger', null, null, '/app/inventory/' . $p['id'], ['key' => $key], $tenantId);
            $created++;
            static::maybeMail($tenantId, 'stock', __('notify.stock_mail_subject', ['name' => $p['name']]), __('notify.stock_body', ['company' => $p['company_name'], 'stock' => (float) $p['stock_quantity']]));
        }

        // 2b) Vergi takvimi: yaklaşan / vadesi geçen yükümlülükler (14 gün).
        $taxFuture = date('Y-m-d', strtotime('+14 days'));
        $taxObligations = DB::select(
            "SELECT id, name, due_date FROM tax_obligations
              WHERE tenant_id = :t AND deleted_at IS NULL AND status = 'pending'
                AND due_date IS NOT NULL AND due_date <= :future",
            ['t' => $tenantId, 'future' => $taxFuture]
        );
        foreach ($taxObligations as $to) {
            $key = 'tax_' . $to['id'] . '_' . $todayKey;
            if (DB::scalar('SELECT COUNT(*) FROM notifications WHERE tenant_id = :t AND payload = :p', ['t' => $tenantId, 'p' => json_encode(['key' => $key])]) > 0) {
                continue;
            }
            $over = strtotime($to['due_date']) < strtotime(date('Y-m-d'));
            $this->create(
                'tax',
                $over ? __('notify.tax_overdue_title', ['name' => $to['name']]) : __('notify.tax_title', ['name' => $to['name']]),
                __('notify.tax_body', ['date' => format_date($to['due_date'])]),
                $over ? 'danger' : 'warning',
                null, null, '/app/tax-calendar', ['key' => $key], $tenantId
            );
            $created++;
            static::maybeMail($tenantId, 'tax', __('notify.tax_title', ['name' => $to['name']]), __('notify.tax_body', ['date' => format_date($to['due_date'])]));
        }

        // 3) Subscription expiry (only the tenant's own subscription).
        $sub = DB::first(
            'SELECT s.*, t.email AS tenant_email, t.locale AS tenant_locale FROM subscriptions s JOIN tenants t ON t.id = s.tenant_id
              WHERE s.tenant_id = :tid ORDER BY s.id DESC LIMIT 1',
            ['tid' => $tenantId]
        );
        if ($sub && !empty($sub['ends_at'])) {
            $daysLeft = (int) floor((strtotime($sub['ends_at']) - strtotime(date('Y-m-d'))) / 86400);
            $key = 'sub_' . $tenantId . '_' . $todayKey;
            $already = DB::scalar('SELECT COUNT(*) FROM notifications WHERE tenant_id = :t AND payload = :p', ['t' => $tenantId, 'p' => json_encode(['key' => $key])]) > 0;
            if ($daysLeft <= 14 && !$already) {
                $over = $daysLeft < 0;
                $this->create(
                    'subscription',
                    $over ? __('notify.sub_expired') : __('notify.sub_title', ['days' => max(0, $daysLeft)]),
                    $over ? __('notify.sub_body', ['date' => format_date($sub['ends_at'])]) : __('notify.sub_body', ['date' => format_date($sub['ends_at'])]),
                    $over ? 'danger' : 'warning',
                    null, null, '/app/settings/subscription', ['key' => $key], $tenantId
                );
                $created++;
                // E-posta hatırlatma: 3 gün içinde / sona erenler için (günde bir), ofisin dilinde markalı şablon.
                if ($daysLeft <= 3 && $sub['tenant_email']) {
                    $locale = \Muh\Services\EmailTemplateService::norm((string) ($sub['tenant_locale'] ?? 'tr'));
                    $subject = $over ? \Muh\Services\EmailTemplateService::translate($locale, 'notify.sub_mail_expired') : \Muh\Services\EmailTemplateService::translate($locale, 'notify.sub_mail_subject');
                    $body = '<p>' . ($over ? \Muh\Services\EmailTemplateService::translate($locale, 'notify.sub_expired') : \Muh\Services\EmailTemplateService::translate($locale, 'notify.sub_body', ['date' => format_date($sub['ends_at'])])) . '</p>';
                    \Muh\Services\EmailTemplateService::send($locale, (string) $sub['tenant_email'], $subject, $body);
                }
            }
        }

        return $created;
    }
}

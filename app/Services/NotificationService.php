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
    private const TYPES = ['due_date', 'unpaid_invoice', 'critical_stock', 'efatura_error', 'subscription', 'user', 'document', 'system'];

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
        return (new Mailer())->send($to, $subject, $body, $opts);
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

        // 3) Subscription expiry (only the tenant's own subscription).
        $sub = DB::first(
            'SELECT s.*, t.email AS tenant_email FROM subscriptions s JOIN tenants t ON t.id = s.tenant_id
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
                // E-posta hatırlatma: 3 gün içinde / sona erenler için (günde bir).
                if ($daysLeft <= 3 && $sub['tenant_email']) {
                    $subject = $over ? __('notify.sub_mail_expired') : __('notify.sub_mail_subject');
                    (new \Muh\Services\Mailer())->send((string) $sub['tenant_email'], $subject, $over ? __('notify.sub_expired') : __('notify.sub_body', ['date' => format_date($sub['ends_at'])]));
                }
            }
        }

        return $created;
    }
}

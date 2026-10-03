<?php

declare(strict_types=1);

namespace Muh\Services;

use Muh\Core\DB;

/**
 * Automated e-mail reminders for unpaid / overdue invoices (due within the
 * configured window). Uses the Mailer abstraction, so in log mode (no SMTP
 * configured) reminders are written to mail.log for inspection.
 */
final class InvoiceReminderService
{
    public function sendReminders(int $tenantId, int $windowDays = 7): array
    {
        $future = date('Y-m-d', strtotime("+{$windowDays} days"));
        $today = date('Y-m-d');

        $invoices = DB::select(
            "SELECT i.id, i.number, i.total, i.paid, i.due_date, i.company_id,
                    c.name AS company_name, c.email AS company_email,
                    ca.name AS cari_name, ca.email AS cari_email
               FROM invoices i
               JOIN companies c ON c.id = i.company_id
               LEFT JOIN current_accounts ca ON ca.id = i.current_account_id
              WHERE i.tenant_id = :t AND i.status = 'posted' AND i.paid < i.total
                AND i.due_date <= :future AND i.deleted_at IS NULL",
            ['t' => $tenantId, 'future' => $future]
        );

        $mailer = new Mailer();
        $sent = 0;
        foreach ($invoices as $inv) {
            $emails = array_values(array_unique(array_filter([
                $inv['company_email'] ?? null,
                $inv['cari_email'] ?? null,
            ])));
            if (!$emails) {
                continue;
            }
            $overdue = $inv['due_date'] < $today;
            $subject = __('invoice.reminder_subject', ['no' => $inv['number']]);
            $body = __('invoice.reminder_body', [
                'no' => $inv['number'],
                'amount' => money((float) $inv['total'] - (float) $inv['paid']),
                'due' => format_date($inv['due_date']),
            ]);
            foreach ($emails as $to) {
                $mailer->send($to, $subject, $body);
                $sent++;
            }
            AuditLogService::record('invoice.reminder', 'invoice', 'invoices', (string) $inv['id'], null, ['emails' => implode(',', $emails), 'overdue' => $overdue], (int) $inv['company_id'], $tenantId);
        }

        return ['sent' => $sent, 'invoices' => count($invoices)];
    }
}

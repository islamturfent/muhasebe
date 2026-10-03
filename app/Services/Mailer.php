<?php

declare(strict_types=1);

namespace Muh\Services;

/**
 * Lightweight e-mail abstraction.
 *
 * When MAIL_ENABLED=true it sends via PHP's mail(); when disabled (default),
 * emails are written to storage/logs/mail.log so flows can be developed and
 * tested without a mail server. Swap in a real provider (SMTP etc.) later
 * without changing callers.
 */
final class Mailer
{
    public function send(string $to, string $subject, string $html): bool
    {
        $to = trim($to);
        $enabled = (bool) config('mail.enabled', false);
        $host = (string) config('mail.host', '');

        // 1) SMTP relay (production): host configured + enabled.
        if ($enabled && $host !== '') {
            $smtp = new SmtpMailer(
                $host,
                (int) config('mail.port', 587),
                (string) config('mail.username', ''),
                (string) config('mail.password', ''),
                (string) config('mail.encryption', 'tls'),
                (string) config('mail.from', 'Hesap360 <no-reply@muh.local>')
            );
            if ($smtp->send($to, $subject, $html)) {
                return true;
            }

            // Fall back to log on failure so mail is never silently lost.
            $this->log($to, $subject, $html);
            return false;
        }

        // 2) PHP mail() when enabled but no relay configured.
        if ($enabled) {
            $from = config('mail.from', 'Hesap360 <no-reply@muh.local>');
            $headers = "MIME-Version: 1.0\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n"
                . "From: {$from}\r\n";
            $ok = @mail($to, $subject, $html, $headers);
            if ($ok) {
                return true;
            }
        }

        // 3) Log mode (default) — persist the message for inspection.
        $this->log($to, $subject, $html);
        return true;
    }

    private function log(string $to, string $subject, string $html): void
    {
        $log = config('app.filesystem.logs', dirname(__DIR__, 2) . '/storage/logs') . '/mail.log';
        $entry = '[' . now() . "] TO: {$to} | SUBJECT: {$subject}\n{$html}\n"
            . str_repeat('-', 60) . "\n";
        @file_put_contents($log, $entry, FILE_APPEND | LOCK_EX);
    }
}

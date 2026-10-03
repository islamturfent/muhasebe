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
    /**
     * Send an e-mail. Pass $options to override global config with tenant
     * settings (host/port/user/pass/encryption/from/enabled).
     */
    public function send(string $to, string $subject, string $html, ?array $options = null): bool
    {
        $to = trim($to);
        $options = $options ?? [];
        $enabled = (bool) ($options['enabled'] ?? config('mail.enabled', false));
        $host = (string) ($options['host'] ?? config('mail.host', ''));

        // 1) SMTP relay (production): host configured + enabled.
        if ($enabled && $host !== '') {
            $smtp = new SmtpMailer(
                $host,
                (int) ($options['port'] ?? config('mail.port', 587)),
                (string) ($options['username'] ?? config('mail.username', '')),
                (string) ($options['password'] ?? config('mail.password', '')),
                (string) ($options['encryption'] ?? config('mail.encryption', 'tls')),
                (string) ($options['from'] ?? config('mail.from', 'Hesap360 <no-reply@muh.local>'))
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
            $from = $options['from'] ?? config('mail.from', 'Hesap360 <no-reply@muh.local>');
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

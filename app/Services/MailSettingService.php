<?php

declare(strict_types=1);

namespace Muh\Services;

use Muh\Core\DB;

/**
 * Tenant-scoped e-mail (SMTP) settings and per-type notification email toggles.
 * Stored in the `settings` table (group = 'mail').
 */
final class MailSettingService
{
    /** @return array<string,mixed> */
    public static function get(int $tenantId): array
    {
        $rows = DB::select("SELECT `key`, value FROM settings WHERE tenant_id = :t AND `group` = 'mail'", ['t' => $tenantId]);
        $out = static::defaults();
        foreach ($rows as $r) {
            $out[$r['key']] = $r['value'];
        }
        return $out;
    }

    public static function defaults(): array
    {
        return [
            'enabled' => '0',
            'host' => '',
            'port' => '587',
            'username' => '',
            'password' => '',
            'encryption' => 'tls',
            'from_email' => '',
            'from_name' => 'Hesap360',
            'notify_due' => '1',
            'notify_stock' => '1',
            'notify_efatura' => '1',
            'notify_user' => '1',
        ];
    }

    public static function save(int $tenantId, array $data): void
    {
        $allowed = [
            'enabled', 'host', 'port', 'username', 'password', 'encryption',
            'from_email', 'from_name', 'notify_due', 'notify_stock', 'notify_efatura', 'notify_user',
        ];
        // Never persist the password unless it was re-entered.
        $existing = static::get($tenantId);
        if ((string) ($data['password'] ?? '') === '') {
            $data['password'] = $existing['password'] ?? '';
        }
        foreach ($allowed as $key) {
            $value = (string) ($data[$key] ?? '');
            if ($key === 'enabled') {
                $value = $value ? '1' : '0';
            }
            foreach (['notify_due', 'notify_stock', 'notify_efatura', 'notify_user'] as $b) {
                if ($key === $b) {
                    $value = $value ? '1' : '0';
                }
            }
            static::upsert($tenantId, $key, $value);
        }
    }

    /** Effective Mailer options merged from tenant settings. */
    public static function mailerOptions(int $tenantId): array
    {
        $s = static::get($tenantId);
        return [
            'enabled' => (bool) (int) ($s['enabled'] ?? 0),
            'host' => $s['host'] ?? '',
            'port' => (int) ($s['port'] ?? 587),
            'username' => $s['username'] ?? '',
            'password' => $s['password'] ?? '',
            'encryption' => $s['encryption'] ?? 'tls',
            'from' => trim(($s['from_name'] ?? 'Hesap360') . ' <' . ($s['from_email'] ?? 'no-reply@muh.local') . '>'),
            'notify_due' => (bool) (int) ($s['notify_due'] ?? 1),
            'notify_stock' => (bool) (int) ($s['notify_stock'] ?? 1),
            'notify_efatura' => (bool) (int) ($s['notify_efatura'] ?? 1),
            'notify_user' => (bool) (int) ($s['notify_user'] ?? 1),
        ];
    }

    /** Primary notification e-mail for a tenant (tenant email, else first user). */
    public static function tenantEmail(int $tenantId): ?string
    {
        $t = DB::first('SELECT email FROM tenants WHERE id = :id', ['id' => $tenantId]);
        $email = $t['email'] ?? '';
        if (!$email) {
            $email = (string) DB::scalar('SELECT email FROM users WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY id LIMIT 1', ['t' => $tenantId]) ?? '';
        }
        return $email !== '' ? $email : null;
    }

    private static function upsert(int $tenantId, string $key, string $value): void
    {
        $exists = DB::first("SELECT id FROM settings WHERE tenant_id = :t AND `group` = 'mail' AND `key` = :k", ['t' => $tenantId, 'k' => $key]);
        if ($exists) {
            DB::execute("UPDATE settings SET value = :v, updated_at = NOW() WHERE id = :id", ['v' => $value, 'id' => (int) $exists['id']]);
        } else {
            DB::insert('settings', [
                'tenant_id' => $tenantId, 'group' => 'mail', 'key' => $key, 'value' => $value,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}

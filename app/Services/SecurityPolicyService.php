<?php

declare(strict_types=1);

namespace Muh\Services;

use Muh\Core\DB;

/**
 * Platform-wide security policies set by the super admin. Stored in the
 * `settings` table (group = 'security_policy', tenant_id null). All default off.
 */
final class SecurityPolicyService
{
    public static function get(): array
    {
        $defaults = ['require_email_verify' => '0', 'require_2fa' => '0', 'rate_limit_webhooks' => '0'];
        $rows = DB::select("SELECT `key`, value FROM settings WHERE `group` = 'security_policy'");
        foreach ($rows as $r) {
            $defaults[$r['key']] = $r['value'];
        }
        return $defaults;
    }

    public static function is(string $key): bool
    {
        return self::get()[$key] === '1';
    }

    public static function save(array $data): void
    {
        foreach (['require_email_verify', 'require_2fa', 'rate_limit_webhooks'] as $key) {
            $value = !empty($data[$key]) ? '1' : '0';
            $exists = DB::first("SELECT id FROM settings WHERE `group` = 'security_policy' AND `key` = :k", ['k' => $key]);
            if ($exists) {
                DB::execute("UPDATE settings SET value = :v, updated_at = :n WHERE id = :id", ['v' => $value, 'n' => now(), 'id' => (int) $exists['id']]);
            } else {
                DB::insert('settings', [
                    'tenant_id' => null, 'group' => 'security_policy', 'key' => $key, 'value' => $value,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }
}

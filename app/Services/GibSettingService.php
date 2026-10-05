<?php

declare(strict_types=1);

namespace Muh\Services;

use Muh\Core\DB;

/**
 * Tenant-scoped GİB / e-Beyan ayarları (provider, token, kimlik, uç URL).
 * `settings` tablosunda `group = 'gib'` altında saklanır; e-Fatura ayarlarına
 * benzer şekilde tenant üzerinden okunur/yazılır.
 */
final class GibSettingService
{
    /** @return array<string,mixed> */
    public static function get(int $tenantId): array
    {
        $rows = DB::select("SELECT `key`, value FROM settings WHERE tenant_id = :t AND `group` = 'gib'", ['t' => $tenantId]);
        $out = static::defaults();
        foreach ($rows as $r) {
            $out[$r['key']] = $r['value'];
        }
        return $out;
    }

    /** @return array<string,mixed> */
    public static function defaults(): array
    {
        return [
            'provider' => 'simulated', // simulated | rest
            'mode' => 'test',          // test | prod
            'token' => '',
            'password' => '',
            'username' => '',
            'test_url' => '',
            'production_url' => '',
        ];
    }

    /** @param array<string,mixed> $data */
    public static function save(int $tenantId, array $data): void
    {
        $allowed = ['provider', 'mode', 'token', 'password', 'username', 'test_url', 'production_url'];
        $existing = static::get($tenantId);
        if ((string) ($data['password'] ?? '') === '') {
            $data['password'] = $existing['password'] ?? '';
        }
        if ((string) ($data['token'] ?? '') === '') {
            $data['token'] = $existing['token'] ?? '';
        }
        foreach ($allowed as $key) {
            $value = (string) ($data[$key] ?? '');
            if ($key === 'provider' && !in_array($value, ['simulated', 'rest'], true)) {
                $value = 'simulated';
            }
            if ($key === 'mode' && !in_array($value, ['test', 'prod'], true)) {
                $value = 'test';
            }
            static::upsert($tenantId, $key, $value);
        }
    }

    /** Effective gateway config for a tenant (settings override env defaults). */
    public static function config(int $tenantId): array
    {
        $s = static::get($tenantId);
        $env = config('efatura', []);
        return [
            'provider' => $s['provider'],
            'mode' => $s['mode'],
            'token' => $s['token'],
            'password' => $s['password'],
            'username' => $s['username'],
            'test_url' => $s['test_url'] !== '' ? $s['test_url'] : ($env['test_url'] ?? ''),
            'production_url' => $s['production_url'] !== '' ? $s['production_url'] : ($env['production_url'] ?? ''),
        ];
    }

    private static function upsert(int $tenantId, string $key, string $value): void
    {
        $exists = DB::first("SELECT id FROM settings WHERE tenant_id = :t AND `group` = 'gib' AND `key` = :k", ['t' => $tenantId, 'k' => $key]);
        if ($exists) {
            DB::execute('UPDATE settings SET value = :v, updated_at = NOW() WHERE id = :id', ['v' => $value, 'id' => (int) $exists['id']]);
        } else {
            DB::insert('settings', ['tenant_id' => $tenantId, 'group' => 'gib', 'key' => $key, 'value' => $value, 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}

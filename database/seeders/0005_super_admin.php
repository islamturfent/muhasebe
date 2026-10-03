<?php

declare(strict_types=1);

/**
 * Seed the initial SUPER ADMIN (system owner) account.
 *
 * Credentials: admin@muh.local / Admin1234!
 * Only the software owner (or a system admin) should ever create additional
 * system admins — see the /admin panel and `php bin/muh admin:make <email>`.
 */
use Muh\Core\DB;
use Muh\Core\Hash;

return function (): void {
    $existing = DB::first('SELECT id FROM users WHERE is_system_admin = 1 AND deleted_at IS NULL LIMIT 1');
    if ($existing) {
        echo "  (super admin already exists, skipping)\n";
        return;
    }

    DB::transaction(function () {
        $platform = DB::first("SELECT id FROM tenants WHERE slug = 'platform'");
        if ($platform) {
            $platformId = (int) $platform['id'];
        } else {
            $platformId = (int) DB::insert('tenants', [
                'name' => 'Hesap360 Platform', 'slug' => 'platform', 'legal_name' => 'Hesap360 Platform',
                'email' => 'platform@muh.local', 'phone' => null,
                'country' => 'TR', 'locale' => 'tr', 'currency' => 'TRY', 'status' => 'active',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $userId = (int) DB::insert('users', [
            'tenant_id' => $platformId,
            'name' => 'System Owner',
            'email' => 'admin@muh.local',
            'phone' => null,
            'password' => Hash::make('Admin1234!'),
            'locale' => 'tr',
            'currency' => 'TRY',
            'status' => 'active',
            'is_owner' => 0,
            'is_system_admin' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        unset($userId);
    });

    echo "  Super admin created: admin@muh.local / Admin1234!\n";
    echo "  Change this password after first login and use /admin panel to assign others.\n";
};

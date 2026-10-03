<?php

declare(strict_types=1);

/**
 * Second demo office seeder.
 *
 * Provisions a completely separate tenant ("Demo Ofis B") with its own user and
 * a company. This gives the security suite (bin/muh security → tenant isolation)
 * two real tenants that each have a company, so the cross-tenant access check
 * actually runs instead of being skipped for "not enough data".
 *
 * It also makes local/demo browsing show a second, fully independent office —
 * reinforcing the multi-tenant story. Idempotent: skips if it already exists.
 *
 * Credentials: second@muh.local / Second1234
 */
use Muh\Core\DB;
use Muh\Core\Hash;

return function (): void {
    $now = now();

    if (DB::first('SELECT id FROM tenants WHERE slug = :s', ['s' => 'demo-office-b'])) {
        echo "  (second demo office already exists, skipping)\n";
        return;
    }

    DB::transaction(function () use ($now) {
        $tenantId = (int) DB::insert('tenants', [
            'name' => 'Demo Ofis B', 'slug' => 'demo-office-b', 'legal_name' => 'İkinci Demo Muhasebe Bürosu',
            'email' => 'second@muh.local', 'phone' => '+90 532 111 11 11',
            'country' => 'TR', 'locale' => 'tr', 'currency' => 'TRY',
            'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
        ]);

        $ownerRole = DB::first('SELECT id FROM roles WHERE `key` = :k', ['k' => 'owner']);

        $userId = (int) DB::insert('users', [
            'tenant_id' => $tenantId, 'name' => 'Zeynep Kaya', 'email' => 'second@muh.local',
            'phone' => '+90 532 111 11 11', 'password' => Hash::make('Second1234'),
            'locale' => 'tr', 'currency' => 'TRY', 'status' => 'active',
            'is_owner' => 1, 'is_system_admin' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        if ($ownerRole) {
            DB::insert('user_role', ['user_id' => $userId, 'role_id' => (int) $ownerRole['id'], 'created_at' => $now, 'updated_at' => $now]);
        }

        // A company belonging to the second tenant (used by the isolation check).
        DB::insert('companies', [
            'tenant_id' => $tenantId, 'name' => 'Gizli Firma B', 'trade_name' => 'Gizli Firma B Tic. Ltd.',
            'tax_number' => '2222222222', 'tax_office' => 'Kadıköy',
            'currency' => 'TRY', 'status' => 'active',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        // FREE trial subscription for the second tenant.
        $plan = DB::first('SELECT id FROM plans WHERE code = :c', ['c' => 'FREE']);
        if ($plan) {
            DB::insert('subscriptions', [
                'tenant_id' => $tenantId, 'plan_id' => (int) $plan['id'],
                'status' => 'trial', 'starts_at' => $now,
                'trial_ends_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
                'billing_cycle' => 'monthly',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    });
};

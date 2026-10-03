<?php

declare(strict_types=1);

/**
 * Demo office seeder for local development / testing.
 * Skips gracefully if a demo office already exists.
 *
 * Credentials: demo@muh.local / Demo1234
 */
use Muh\Core\DB;
use Muh\Core\Hash;

return function (): void {
    $now = now();

    $existing = DB::first('SELECT id FROM tenants WHERE slug = :s', ['s' => 'demo-office']);
    if ($existing) {
        echo "  (demo office already exists, skipping)\n";
        return;
    }

    DB::transaction(function () use ($now) {
        $tenantId = (int) DB::insert('tenants', [
            'name' => 'Demo Ofis', 'slug' => 'demo-office', 'legal_name' => 'Demo Muhasebe Ofisi Ltd. Şti.',
            'email' => 'demo@muh.local', 'phone' => '+90 532 000 00 00',
            'country' => 'TR', 'locale' => 'tr', 'currency' => 'TRY',
            'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
        ]);

        $ownerRole = DB::first('SELECT id FROM roles WHERE `key` = :k', ['k' => 'owner']);

        $userId = (int) DB::insert('users', [
            'tenant_id' => $tenantId, 'name' => 'Ahmet Yılmaz', 'email' => 'demo@muh.local',
            'phone' => '+90 532 000 00 00', 'password' => Hash::make('Demo1234'),
            'locale' => 'tr', 'currency' => 'TRY', 'status' => 'active',
            'is_owner' => 1, 'is_system_admin' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        if ($ownerRole) {
            DB::insert('user_role', ['user_id' => $userId, 'role_id' => (int) $ownerRole['id'], 'created_at' => $now, 'updated_at' => $now]);
        }

        // Attach the FREE plan with an active trial subscription.
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

<?php

declare(strict_types=1);

use Muh\Core\DB;

/**
 * Add Stripe price IDs to plans. The StripePaymentGateway creates a real
 * subscription using the price id (stripe_price_monthly_id / _yearly_id), but
 * the plans table had no such columns — so live Stripe sign-up would fail.
 * Super admins fill these in via the /admin/plans screen (plan CRUD).
 */
return new class {
    public function up(): void
    {
        $driver = DB::driver();
        $type = $driver === 'pgsql' ? 'VARCHAR(191) NULL' : 'VARCHAR(191) NULL';

        $cols = [
            'stripe_price_monthly_id' => $type,
            'stripe_price_yearly_id'  => $type,
        ];
        foreach ($cols as $col => $colType) {
            $exists = DB::first(
                "SELECT COUNT(*) AS c FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plans' AND COLUMN_NAME = :c",
                ['c' => $col]
            );
            if (($exists['c'] ?? 0) === 0) {
                DB::execute('ALTER TABLE plans ADD COLUMN ' . DB::quoteIdentifier($col) . ' ' . $colType);
            }
        }
    }

    public function down(): void
    {
    }
};

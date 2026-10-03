<?php

declare(strict_types=1);

use Muh\Core\DB;

/**
 * Add per-account annual budget amount (bütçe) to the chart of accounts.
 */
return new class {
    public function up(): void
    {
        $decimal = DB::driver() === 'pgsql' ? 'NUMERIC(15,2) NOT NULL DEFAULT 0' : 'DECIMAL(15,2) NOT NULL DEFAULT 0';
        $exists = DB::first(
            "SELECT COUNT(*) AS c FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'accounting_accounts' AND COLUMN_NAME = 'budget_amount'"
        );
        if (($exists['c'] ?? 0) === 0) {
            DB::execute('ALTER TABLE accounting_accounts ADD COLUMN budget_amount ' . $decimal);
        }
    }

    public function down(): void
    {
        DB::execute('ALTER TABLE accounting_accounts DROP COLUMN IF EXISTS budget_amount');
    }
};

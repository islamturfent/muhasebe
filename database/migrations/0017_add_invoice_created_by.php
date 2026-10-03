<?php

declare(strict_types=1);

use Muh\Core\DB;

/**
 * Approval workflow (req #20 "Onayla"): track who created an invoice so that
 * approval/rejection decisions can notify the original creator. Accounting
 * entries already have `created_by`; invoices lacked it.
 */
return new class {
    public function up(): void
    {
        $exists = DB::first(
            "SELECT COUNT(*) AS c FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoices' AND COLUMN_NAME = 'created_by'"
        );
        if (($exists['c'] ?? 0) === 0) {
            DB::execute('ALTER TABLE invoices ADD COLUMN created_by BIGINT NULL');
        }
    }

    public function down(): void
    {
    }
};

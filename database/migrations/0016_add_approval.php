<?php

declare(strict_types=1);

use Muh\Core\DB;

/**
 * Approval workflow (req #20 "Onayla"): add approval columns to accounting
 * entries (fişler) and invoices so draft/onay akışı can be tracked and gated.
 */
return new class {
    public function up(): void
    {
        $decimal = null;
        $add = function (string $table, string $col, string $type) {
            $exists = DB::first(
                "SELECT COUNT(*) AS c FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c",
                ['t' => $table, 'c' => $col]
            );
            if (($exists['c'] ?? 0) === 0) {
                DB::execute('ALTER TABLE ' . DB::quoteIdentifier($table) . ' ADD COLUMN ' . DB::quoteIdentifier($col) . ' ' . $type);
            }
        };
        foreach (['accounting_entries', 'invoices'] as $t) {
            $add($t, 'approval_status', "VARCHAR(20) NULL DEFAULT 'none'");
            $add($t, 'approval_note', 'VARCHAR(255) NULL');
            $add($t, 'approved_by', 'BIGINT NULL');
            $add($t, 'approved_at', 'DATETIME NULL');
        }
    }

    public function down(): void
    {
    }
};

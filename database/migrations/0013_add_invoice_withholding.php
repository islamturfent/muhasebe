<?php

declare(strict_types=1);

use Muh\Core\DB;

/**
 * Add KDV tevkifat (withholding) support.
 *   invoices.withholding         — total withheld KDV for the invoice
 *   invoice_items.withholding_rate — tevkifat rate (% of the VAT) per line
 *   invoice_items.withholding      — withheld amount for the line
 */
return new class {
    public function up(): void
    {
        $decimal = DB::driver() === 'pgsql' ? 'NUMERIC(15,2) NULL' : 'DECIMAL(15,2) NULL';

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

        $add('invoices', 'withholding', $decimal);
        $add('invoice_items', 'withholding_rate', $decimal);
        $add('invoice_items', 'withholding', $decimal);
    }

    public function down(): void
    {
    }
};

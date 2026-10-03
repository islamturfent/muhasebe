<?php

declare(strict_types=1);

use Muh\Core\DB;

return new class {
    public function up(): void
    {
        $driver = DB::driver();
        $type = $driver === 'pgsql' ? 'TIMESTAMP NULL' : 'DATETIME NULL';

        foreach (['invoice_items', 'order_items'] as $table) {
            foreach (['created_at', 'updated_at'] as $col) {
                $exists = DB::first(
                    "SELECT COUNT(*) AS c FROM information_schema.COLUMNS
                      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c",
                    ['t' => $table, 'c' => $col]
                );
                if (($exists['c'] ?? 0) === 0) {
                    DB::execute('ALTER TABLE ' . DB::quoteIdentifier($table) . ' ADD COLUMN ' . DB::quoteIdentifier($col) . ' ' . $type);
                }
            }
        }
    }

    public function down(): void
    {
    }
};

<?php

declare(strict_types=1);

use Muh\Core\DB;

return new class {
    public function up(): void
    {
        $driver = DB::driver();
        $type = $driver === 'pgsql' ? 'VARCHAR(500) NULL' : 'VARCHAR(500) NULL';

        foreach (['invoice_items', 'order_items'] as $table) {
            $exists = DB::first(
                "SELECT COUNT(*) AS c FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = 'description'",
                ['t' => $table]
            );
            if (($exists['c'] ?? 0) === 0) {
                DB::execute('ALTER TABLE ' . DB::quoteIdentifier($table) . ' ADD COLUMN ' . DB::quoteIdentifier('description') . ' ' . $type);
            }
        }
    }

    public function down(): void
    {
        // Optional rollback removed description columns.
    }
};

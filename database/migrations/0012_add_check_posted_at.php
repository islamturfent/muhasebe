<?php

declare(strict_types=1);

use Muh\Core\DB;

/**
 * Add posted_at to checks & promissory_notes so a "collected" financial
 * posting (cari + kasa + journal) is applied only once per record.
 */
return new class {
    public function up(): void
    {
        $type = DB::driver() === 'pgsql' ? 'TIMESTAMP NULL' : 'DATETIME NULL';
        foreach (['checks', 'promissory_notes'] as $table) {
            $exists = DB::first(
                "SELECT COUNT(*) AS c FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = 'posted_at'",
                ['t' => $table]
            );
            if (($exists['c'] ?? 0) === 0) {
                DB::execute('ALTER TABLE ' . DB::quoteIdentifier($table) . ' ADD COLUMN posted_at ' . $type);
            }
        }
    }

    public function down(): void
    {
    }
};

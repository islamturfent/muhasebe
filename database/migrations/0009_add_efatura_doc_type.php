<?php

declare(strict_types=1);

use Muh\Core\DB;

/**
 * Add e-Fatura document type (invoice / archive / dispatch) and the integrator
 * envelope id so we can track E-Fatura and E-Arşiv documents with their
 * provider reference.
 */
return new class {
    public function up(): void
    {
        $driver = DB::driver();
        $varchar  = $driver === 'pgsql' ? 'VARCHAR(20) NULL' : 'VARCHAR(20) NULL';
        $varchar64 = $driver === 'pgsql' ? 'VARCHAR(64) NULL' : 'VARCHAR(64) NULL';

        $cols = [
            'efatura_doc_type'   => $varchar,
            'efatura_envelope_id' => $varchar64,
        ];
        foreach ($cols as $col => $type) {
            $exists = DB::first(
                "SELECT COUNT(*) AS c FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoices' AND COLUMN_NAME = :c",
                ['c' => $col]
            );
            if (($exists['c'] ?? 0) === 0) {
                DB::execute('ALTER TABLE invoices ADD COLUMN ' . DB::quoteIdentifier($col) . ' ' . $type);
            }
        }
    }

    public function down(): void
    {
    }
};

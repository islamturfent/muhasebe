<?php

declare(strict_types=1);

use Muh\Core\DB;

/**
 * Auth & FX enhancements (Phase 14):
 *  - password_resets table (forgot-password / email verify tokens)
 *  - users.email_verified_at
 *  - invoices.currency_code + invoices.exchange_rate (foreign-currency invoices)
 */
return new class {
    public function up(): void
    {
        // 1) password_resets table
        DB::execute(
            'CREATE TABLE IF NOT EXISTS password_resets (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                email VARCHAR(190) NOT NULL,
                token VARCHAR(100) NOT NULL,
                type VARCHAR(20) NOT NULL DEFAULT "reset",
                expires_at DATETIME NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                PRIMARY KEY (id),
                KEY idx_pwreset_email (email),
                KEY idx_pwreset_token (token)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

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
        $add('users', 'email_verified_at', 'DATETIME NULL');
        $add('invoices', 'currency_code', "VARCHAR(8) NULL");
        $add('invoices', 'exchange_rate', 'DECIMAL(18,6) NULL');
    }

    public function down(): void
    {
        DB::execute('DROP TABLE IF EXISTS password_resets');
    }
};

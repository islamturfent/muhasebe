<?php

declare(strict_types=1);

use Muh\Core\DB;

/**
 * Pivot table linking an invite to the companies granted on acceptance.
 */
return new class {
    public function up(): void
    {
        $exists = DB::first(
            "SELECT COUNT(*) AS c FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_invite_company'"
        );
        if (($exists['c'] ?? 0) > 0) {
            return;
        }
        $driver = DB::driver();
        if ($driver === 'pgsql') {
            DB::execute("CREATE TABLE user_invite_company (
                id BIGSERIAL PRIMARY KEY,
                invite_id BIGINT NOT NULL,
                company_id BIGINT NOT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            )");
        } else {
            DB::execute("CREATE TABLE user_invite_company (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                invite_id BIGINT UNSIGNED NOT NULL,
                company_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                UNIQUE KEY uq_invite_company (invite_id, company_id)
            )");
        }
    }

    public function down(): void
    {
    }
};

<?php

declare(strict_types=1);

use Muh\Core\DB;

/**
 * Turkey-specific tax obligations / calendar (vergi takvimi).
 */
return new class {
    public function up(): void
    {
        $exists = DB::first(
            "SELECT COUNT(*) AS c FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tax_obligations'"
        );
        if (($exists['c'] ?? 0) > 0) {
            return;
        }
        if (DB::driver() === 'pgsql') {
            DB::execute("CREATE TABLE tax_obligations (
                id BIGSERIAL PRIMARY KEY,
                tenant_id BIGINT NOT NULL,
                company_id BIGINT NULL,
                name VARCHAR(255) NOT NULL,
                obligation_type VARCHAR(40) NULL,
                period_label VARCHAR(40) NULL,
                due_date DATE NULL,
                amount NUMERIC(15,2) NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                notes VARCHAR(500) NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                deleted_at TIMESTAMP NULL
            )");
            DB::execute('CREATE INDEX idx_tax_oblig_tenant ON tax_obligations (tenant_id)');
            DB::execute('CREATE INDEX idx_tax_oblig_company ON tax_obligations (company_id)');
            DB::execute('CREATE INDEX idx_tax_oblig_due ON tax_obligations (due_date)');
        } else {
            DB::execute("CREATE TABLE tax_obligations (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                tenant_id BIGINT UNSIGNED NOT NULL,
                company_id BIGINT UNSIGNED NULL,
                name VARCHAR(255) NOT NULL,
                obligation_type VARCHAR(40) NULL,
                period_label VARCHAR(40) NULL,
                due_date DATE NULL,
                amount DECIMAL(15,2) NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                notes VARCHAR(500) NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                deleted_at DATETIME NULL,
                INDEX idx_tax_oblig_tenant (tenant_id),
                INDEX idx_tax_oblig_company (company_id),
                INDEX idx_tax_oblig_due (due_date)
            )");
        }
    }

    public function down(): void
    {
        DB::execute('DROP TABLE IF EXISTS tax_obligations');
    }
};

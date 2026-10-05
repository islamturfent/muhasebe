<?php

declare(strict_types=1);

use Muh\Core\DB;

/**
 * Defter-Beyan (Faz 3): işletme defteri kayıtları (e-SMM dahil) — GİB'e
 * tek tıkla gönderim + geçmiş içe aktarım.
 */
return new class {
    public function up(): void
    {
        DB::execute(
            "CREATE TABLE IF NOT EXISTS defter_beyan_records (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id BIGINT UNSIGNED NOT NULL,
                company_id BIGINT UNSIGNED NOT NULL,
                record_date DATE NULL,
                doc_type VARCHAR(16) NOT NULL DEFAULT 'diger',
                doc_number VARCHAR(64) NULL,
                description VARCHAR(255) NULL,
                amount DECIMAL(15,2) NOT NULL DEFAULT 0,
                vat DECIMAL(15,2) NOT NULL DEFAULT 0,
                total DECIMAL(15,2) NOT NULL DEFAULT 0,
                status VARCHAR(16) NOT NULL DEFAULT 'draft',
                gib_reference VARCHAR(64) NULL,
                gib_error TEXT NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                PRIMARY KEY (id),
                KEY idx_dbay_tenant_status (tenant_id, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(): void
    {
        DB::execute('DROP TABLE IF EXISTS defter_beyan_records');
    }
};

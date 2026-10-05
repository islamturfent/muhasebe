<?php

declare(strict_types=1);

use Muh\Core\DB;

/**
 * GİB e-Beyan (Faz 1): firma için 6 haneli GİB Vergi Dairesi kodu ve
 * beyannameler tablosu (hazırlama → paketleme → GİB'e gönderme → onaylama).
 */
return new class {
    public function up(): void
    {
        $cols = [];
        foreach (DB::select('SHOW COLUMNS FROM companies') as $c) {
            $cols[strtolower((string) $c['Field'])] = true;
        }
        if (!isset($cols['tax_office_code'])) {
            DB::execute('ALTER TABLE companies ADD COLUMN tax_office_code VARCHAR(8) NULL DEFAULT NULL AFTER tax_office');
        }

        DB::execute(
            "CREATE TABLE IF NOT EXISTS beyannameler (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id BIGINT UNSIGNED NOT NULL,
                company_id BIGINT UNSIGNED NULL,
                type VARCHAR(16) NOT NULL,
                period CHAR(7) NOT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'draft',
                tax_office_code VARCHAR(8) NULL,
                payload MEDIUMTEXT NULL,
                gib_reference VARCHAR(64) NULL,
                gib_error TEXT NULL,
                sent_at DATETIME NULL,
                approved_at DATETIME NULL,
                created_by BIGINT UNSIGNED NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                PRIMARY KEY (id),
                KEY idx_beyan_tenant_type_period (tenant_id, type, period)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(): void
    {
        DB::execute('DROP TABLE IF EXISTS beyannameler');
        DB::execute('ALTER TABLE companies DROP COLUMN IF EXISTS tax_office_code');
    }
};

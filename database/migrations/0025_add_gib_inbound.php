<?php

declare(strict_types=1);

use Muh\Core\DB;

/**
 * GİB e-Belge Express (Faz 2): GİB'ten çekilen e-Fatura/e-Arşiv evraklarını
 * saklar, muhasebe fişine çevrilir.
 */
return new class {
    public function up(): void
    {
        DB::execute(
            "CREATE TABLE IF NOT EXISTS gib_inbound_documents (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id BIGINT UNSIGNED NOT NULL,
                company_id BIGINT UNSIGNED NOT NULL,
                doc_type VARCHAR(16) NOT NULL DEFAULT 'e-fatura',
                document_number VARCHAR(64) NULL,
                supplier_name VARCHAR(255) NULL,
                supplier_taxno VARCHAR(32) NULL,
                doc_date DATE NULL,
                currency VARCHAR(8) NOT NULL DEFAULT 'TRY',
                base DECIMAL(15,2) NOT NULL DEFAULT 0,
                vat DECIMAL(15,2) NOT NULL DEFAULT 0,
                total DECIMAL(15,2) NOT NULL DEFAULT 0,
                status VARCHAR(16) NOT NULL DEFAULT 'downloaded',
                raw_xml MEDIUMTEXT NULL,
                entry_id BIGINT UNSIGNED NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                PRIMARY KEY (id),
                KEY idx_gibin_tenant_docnum (tenant_id, doc_type, document_number)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(): void
    {
        DB::execute('DROP TABLE IF EXISTS gib_inbound_documents');
    }
};

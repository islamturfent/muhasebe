<?php

declare(strict_types=1);

use Muh\Core\DB;

/**
 * Per-warehouse stock quantity (Item 3): a product_warehouses table holding
 * the running quantity per (product, warehouse). Kept in sync by
 * InventoryService::recordMovement (which already records warehouse-scoped
 * movements). products.stock_quantity remains the global total.
 */
return new class {
    public function up(): void
    {
        DB::execute(
            'CREATE TABLE IF NOT EXISTS product_warehouses (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT NOT NULL,
                company_id INT NULL,
                product_id INT UNSIGNED NOT NULL,
                warehouse_id INT UNSIGNED NOT NULL,
                quantity DECIMAL(18,4) NOT NULL DEFAULT 0,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_product_warehouse (product_id, warehouse_id),
                KEY idx_pw_tenant_product (tenant_id, product_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        // Seed current known quantities from stock movements (best-effort).
        DB::execute(
            'INSERT INTO product_warehouses (tenant_id, company_id, product_id, warehouse_id, quantity, created_at, updated_at)
             SELECT p.tenant_id, p.company_id, p.id, sm.warehouse_id, SUM(sm.quantity), NOW(), NOW()
               FROM stock_movements sm JOIN products p ON p.id = sm.product_id
              GROUP BY p.tenant_id, p.company_id, p.id, sm.warehouse_id
              ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)'
        );
    }

    public function down(): void
    {
        DB::execute('DROP TABLE IF EXISTS product_warehouses');
    }
};

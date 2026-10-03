<?php

declare(strict_types=1);

namespace Muh\Services;

use Muh\Core\Auth;
use Muh\Core\DB;
use Muh\Core\Request;
use Muh\Core\Validator;
use Muh\Core\ValidationException;

/**
 * Inventory & warehouse business logic (Phase 5).
 * All writes are tenant-scoped, validated and audited.
 */
final class InventoryService
{
    public static function units(): array
    {
        return DB::select('SELECT * FROM units ORDER BY name ASC');
    }

    private function tenantId(): int
    {
        return (int) Auth::tenantId();
    }

    public function warehouses(?int $companyId = null): array
    {
        $tenantId = $this->tenantId();
        $sql = 'SELECT w.*, c.name AS company_name FROM warehouses w
                 JOIN companies c ON c.id = w.company_id
                WHERE w.tenant_id = :t AND w.deleted_at IS NULL';
        $params = ['t' => $tenantId];
        if ($companyId) {
            $sql .= ' AND w.company_id = :c';
            $params['c'] = $companyId;
        }
        $sql .= ' ORDER BY w.name ASC';
        return DB::select($sql, $params);
    }

    public function createWarehouse(array $data): int
    {
        \Muh\Services\PlanLimitsService::assertCanCreate('warehouses');
        $tenantId = $this->tenantId();
        (new Validator())->validateOrFail($data, [
            'company_id' => 'required',
            'name' => 'required|min:2',
            'code' => 'required|min:1',
        ]);
        $companyId = (int) $data['company_id'];
        $this->assertCompany($tenantId, $companyId);

        // company-scoped unique code
        $exists = DB::first('SELECT id FROM warehouses WHERE company_id = :c AND code = :code AND deleted_at IS NULL', ['c' => $companyId, 'code' => $data['code']]);
        if ($exists) {
            throw new ValidationException(['code' => __('validation.unique')]);
        }

        $id = (int) DB::insert('warehouses', [
            'tenant_id' => $tenantId, 'company_id' => $companyId,
            'name' => $data['name'], 'code' => $data['code'],
            'address' => $data['address'] ?? null,
            'is_default' => (int) ($data['is_default'] ?? 0),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        AuditLogService::record('warehouse.create', 'inventory', 'warehouses', (string) $id, null, $data, $companyId, $tenantId);
        return $id;
    }

    public function products(?int $companyId = null, ?string $search = null): array
    {
        $tenantId = $this->tenantId();
        $sql = 'SELECT p.*, c.name AS company_name, u.abbr AS unit_abbr FROM products p
                 JOIN companies c ON c.id = p.company_id
                 LEFT JOIN units u ON u.id = p.unit_id
                WHERE p.tenant_id = :t AND p.deleted_at IS NULL';
        $params = ['t' => $tenantId];
        if ($companyId) {
            $sql .= ' AND p.company_id = :c';
            $params['c'] = $companyId;
        }
        if ($search) {
            $sql .= ' AND (p.name LIKE :s OR p.code LIKE :s OR p.barcode LIKE :s)';
            $params['s'] = '%' . $search . '%';
        }
        $sql .= ' ORDER BY p.name ASC';
        return DB::select($sql, $params);
    }

    public function createProduct(array $data): int
    {
        $tenantId = $this->tenantId();
        (new Validator())->validateOrFail($data, [
            'company_id' => 'required',
            'code' => 'required|min:1',
            'name' => 'required|min:2',
            'type' => 'required|in:product,service',
            'purchase_price' => 'nullable|decimal',
            'sale_price' => 'nullable|decimal',
            'vat_rate' => 'nullable|decimal',
            'critical_stock' => 'nullable|decimal',
        ]);
        $companyId = (int) $data['company_id'];
        $this->assertCompany($tenantId, $companyId);

        $exists = DB::first('SELECT id FROM products WHERE company_id = :c AND code = :code AND deleted_at IS NULL', ['c' => $companyId, 'code' => $data['code']]);
        if ($exists) {
            throw new ValidationException(['code' => __('validation.unique')]);
        }

        // Resolve unit (by id, or by abbr, else null)
        $unitId = null;
        if (!empty($data['unit_id'])) {
            $unit = DB::first('SELECT id FROM units WHERE id = :id', ['id' => (int)$data['unit_id']]);
            if ($unit) {
                $unitId = (int) $unit['id'];
            }
        }

        $id = (int) DB::transaction(function () use ($tenantId, $companyId, $data, $unitId) {
            $isService = ($data['type'] ?? 'product') === 'service';
            $id = (int) DB::insert('products', [
                'tenant_id' => $tenantId, 'company_id' => $companyId, 'unit_id' => $unitId,
                'code' => $data['code'], 'name' => $data['name'],
                'barcode' => $data['barcode'] ?? null,
                'type' => $data['type'] ?? 'product',
                'purchase_price' => ($data['purchase_price'] ?? '') === '' ? 0 : (float)$data['purchase_price'],
                'sale_price' => ($data['sale_price'] ?? '') === '' ? 0 : (float)$data['sale_price'],
                'vat_rate' => ($data['vat_rate'] ?? '') === '' ? 0 : (float)$data['vat_rate'],
                'stock_quantity' => 0,
                'critical_stock' => ($data['critical_stock'] ?? '') === '' ? 0 : (float)$data['critical_stock'],
                'description' => $data['description'] ?? null,
                'status' => 'active',
                'created_at' => now(), 'updated_at' => now(),
            ]);

            // Optional opening stock via the default warehouse of the company.
            $opening = (float) ($data['opening_stock'] ?? 0);
            if ($opening != 0 && !$isService) {
                $warehouse = DB::first('SELECT id FROM warehouses WHERE company_id = :c AND is_default = 1 AND deleted_at IS NULL', ['c' => $companyId])
                    ?: DB::first('SELECT id FROM warehouses WHERE company_id = :c AND deleted_at IS NULL ORDER BY id LIMIT 1', ['c' => $companyId]);
                if ($warehouse) {
                    self::recordMovement($tenantId, $companyId, (int)$warehouse['id'], $id, 'adjustment', date('Y-m-d'), $opening, (float)$data['sale_price'] ?? 0, __('inventory.opening_stock'), 'manual', null);
                }
            }

            AuditLogService::record('product.create', 'inventory', 'products', (string)$id, null, $data, $companyId, $tenantId);
            return $id;
        });
        return $id;
    }

    /**
     * Record a stock movement and update the product's running quantity.
     */
    public static function recordMovement(
        int $tenantId,
        int $companyId,
        int $warehouseId,
        int $productId,
        string $type,          // purchase|sale|return|transfer_in|transfer_out|adjustment
        string $date,
        float $quantity,       // signed; negative for outbound
        float $unitPrice,
        ?string $description = null,
        ?string $refType = null,
        ?string $refId = null
    ): int {
        $id = (int) DB::insert('stock_movements', [
            'tenant_id' => $tenantId, 'company_id' => $companyId,
            'warehouse_id' => $warehouseId, 'product_id' => $productId,
            'type' => $type, 'date' => $date,
            'quantity' => $quantity, 'unit_price' => $unitPrice,
            'total' => $quantity * $unitPrice,
            'reference_type' => $refType, 'reference_id' => $refId,
            'description' => $description,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Update product running stock.
        DB::execute(
            'UPDATE products SET stock_quantity = stock_quantity + :q, updated_at = :u WHERE id = :id',
            ['q' => $quantity, 'u' => now(), 'id' => $productId]
        );
        return $id;
    }

    public function productMovements(int $productId): array
    {
        return DB::select(
            'SELECT sm.*, w.name AS warehouse_name FROM stock_movements sm
              LEFT JOIN warehouses w ON w.id = sm.warehouse_id
             WHERE sm.product_id = :id ORDER BY sm.date DESC, sm.id DESC LIMIT 200',
            ['id' => $productId]
        );
    }

    public function product(int $tenantId, int $id): ?array
    {
        return DB::first(
            'SELECT p.*, u.abbr AS unit_abbr, u.name AS unit_name
               FROM products p
               LEFT JOIN units u ON u.id = p.unit_id
              WHERE p.id = :id AND p.tenant_id = :t AND p.deleted_at IS NULL',
            ['id' => $id, 't' => $tenantId]
        );
    }

    public function updateProduct(int $id, array $data): bool
    {
        $tenantId = $this->tenantId();
        $existing = $this->product($tenantId, $id);
        if (!$existing) {
            throw new \Muh\Core\NotFoundException();
        }
        $fields = ['name', 'barcode', 'type', 'purchase_price', 'sale_price', 'vat_rate', 'critical_stock', 'description'];
        $save = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $data)) {
                $save[$f] = ($data[$f] ?? '') === '' ? 0 : $data[$f];
            }
        }
        DB::update('products', $save, 'id = :id AND tenant_id = :t', ['id' => $id, 't' => $tenantId]);
        AuditLogService::record('product.update', 'inventory', 'products', (string)$id, $existing, $save, (int)$existing['company_id'], $tenantId);
        return true;
    }

    public function deleteProduct(int $id): bool
    {
        $tenantId = $this->tenantId();
        $existing = $this->product($tenantId, $id);
        if (!$existing) {
            throw new \Muh\Core\NotFoundException();
        }
        DB::update('products', ['deleted_at' => now()], 'id = :id AND tenant_id = :t', ['id' => $id, 't' => $tenantId]);
        AuditLogService::record('product.delete', 'inventory', 'products', (string)$id, $existing, ['deleted' => true], (int)$existing['company_id'], $tenantId);
        return true;
    }

    private function assertCompany(int $tenantId, int $companyId): void
    {
        $exists = DB::first('SELECT id FROM companies WHERE id = :id AND tenant_id = :t AND deleted_at IS NULL', ['id' => $companyId, 't' => $tenantId]);
        if (!$exists) {
            throw new ValidationException(['company_id' => __('validation.in')]);
        }
    }
}

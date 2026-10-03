<?php

declare(strict_types=1);

/**
 * Demo company seeder — provisions a complete, working example firm into the
 * demo office (or the first tenant that has no company yet) so new users have
 * real, browsable data: chart of accounts, warehouse, current accounts,
 * products with opening stock, and posted sales + purchase invoices with
 * full transactions (cari + stock + balanced journal).
 *
 * Idempotent: skips if the target tenant already has a company.
 */
use Muh\Core\DB;
use Muh\Services\TenantOnboardingService;
use Muh\Services\InventoryService;
use Muh\Services\CurrentAccountService;

return function (): void {
    // Target tenant: the demo office if present, else any tenant without companies.
    $tenant = DB::first('SELECT id, currency, locale FROM tenants WHERE slug = :s', ['s' => 'demo-office']);
    if (!$tenant) {
        $tenant = DB::first(
            'SELECT t.id, t.currency, t.locale FROM tenants t
              LEFT JOIN companies c ON c.tenant_id = t.id AND c.deleted_at IS NULL
             GROUP BY t.id HAVING COUNT(c.id) = 0 ORDER BY t.id ASC LIMIT 1'
        );
    }
    if (!$tenant) {
        echo "  (no free tenant for demo company, skipping)\n";
        return;
    }
    $tenantId = (int) $tenant['id'];
    $currency = $tenant['currency'] ?? 'TRY';

    if (DB::first('SELECT id FROM companies WHERE tenant_id = :t AND deleted_at IS NULL LIMIT 1', ['t' => $tenantId])) {
        echo "  (tenant {$tenantId} already has a company, skipping)\n";
        return;
    }

    $now = now();
    $year = (int) date('Y');

    DB::transaction(function () use ($tenantId, $currency, $now, $year) {
        // ---- Company + fiscal period + chart of accounts ----
        $companyId = (int) DB::insert('companies', [
            'tenant_id' => $tenantId, 'name' => 'Demo A.Ş.', 'trade_name' => 'Demo Ticaret Ltd. Şti.',
            'tax_number' => '1234567890', 'tax_office' => 'Beşiktaş', 'mersis' => '0123456789',
            'address' => 'Örnek Mah. Demo Cad. No:5, Beşiktaş/İstanbul',
            'phone' => '+90 212 000 00 00', 'email' => 'info@demo.local',
            'company_type' => 'anonim', 'currency' => $currency, 'status' => 'active',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $periodId = (int) DB::insert('fiscal_periods', [
            'tenant_id' => $tenantId, 'company_id' => $companyId, 'name' => $year . ' Dönemi',
            'start_date' => $year . '-01-01', 'end_date' => $year . '-12-31',
            'is_closed' => 0, 'is_current' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);

        $rows = [];
        foreach (TenantOnboardingService::TURKISH_CHART_OF_ACCOUNTS as $acc) {
            $rows[] = [
                'tenant_id' => $tenantId, 'company_id' => $companyId, 'fiscal_period_id' => $periodId,
                'code' => $acc['code'], 'name' => $acc['name'], 'type' => $acc['type'],
                'group' => substr($acc['code'], 0, 1), 'is_header' => $acc['is_header'],
                'currency' => $currency, 'opening_debit' => 0, 'opening_credit' => 0,
                'created_at' => $now, 'updated_at' => $now,
            ];
        }
        DB::insertMany('accounting_accounts', $rows);

        $accId = function (string $code) use ($companyId): int {
            return (int) DB::scalar(
                'SELECT id FROM accounting_accounts WHERE company_id = :c AND code = :k ORDER BY id LIMIT 1',
                ['c' => $companyId, 'k' => $code]
            );
        };

        // ---- Warehouse (default) ----
        $warehouseId = (int) DB::insert('warehouses', [
            'tenant_id' => $tenantId, 'company_id' => $companyId, 'code' => 'DEPO-1',
            'name' => 'Ana Depo', 'address' => 'Merkez Depo', 'is_default' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        // ---- Products + opening stock ----
        $mkProduct = function (string $code, string $name, string $type, float $purchase, float $sale, float $vat, float $opening) use ($tenantId, $companyId, $warehouseId, $now) {
            $pid = (int) DB::insert('products', [
                'tenant_id' => $tenantId, 'company_id' => $companyId, 'code' => $code, 'name' => $name,
                'type' => $type, 'purchase_price' => $purchase, 'sale_price' => $sale,
                'vat_rate' => $vat, 'stock_quantity' => 0, 'status' => 'active',
                'created_at' => $now, 'updated_at' => $now,
            ]);
            if ($opening > 0) {
                InventoryService::recordMovement($tenantId, $companyId, $warehouseId, $pid, 'opening', date('Y-m-d'), $opening, $purchase, 'Açılış stoğu', 'opening', 'demo');
            }
            return $pid;
        };
        $phone = $mkProduct('UR-100', 'Akıllı Telefon', 'product', 12000, 15000, 20, 20);
        $laptop = $mkProduct('UR-200', 'Dizüstü Bilgisayar', 'product', 9000, 12000, 20, 10);
        $mkProduct('HR-001', 'Danışmanlık Hizmeti', 'service', 0, 2000, 20, 0);

        // ---- Current accounts ----
        $customer = (int) DB::insert('current_accounts', [
            'tenant_id' => $tenantId, 'company_id' => $companyId, 'code' => '120-001', 'name' => 'Arda Market',
            'type' => 'customer', 'tax_number' => '9876543210', 'balance' => 0, 'status' => 'active',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $supplier = (int) DB::insert('current_accounts', [
            'tenant_id' => $tenantId, 'company_id' => $companyId, 'code' => '320-001', 'name' => 'Tedarik A.Ş.',
            'type' => 'supplier', 'tax_number' => '5554443332', 'balance' => 0, 'status' => 'active',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        // ---- Post helpers ----
        $postInvoice = function (
            int $currentAccountId, string $type, string $date, string $number, string $description,
            array $lines, string $cariMovementType, array $stock, array $journalAccts
        ) use ($tenantId, $companyId, $periodId, $warehouseId, $now, $accId) {
            $subtotal = 0.0; $tax = 0.0; $total = 0.0;
            $items = [];
            foreach ($lines as $l) {
                $net = round($l['qty'] * $l['price'], 2);
                $lineTax = round($net * $l['vat'] / 100, 2);
                $lineTotal = round($net + $lineTax, 2);
                $subtotal += $net; $tax += $lineTax; $total += $lineTotal;
                $items[] = [
                    'tenant_id' => $tenantId, 'company_id' => $companyId, 'invoice_id' => 0,
                    'product_id' => $l['product_id'], 'description' => $l['description'],
                    'quantity' => $l['qty'], 'unit_price' => $l['price'], 'discount' => 0,
                    'tax_rate' => $l['vat'], 'tax' => $lineTax,
                    'line_total' => $net, 'total' => $lineTotal, 'created_at' => $now, 'updated_at' => $now,
                ];
            }

            $invoiceId = (int) DB::insert('invoices', [
                'tenant_id' => $tenantId, 'company_id' => $companyId, 'fiscal_period_id' => $periodId,
                'current_account_id' => $currentAccountId, 'number' => $number, 'type' => $type,
                'status' => 'posted', 'efatura_status' => 'draft', 'date' => $date, 'due_date' => date('Y-m-d', strtotime($date . ' +30 days')),
                'subtotal' => $subtotal, 'discount' => 0, 'tax' => $tax, 'total' => $total, 'paid' => 0,
                'notes' => $description, 'created_at' => $now, 'updated_at' => $now,
            ]);

            foreach ($items as $k => $it) {
                $items[$k]['invoice_id'] = $invoiceId;
            }
            DB::insertMany('invoice_items', $items);

            // Cari movement
            CurrentAccountService::addMovement($tenantId, $companyId, $currentAccountId, $cariMovementType, $date, $total, $description, 'invoice', (string) $invoiceId);

            // Stock movements
            foreach ($stock as $s) {
                InventoryService::recordMovement($tenantId, $companyId, $warehouseId, $s['product_id'], $s['type'], $date, $s['qty'], $s['price'], $description, 'invoice', (string) $invoiceId);
            }

            // Balanced journal
            $dbt = 0.0; $crt = 0.0;
            foreach ($journalAccts as $j) {
                if ($j['debit'] > 0) { $dbt += $j['debit']; }
                if ($j['credit'] > 0) { $crt += $j['credit']; }
            }
            $entryId = (int) DB::insert('accounting_entries', [
                'tenant_id' => $tenantId, 'company_id' => $companyId, 'fiscal_period_id' => $periodId,
                'created_by' => null, 'voucher_type' => 'journal', 'number' => $number,
                'date' => $date, 'description' => $description, 'debit_total' => $dbt, 'credit_total' => $crt,
                'status' => 'posted', 'reference_type' => 'invoice', 'reference_id' => (string) $invoiceId,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            foreach ($journalAccts as $j) {
                DB::insert('accounting_entry_lines', [
                    'tenant_id' => $tenantId, 'company_id' => $companyId, 'entry_id' => $entryId,
                    'account_id' => $accId($j['code']),
                    'debit' => $j['debit'], 'credit' => $j['credit'], 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            return $invoiceId;
        };

        // Sales invoice: 2× Akıllı Telefon @ 15000 + 20% KDV → total 36000
        $postInvoice(
            $customer, 'sales', $year . '-01-15', 'F-S-' . $year . '-0001', 'Akıllı Telefon satışı',
            [['product_id' => $phone, 'description' => 'Akıllı Telefon', 'qty' => 2, 'price' => 15000, 'vat' => 20]],
            'debt',
            [['product_id' => $phone, 'type' => 'sale', 'qty' => -2, 'price' => 12000]],
            [
                ['code' => '120', 'name' => 'Alıcılar', 'debit' => 36000, 'credit' => 0],
                ['code' => '600', 'name' => 'Yurt İçi Satışlar', 'debit' => 0, 'credit' => 30000],
                ['code' => '391', 'name' => 'Hesaplanan KDV', 'debit' => 0, 'credit' => 6000],
            ]
        );

        // Purchase invoice: 3× Dizüstü Bilgisayar @ 9000 + 20% KDV → total 32400
        $postInvoice(
            $supplier, 'purchase', $year . '-02-10', 'F-A-' . $year . '-0001', 'Dizüstü Bilgisayar alımı',
            [['product_id' => $laptop, 'description' => 'Dizüstü Bilgisayar', 'qty' => 3, 'price' => 9000, 'vat' => 20]],
            'credit',
            [['product_id' => $laptop, 'type' => 'purchase', 'qty' => 3, 'price' => 9000]],
            [
                ['code' => '153', 'name' => 'Ticari Mallar', 'debit' => 27000, 'credit' => 0],
                ['code' => '191', 'name' => 'İndirilecek KDV', 'debit' => 5400, 'credit' => 0],
                ['code' => '320', 'name' => 'Satıcılar', 'debit' => 0, 'credit' => 32400],
            ]
        );
    });

    echo "  Demo company provisioned for tenant {$tenantId}\n";
};

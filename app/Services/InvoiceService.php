<?php

declare(strict_types=1);

namespace Muh\Services;

use Muh\Core\Auth;
use Muh\Core\DB;
use Muh\Core\Validator;
use Muh\Core\ValidationException;
use Muh\Core\Request;

/**
 * Sales/Purchase invoice posting (Phase 6) — the vertical slice that ties the
 * modules together in one transaction:
 *   invoice + items → current account movement → stock movement → VAT → journal.
 */
final class InvoiceService
{
    /**
     * Create and post an invoice atomically.
     *
     * Expected $data:
     *   company_id, type (sales|purchase), current_account_id, date, due_date,
     *   notes, lines: [ ['product_id'=>?, 'description'=>?, 'qty'=>?, 'unit_price'=>?, 'vat_rate'=>?, 'discount'=>?], ... ]
     *
     * @return int invoice id
     */
    public function create(array $data, ?Request $request = null): int
    {
        \Muh\Services\PlanLimitsService::assertCanCreate('invoices');
        $tenantId = (int) Auth::tenantId();

        $v = new Validator();
        $v->validateOrFail($data, [
            'company_id'         => 'required',
            'current_account_id' => 'required',
            'type'               => 'required|in:sales,purchase',
            'date'               => 'required|date',
            'due_date'           => 'nullable|date',
        ]);
        $lines = $data['lines'] ?? [];
        if (!is_array($lines) || empty($lines)) {
            throw new ValidationException(['lines' => __('invoice.lines_required')]);
        }

        $companyId = (int) $data['company_id'];
        $this->assertCompany($tenantId, $companyId);
        $account = $this->assertCurrentAccount($tenantId, (int) $data['current_account_id'], $companyId);

        $date = $data['date'];
        $dueDate = $data['due_date'] ?? $date;
        $type = $data['type'];

        // --- Compute line totals (rounded to 2 decimals) ---
        $preparedLines = [];
        $subtotal = 0.0;
        $totalDiscount = 0.0;
        $totalTax = 0.0;
        $total = 0.0;

        foreach ($lines as $i => $raw) {
            $qty = (float) ($raw['qty'] ?? 0);
            $price = (float) ($raw['unit_price'] ?? 0);
            $vat = (float) ($raw['vat_rate'] ?? 0);
            $disc = (float) ($raw['discount'] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $net = round($qty * $price, 2);
            $discountAmt = round($net * $disc / 100, 2);
            $netAfterDiscount = round($net - $discountAmt, 2);
            $tax = round($netAfterDiscount * $vat / 100, 2);
            $lineTotal = round($netAfterDiscount + $tax, 2);

            $subtotal += $net;
            $totalDiscount += $discountAmt;
            $totalTax += $tax;
            $total += $lineTotal;

            $preparedLines[] = [
                'product_id'  => !empty($raw['product_id']) ? (int) $raw['product_id'] : null,
                'description' => $raw['description'] ?? null,
                'quantity'    => $qty,
                'unit_price'  => $price,
                'discount'    => $disc,
                'tax_rate'    => $vat,
                'tax'         => $tax,
                'line_total'  => $netAfterDiscount,
                'total'       => $lineTotal,
            ];
        }

        if (empty($preparedLines)) {
            throw new ValidationException(['lines' => __('invoice.lines_required')]);
        }

        $subtotal = round($subtotal, 2);
        $totalDiscount = round($totalDiscount, 2);
        $totalTax = round($totalTax, 2);
        $total = round($total, 2);

        $period = DB::first(
            'SELECT id FROM fiscal_periods WHERE company_id = :c AND is_current = 1 AND deleted_at IS NULL ORDER BY id LIMIT 1',
            ['c' => $companyId]
        ) ?: DB::first('SELECT id FROM fiscal_periods WHERE company_id = :c ORDER BY id LIMIT 1', ['c' => $companyId]);
        if (!$period) {
            throw new ValidationException(['company_id' => __('invoice.no_period')]);
        }
        $periodId = (int) $period['id'];

        // --- Default warehouse for stock movement ---
        $warehouse = DB::first('SELECT id FROM warehouses WHERE company_id = :c AND is_default = 1 AND deleted_at IS NULL', ['c' => $companyId])
            ?: DB::first('SELECT id FROM warehouses WHERE company_id = :c AND deleted_at IS NULL ORDER BY id LIMIT 1', ['c' => $companyId]);

        $invoiceId = (int) DB::transaction(function () use (
            $tenantId, $companyId, $periodId, $type, $date, $dueDate, $data,
            $preparedLines, $subtotal, $totalDiscount, $totalTax, $total,
            $account, $warehouse, $request
        ) {
            // 1. Insert invoice (posted)
            $number = $this->nextInvoiceNumber($companyId, $type);
            $invoiceId = (int) DB::insert('invoices', [
                'tenant_id'          => $tenantId,
                'company_id'         => $companyId,
                'fiscal_period_id'   => $periodId,
                'current_account_id' => (int) $account['id'],
                'number'             => $number,
                'type'               => $type,
                'status'             => 'posted',
                'efatura_status'     => 'draft',
                'date'               => $date,
                'due_date'           => $dueDate,
                'document_no'        => $data['document_no'] ?? null,
                'subtotal'           => $subtotal,
                'discount'           => $totalDiscount,
                'tax'                => $totalTax,
                'total'              => $total,
                'paid'               => 0,
                'notes'              => $data['notes'] ?? null,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);

            // 2. Invoice items
            $rows = [];
            foreach ($preparedLines as $l) {
                $rows[] = array_merge($l, [
                    'tenant_id'  => $tenantId,
                    'company_id' => $companyId,
                    'invoice_id' => $invoiceId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            DB::insertMany('invoice_items', $rows);

            // 3. Current account movement
            $movementType = $type === 'sales' ? 'debt' : 'credit';
            $this->recordAccountMovement($tenantId, $companyId, (int) $account['id'], $movementType, $date, $total, $data['notes'] ?? null, 'invoice', (string) $invoiceId);

            // 4. Stock movement (sales reduce stock; purchases increase it)
            if ($warehouse) {
                foreach ($preparedLines as $l) {
                    if (empty($l['product_id'])) {
                        continue;
                    }
                    $prod = DB::first('SELECT id, type FROM products WHERE id = :id', ['id' => $l['product_id']]);
                    if (!$prod || $prod['type'] === 'service') {
                        continue;
                    }
                    $qty = $type === 'sales' ? -abs($l['quantity']) : abs($l['quantity']);
                    InventoryService::recordMovement(
                        $tenantId, $companyId, (int) $warehouse['id'], (int) $prod['id'],
                        $type === 'sales' ? 'sale' : 'purchase',
                        $date, $qty, $l['unit_price'],
                        __('invoice.stock_' . $type, ['no' => $number]),
                        'invoice', (string) $invoiceId
                    );
                }
            }

            // 5. Accounting entry (double-entry, debit = credit enforced)
            $this->postInvoiceJournal($tenantId, $companyId, $periodId, $type, $date, $number, $subtotal, $totalDiscount, $totalTax, $total, $invoiceId);

            AuditLogService::record('invoice.create', 'invoice', 'invoices', (string) $invoiceId, null, ['number' => $number, 'total' => $total, 'type' => $type], $companyId, $tenantId, null, $request);
            return $invoiceId;
        });

        return $invoiceId;
    }

    /**
     * Create the same invoice (template) for several current accounts at once.
     * Each account is posted atomically via {@see create()} (cari + stock +
     * VAT + balanced journal). Returns created ids and per-account failures.
     */
    public function createBulk(array $data, array $accountIds, ?Request $request = null): array
    {
        $created = [];
        $failed = [];
        foreach (array_unique(array_filter($accountIds)) as $acctId) {
            $row = $data;
            $row['current_account_id'] = (int) $acctId;
            try {
                $created[] = $this->create($row, $request);
            } catch (ValidationException $e) {
                $failed[(int) $acctId] = array_values($e->errors);
            }
        }
        return ['created' => $created, 'failed' => $failed];
    }

    private function postInvoiceJournal(
        int $tenantId, int $companyId, int $periodId, string $type,
        string $date, string $number, float $subtotal, float $discount, float $tax, float $total,
        int $invoiceId
    ): void {
        $lines = [];
        $net = round($subtotal - $discount, 2);

        if ($type === 'sales') {
            // Debit 120 Alıcılar (receivable), credit 600 Satışlar + 391 KDV
            $lines[] = ['account_code' => '120', 'debit' => $total, 'credit' => 0];
            $lines[] = ['account_code' => '600', 'debit' => 0, 'credit' => $net];
            if ($tax > 0) {
                $lines[] = ['account_code' => '391', 'debit' => 0, 'credit' => $tax];
            }
        } else {
            // Purchase: debit 153/620 (inventory) + 191 KDV, credit 320 Satıcılar
            $lines[] = ['account_code' => '620', 'debit' => $net, 'credit' => 0];
            if ($tax > 0) {
                $lines[] = ['account_code' => '191', 'debit' => $tax, 'credit' => 0];
            }
            $lines[] = ['account_code' => '320', 'debit' => 0, 'credit' => $total];
        }

        AccountingService::postEntry(
            $tenantId, $companyId, $periodId,
            'journal', $date,
            __('invoice.journal_' . $type, ['no' => $number]),
            $lines,
            null,
            'invoice',
            (string) $invoiceId
        );
    }

    private function recordAccountMovement(
        int $tenantId, int $companyId, int $accountId, string $type, string $date, float $amount,
        ?string $description, string $refType, string $refId
    ): int {
        return CurrentAccountService::addMovement($tenantId, $companyId, $accountId, $type, $date, $amount, $description, $refType, $refId);
    }

    private function nextInvoiceNumber(int $companyId, string $type): string
    {
        $prefix = $type === 'sales' ? 'F-S' : 'F-A'; // satış / alış
        $seq = ((int) DB::scalar(
            'SELECT COUNT(*)+1 FROM invoices WHERE company_id = :c AND type = :t AND deleted_at IS NULL',
            ['c' => $companyId, 't' => $type]
        ));
        return $prefix . '-' . date('Y') . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function assertCompany(int $tenantId, int $companyId): void
    {
        $exists = DB::first('SELECT id FROM companies WHERE id = :id AND tenant_id = :t AND deleted_at IS NULL', ['id' => $companyId, 't' => $tenantId]);
        if (!$exists) {
            throw new ValidationException(['company_id' => __('validation.in')]);
        }
    }

    private function assertCurrentAccount(int $tenantId, int $accountId, int $companyId): array
    {
        $account = DB::first(
            'SELECT * FROM current_accounts WHERE id = :id AND tenant_id = :t AND company_id = :c AND deleted_at IS NULL',
            ['id' => $accountId, 't' => $tenantId, 'c' => $companyId]
        );
        if (!$account) {
            throw new ValidationException(['current_account_id' => __('validation.in')]);
        }
        return $account;
    }
}

<?php

declare(strict_types=1);

namespace Muh\Services;

use Muh\Core\Auth;
use Muh\Core\DB;
use Muh\Core\Validator;
use Muh\Core\ValidationException;
use Muh\Core\Request;

/**
 * Business logic for current accounts (cari hesap).
 * Every write is tenant-scoped, validated, balance-recomputed and audited.
 */
final class CurrentAccountService
{
    private function tenantId(): int
    {
        return (int) Auth::tenantId();
    }

    /**
     * Recompute a current account balance from its movements (borç - alacak).
     */
    public static function recomputeBalance(int $tenantId, int $currentAccountId): void
    {
        // debit increases what the account "owes us" when customer (borç),
        // credit decreases it. For suppliers the sign convention is inverted
        // in the UI; here we store net balance = Σ(debt) - Σ(credit).
        $net = (float) DB::scalar(
            "SELECT COALESCE(SUM(CASE WHEN type IN ('debt','payment') THEN amount
                                      WHEN type IN ('credit','collection') THEN -amount
                                      ELSE 0 END), 0)
               FROM current_account_transactions
              WHERE current_account_id = :id",
            ['id' => $currentAccountId]
        );

        DB::update('current_accounts', ['balance' => $net], 'id = :id AND tenant_id = :t', ['id' => $currentAccountId, 't' => $tenantId]);
    }

    public static function addMovement(
        int $tenantId,
        int $companyId,
        int $currentAccountId,
        string $type,
        string $date,
        float $amount,
        ?string $description = null,
        ?string $refType = null,
        ?string $refId = null,
        ?Request $request = null
    ): int {
        $id = (int) DB::insert('current_account_transactions', [
            'tenant_id'           => $tenantId,
            'company_id'          => $companyId,
            'current_account_id'  => $currentAccountId,
            'type'                => $type,
            'date'                => $date,
            'amount'              => $amount,
            'description'         => $description,
            'reference_type'      => $refType,
            'reference_id'        => $refId,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        self::recomputeBalance($tenantId, $currentAccountId);

        AuditLogService::record('current_account.movement', 'current_account', 'current_accounts', (string) $currentAccountId, null, [
            'type' => $type, 'amount' => $amount, 'date' => $date,
        ], (int) $companyId, $tenantId, null, $request);

        return $id;
    }

    public function create(array $data, ?Request $request = null): int
    {
        $tenantId = $this->tenantId();
        $v = new Validator();
        $rules = [
            'company_id'    => 'required',
            'code'          => 'required|min:1',
            'name'          => 'required|min:2',
            'type'          => 'required|in:customer,supplier,both',
            'risk_limit'    => 'nullable|decimal',
            'opening_balance' => 'nullable|decimal',
        ];
        if (!$v->validate($data, $rules)) {
            throw new ValidationException($v->errors());
        }

        $companyId = (int) $data['company_id'];
        $this->assertCompanyBelongsToTenant($tenantId, $companyId);

        // Enforce plan limit on the number of current accounts is handled by
        // plan quotas elsewhere; here we just ensure company-level uniqueness.
        $exists = DB::first(
            'SELECT id FROM current_accounts WHERE company_id = :c AND code = :code AND deleted_at IS NULL',
            ['c' => $companyId, 'code' => $data['code']]
        );
        if ($exists) {
            throw new ValidationException(['code' => __('validation.unique')]);
        }

        $opening = (float) ($data['opening_balance'] ?? 0);

        $id = (int) DB::transaction(function () use ($tenantId, $companyId, $data, $opening) {
            $id = (int) DB::insert('current_accounts', [
                'tenant_id'    => $tenantId,
                'company_id'   => $companyId,
                'code'         => $data['code'],
                'name'         => $data['name'],
                'type'         => $data['type'],
                'tax_number'   => $data['tax_number'] ?? null,
                'email'        => $data['email'] ?? null,
                'phone'        => $data['phone'] ?? null,
                'address'      => $data['address'] ?? null,
                'iban'         => $data['iban'] ?? null,
                'risk_limit'   => ($data['risk_limit'] ?? '') === '' ? 0 : (float) $data['risk_limit'],
                'balance'      => $opening,
                'locale'       => ($data['locale'] ?? '') === 'en' ? 'en' : 'tr',
                'status'       => $data['status'] ?? 'active',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            // If there's an opening balance, record it as a movement.
            if ($opening != 0) {
                self::addMovement(
                    $tenantId, $companyId, $id,
                    $opening > 0 ? 'debt' : 'credit',
                    $data['opening_date'] ?? date('Y-m-d'),
                    abs($opening),
                    __('current_account.opening_balance'),
                    'manual',
                    null
                );
            }

            AuditLogService::record('current_account.create', 'current_account', 'current_accounts', (string) $id, null, $data);
            return $id;
        });

        return $id;
    }

    public function update(int $id, array $data): bool
    {
        $tenantId = $this->tenantId();
        $existing = $this->findForTenant($tenantId, $id);
        if (!$existing) {
            throw new \Muh\Core\NotFoundException();
        }

        $fields = ['name', 'type', 'tax_number', 'email', 'phone', 'address', 'iban', 'risk_limit', 'status', 'locale'];
        $save = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $data)) {
                // risk_limit is NOT NULL numeric — empty maps to 0.
                $save[$f] = ($f === 'risk_limit' && ($data[$f] ?? '') === '') ? 0 : $data[$f];
            }
        }

        $result = DB::update(
            'current_accounts',
            $save,
            'id = :id AND tenant_id = :t',
            ['id' => $id, 't' => $tenantId]
        );

        AuditLogService::record('current_account.update', 'current_account', 'current_accounts', (string) $id, $existing, $save, (int) $existing['company_id'], $tenantId);
        return $result >= 0;
    }

    public function delete(int $id): bool
    {
        $tenantId = $this->tenantId();
        $existing = $this->findForTenant($tenantId, $id);
        if (!$existing) {
            throw new \Muh\Core\NotFoundException();
        }
        $result = DB::update(
            'current_accounts',
            ['deleted_at' => now()],
            'id = :id AND tenant_id = :t',
            ['id' => $id, 't' => $tenantId]
        );
        AuditLogService::record('current_account.delete', 'current_account', 'current_accounts', (string) $id, $existing, ['deleted' => true], (int) $existing['company_id'], $tenantId);
        return $result > 0;
    }

    public function findForTenant(int $tenantId, int $id): ?array
    {
        return DB::first(
            'SELECT * FROM current_accounts WHERE id = :id AND tenant_id = :t AND deleted_at IS NULL',
            ['id' => $id, 't' => $tenantId]
        );
    }

    public function listForCompany(int $tenantId, ?int $companyId, ?string $type = null, ?string $search = null): array
    {
        $sql = 'SELECT ca.*, c.name AS company_name FROM current_accounts ca
                 JOIN companies c ON c.id = ca.company_id
                WHERE ca.tenant_id = :t AND ca.deleted_at IS NULL';
        $params = ['t' => $tenantId];

        if ($companyId) {
            $sql .= ' AND ca.company_id = :c';
            $params['c'] = $companyId;
        }
        if ($type && in_array($type, ['customer', 'supplier', 'both'], true)) {
            $sql .= ' AND ca.type = :type';
            $params['type'] = $type;
        }
        if ($search) {
            $sql .= ' AND (ca.name LIKE :s OR ca.code LIKE :s OR ca.tax_number LIKE :s)';
            $params['s'] = '%' . $search . '%';
        }
        $sql .= ' ORDER BY ca.name ASC';
        return DB::select($sql, $params);
    }

    /**
     * Compute a current-account statement (ekstre): opening balance, in-range
     * debit/credit totals and closing balance, plus the in-range rows.
     * Sign convention: debt/payment = debit (+), credit/collection = credit (-).
     *
     * @return array{opening:float, debit:float, credit:float, closing:float, rows:array}
     */
    public static function statement(int $currentAccountId, ?string $from = null, ?string $to = null): array
    {
        $txs = DB::select(
            'SELECT * FROM current_account_transactions WHERE current_account_id = :id ORDER BY date ASC, id ASC',
            ['id' => $currentAccountId]
        );
        $opening = 0.0;
        $rows = [];
        $debit = 0.0;
        $credit = 0.0;
        $running = 0.0;
        foreach ($txs as $t) {
            $sign = in_array($t['type'], ['debt', 'payment'], true) ? 1 : -1;
            $amt = (float) $t['amount'];
            // accumulate opening balance from movements strictly before $from
            if ($from !== null && $t['date'] < $from) {
                $opening += $sign * $amt;
                continue;
            }
            if ($to !== null && $t['date'] > $to) {
                continue;
            }
            $running += $sign * $amt;
            if ($sign > 0) {
                $debit += $amt;
            } else {
                $credit += $amt;
            }
            $row = $t;
            $row['sign'] = $sign;
            $row['running'] = $running;
            $rows[] = $row;
        }
        return [
            'opening' => $opening,
            'debit'   => $debit,
            'credit'  => $credit,
            'closing' => $opening + $debit - $credit,
            'rows'    => $rows,
        ];
    }

    /**
     * Post a manual collection (tahsil) or payment (tediye) on a current account
     * atomically: cari movement + cash/bank transaction + balanced journal.
     *
     * @param string $type  'collection' (çek-alış → müşteriden tahsil) | 'payment' (ödeme)
     * @param string $targetType 'cash'|'bank'
     */
    public static function postCollectionPayment(
        int $tenantId,
        int $companyId,
        int $currentAccountId,
        string $type,
        string $date,
        float $amount,
        string $targetType,
        int $targetId,
        ?string $description = null,
        ?int $invoiceId = null
    ): int {
        if (!in_array($type, ['collection', 'payment'], true)) {
            throw new \Muh\Core\ValidationException(['type' => __('validation.in')]);
        }
        $description = $description ?: ($type === 'collection' ? __('current_account.pay_collection') : __('current_account.pay_payment'));

        DB::transaction(function () use ($tenantId, $companyId, $currentAccountId, $type, $date, $amount, $targetType, $targetId, $description, $invoiceId): void {
            // 1. Cari movement
            $movId = self::addMovement($tenantId, $companyId, $currentAccountId, $type, $date, $amount, $description, 'manual', (string) $currentAccountId);

            // 2. Cash / bank transaction
            if ($targetType === 'bank') {
                \Muh\Services\BankService::addTransaction(
                    $tenantId, $companyId, $targetId,
                    $type === 'collection' ? 'deposit' : 'withdrawal',
                    $date, $amount, $description
                );
                $assetCode = '102';
            } else {
                \Muh\Services\CashService::addTransaction($tenantId, $companyId, $targetId, $type, $date, $amount, $description);
                $assetCode = '100';
            }

            // 3. Balanced journal: collection → DR cash/bank, CR 120; payment → DR 320, CR cash/bank
            $cariCode = $type === 'collection' ? '120' : '320';
            $lines = $type === 'collection'
                ? [['account_code' => $assetCode, 'debit' => $amount, 'credit' => 0], ['account_code' => $cariCode, 'debit' => 0, 'credit' => $amount]]
                : [['account_code' => $cariCode, 'debit' => $amount, 'credit' => 0], ['account_code' => $assetCode, 'debit' => 0, 'credit' => $amount]];

            $periodId = (int) DB::scalar(
                'SELECT id FROM fiscal_periods WHERE company_id = :c AND :d BETWEEN start_date AND end_date ORDER BY start_date DESC LIMIT 1',
                ['c' => $companyId, 'd' => $date]
            ) ?: (int) DB::scalar('SELECT id FROM fiscal_periods WHERE company_id = :c ORDER BY start_date DESC LIMIT 1', ['c' => $companyId]);

            if ($periodId) {
                \Muh\Services\AccountingService::postEntry($tenantId, $companyId, $periodId, 'journal', $date, $description, $lines, null, 'payment', (string) $movId);
            }

            // 4. Optionally mark an invoice as (partially) paid.
            if ($invoiceId) {
                $inv = DB::first('SELECT id, paid, total FROM invoices WHERE id = :id AND company_id = :c AND deleted_at IS NULL', ['id' => (int) $invoiceId, 'c' => $companyId]);
                if ($inv) {
                    $newPaid = round((float) $inv['paid'] + $amount, 2);
                    if ($newPaid > (float) $inv['total']) {
                        $newPaid = (float) $inv['total'];
                    }
                    DB::update('invoices', ['paid' => $newPaid], 'id = :id', ['id' => (int) $invoiceId]);
                }
            }

            AuditLogService::record('current_account.payment', 'cari', 'current_accounts', (string) $currentAccountId, null, ['type' => $type, 'amount' => $amount, 'target' => $targetType . ':' . $targetId], $companyId, $tenantId);
        });

        return 1;
    }

    public function transactions(int $currentAccountId, ?string $from = null, ?string $to = null, ?string $type = null): array
    {
        $sql = 'SELECT * FROM current_account_transactions WHERE current_account_id = :id';
        $params = ['id' => $currentAccountId];
        if ($from) {
            $sql .= ' AND date >= :from';
            $params['from'] = $from;
        }
        if ($to) {
            $sql .= ' AND date <= :to';
            $params['to'] = $to;
        }
        if ($type && in_array($type, ['debt', 'credit', 'payment', 'collection'], true)) {
            $sql .= ' AND type = :type';
            $params['type'] = $type;
        }
        $sql .= ' ORDER BY date DESC, id DESC';
        return paginate($sql, $params, 50);
    }

    private function assertCompanyBelongsToTenant(int $tenantId, int $companyId): void
    {
        $exists = DB::first(
            'SELECT id FROM companies WHERE id = :id AND tenant_id = :t AND deleted_at IS NULL',
            ['id' => $companyId, 't' => $tenantId]
        );
        if (!$exists) {
            throw new ValidationException(['company_id' => __('validation.in')]);
        }
    }
}

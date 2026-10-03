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

        $fields = ['name', 'type', 'tax_number', 'email', 'phone', 'address', 'iban', 'risk_limit', 'status'];
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

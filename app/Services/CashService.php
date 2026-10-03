<?php

declare(strict_types=1);

namespace Muh\Services;

use Muh\Core\Auth;
use Muh\Core\DB;
use Muh\Core\Validator;
use Muh\Core\ValidationException;

/**
 * Cash (kasa) ledger — Phase 7.
 */
final class CashService
{
    // types that increase / decrease the cash balance
    private const PLUS = ['collection', 'opening', 'transfer'];
    private const MINUS = ['payment'];

    public function accounts(?int $companyId = null): array
    {
        $sql = 'SELECT csa.*, c.name AS company_name FROM cash_accounts csa
                 JOIN companies c ON c.id = csa.company_id
                WHERE csa.tenant_id = :t AND csa.deleted_at IS NULL';
        $params = ['t' => Auth::tenantId()];
        if ($companyId) {
            $sql .= ' AND csa.company_id = :c';
            $params['c'] = $companyId;
        }
        $sql .= ' ORDER BY csa.name ASC';
        return DB::select($sql, $params);
    }

    public function account(int $tenantId, int $id): ?array
    {
        return DB::first('SELECT * FROM cash_accounts WHERE id = :id AND tenant_id = :t AND deleted_at IS NULL', ['id' => $id, 't' => $tenantId]);
    }

    public function createAccount(array $data): int
    {
        $tenantId = (int) Auth::tenantId();
        (new Validator())->validateOrFail($data, [
            'company_id' => 'required', 'name' => 'required|min:2', 'code' => 'required|min:1',
        ]);
        $companyId = (int) $data['company_id'];
        if (!DB::first('SELECT id FROM companies WHERE id = :id AND tenant_id = :t AND deleted_at IS NULL', ['id' => $companyId, 't' => $tenantId])) {
            throw new ValidationException(['company_id' => __('validation.in')]);
        }
        if (DB::first('SELECT id FROM cash_accounts WHERE company_id = :c AND code = :code AND deleted_at IS NULL', ['c' => $companyId, 'code' => $data['code']])) {
            throw new ValidationException(['code' => __('validation.unique')]);
        }
        $id = (int) DB::insert('cash_accounts', [
            'tenant_id' => $tenantId, 'company_id' => $companyId,
            'name' => $data['name'], 'code' => $data['code'],
            'currency' => $data['currency'] ?? 'TRY', 'balance' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        AuditLogService::record('cash.create', 'cash', 'cash_accounts', (string)$id, null, $data, $companyId, $tenantId);
        return $id;
    }

    public static function addTransaction(int $tenantId, int $companyId, int $cashAccountId, string $type, string $date, float $amount, ?string $description): int
    {
        $id = (int) DB::insert('cash_transactions', [
            'tenant_id' => $tenantId, 'company_id' => $companyId, 'cash_account_id' => $cashAccountId,
            'type' => $type, 'date' => $date, 'amount' => $amount,
            'description' => $description, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $net = in_array($type, self::PLUS, true) ? $amount : -$amount;
        DB::execute('UPDATE cash_accounts SET balance = balance + :amt, updated_at = :u WHERE id = :id', ['amt' => $net, 'u' => now(), 'id' => $cashAccountId]);
        return $id;
    }

    public function transactions(int $cashAccountId): array
    {
        return paginate(
            'SELECT * FROM cash_transactions WHERE cash_account_id = :id ORDER BY date DESC, id DESC',
            ['id' => $cashAccountId],
            50
        );
    }
}

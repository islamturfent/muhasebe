<?php

declare(strict_types=1);

namespace Muh\Services;

use Muh\Core\Auth;
use Muh\Core\DB;
use Muh\Core\Validator;
use Muh\Core\ValidationException;

/**
 * Bank (banka) ledger — Phase 7.
 */
final class BankService
{
    private const PLUS = ['deposit', 'interest', 'transfer'];
    private const MINUS = ['withdrawal', 'fee'];

    public function accounts(?int $companyId = null): array
    {
        $sql = 'SELECT ba.*, c.name AS company_name FROM bank_accounts ba
                 JOIN companies c ON c.id = ba.company_id
                WHERE ba.tenant_id = :t AND ba.deleted_at IS NULL';
        $params = ['t' => Auth::tenantId()];
        if ($companyId) {
            $sql .= ' AND ba.company_id = :c';
            $params['c'] = $companyId;
        }
        $sql .= ' ORDER BY ba.bank_name ASC';
        return DB::select($sql, $params);
    }

    public function account(int $tenantId, int $id): ?array
    {
        return DB::first('SELECT * FROM bank_accounts WHERE id = :id AND tenant_id = :t AND deleted_at IS NULL', ['id' => $id, 't' => $tenantId]);
    }

    public function createAccount(array $data): int
    {
        $tenantId = (int) Auth::tenantId();
        (new Validator())->validateOrFail($data, ['company_id' => 'required', 'bank_name' => 'required|min:2']);
        $companyId = (int) $data['company_id'];
        if (!DB::first('SELECT id FROM companies WHERE id = :id AND tenant_id = :t AND deleted_at IS NULL', ['id' => $companyId, 't' => $tenantId])) {
            throw new ValidationException(['company_id' => __('validation.in')]);
        }
        $id = (int) DB::insert('bank_accounts', [
            'tenant_id' => $tenantId, 'company_id' => $companyId,
            'bank_name' => $data['bank_name'],
            'account_name' => $data['account_name'] ?? null,
            'iban' => $data['iban'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'branch' => $data['branch'] ?? null,
            'currency' => $data['currency'] ?? 'TRY',
            'balance' => 0, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        AuditLogService::record('bank.create', 'bank', 'bank_accounts', (string)$id, null, $data, $companyId, $tenantId);
        return $id;
    }

    public static function addTransaction(int $tenantId, int $companyId, int $bankAccountId, string $type, string $date, float $amount, ?string $description): int
    {
        $id = (int) DB::insert('bank_transactions', [
            'tenant_id' => $tenantId, 'company_id' => $companyId, 'bank_account_id' => $bankAccountId,
            'type' => $type, 'date' => $date, 'amount' => $amount,
            'description' => $description, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $net = in_array($type, self::PLUS, true) ? $amount : -$amount;
        DB::execute('UPDATE bank_accounts SET balance = balance + :amt, updated_at = :u WHERE id = :id', ['amt' => $net, 'u' => now(), 'id' => $bankAccountId]);
        return $id;
    }

    public function transactions(int $bankAccountId): array
    {
        return paginate(
            'SELECT * FROM bank_transactions WHERE bank_account_id = :id ORDER BY date DESC, id DESC',
            ['id' => $bankAccountId],
            50
        );
    }
}

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

        // Optional opening balance -> post an opening transaction + journal entry.
        $opening = (float) ($data['opening_balance'] ?? 0);
        if ($opening > 0) {
            $date = $data['opening_date'] ?: date('Y-m-d');
            self::addTransaction($tenantId, $companyId, $id, 'opening', $date, $opening, __('cash.opening_balance'));
            $periodId = (int) DB::scalar(
                'SELECT id FROM fiscal_periods WHERE company_id = :c AND :d BETWEEN start_date AND end_date ORDER BY start_date DESC LIMIT 1',
                ['c' => $companyId, 'd' => $date]
            ) ?: (int) DB::scalar('SELECT id FROM fiscal_periods WHERE company_id = :c ORDER BY start_date DESC LIMIT 1', ['c' => $companyId]);
            if ($periodId) {
                \Muh\Services\AccountingService::postEntry(
                    $tenantId, $companyId, $periodId, 'opening', $date, __('cash.opening_balance'),
                    [
                        ['account_code' => '100', 'debit' => $opening, 'credit' => 0],
                        ['account_code' => '500', 'debit' => 0, 'credit' => $opening],
                    ],
                    null, 'opening', null
                );
            }
            AuditLogService::record('cash.opening', 'cash', 'cash_accounts', (string)$id, null, ['opening' => $opening], $companyId, $tenantId);
        }
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

    /**
     * Virman: move funds between two cash accounts of the tenant. Both sides
     * are recorded (outbound debit, inbound credit) inside one transaction so
     * the total never drifts.
     */
    public function transfer(int $fromId, int $toId, float $amount, ?string $date = null, ?string $description = null): void
    {
        $tenantId = (int) Auth::tenantId();
        if ($fromId === $toId) {
            throw new ValidationException(['to_id' => __('cash.same_account')]);
        }
        if ($amount <= 0) {
            throw new ValidationException(['amount' => __('validation.min')]);
        }
        $from = DB::first('SELECT id, company_id FROM cash_accounts WHERE id = :id AND tenant_id = :t AND deleted_at IS NULL', ['id' => $fromId, 't' => $tenantId]);
        $to = DB::first('SELECT id, company_id FROM cash_accounts WHERE id = :id AND tenant_id = :t AND deleted_at IS NULL', ['id' => $toId, 't' => $tenantId]);
        if (!$from || !$to) {
            throw new ValidationException(['to_id' => __('validation.in')]);
        }
        $d = $date ?: date('Y-m-d');
        $desc = $description ?: __('cash.virman');

        DB::transaction(function () use ($tenantId, $from, $to, $d, $amount, $desc): void {
            self::addTransaction($tenantId, (int) $from['company_id'], (int) $from['id'], 'transfer', $d, -$amount, $desc);
            self::addTransaction($tenantId, (int) $to['company_id'], (int) $to['id'], 'transfer', $d, $amount, $desc);
            AuditLogService::record('cash.virman', 'cash', 'cash_accounts', (string) $from['id'], null, ['to' => (int) $to['id'], 'amount' => $amount], (int) $to['company_id'], $tenantId);
        });
    }

    public function transactions(int $cashAccountId, ?string $from = null, ?string $to = null, ?string $type = null): array
    {
        $sql = 'SELECT * FROM cash_transactions WHERE cash_account_id = :id';
        $params = ['id' => $cashAccountId];
        if ($from) {
            $sql .= ' AND date >= :from';
            $params['from'] = $from;
        }
        if ($to) {
            $sql .= ' AND date <= :to';
            $params['to'] = $to;
        }
        if ($type) {
            $sql .= ' AND type = :type';
            $params['type'] = $type;
        }
        $sql .= ' ORDER BY date DESC, id DESC';
        return paginate($sql, $params, 50);
    }
}

<?php

declare(strict_types=1);

namespace Muh\Services;

use Muh\Core\Auth;
use Muh\Core\DB;
use Muh\Core\ValidationException;

/**
 * Unified transfer (virman) between cash and/or bank accounts of a tenant.
 * Supports all four combinations: cash->cash, bank->bank, cash->bank,
 * bank->cash. Both sides are recorded inside one transaction so the combined
 * balance never drifts.
 */
final class TransferService
{
    /** @var array{table:string,label:string} */
    private const DEFS = [
        'cash' => ['table' => 'cash_accounts', 'label' => 'Kasa'],
        'bank' => ['table' => 'bank_accounts', 'label' => 'Banka'],
    ];

    public function transfer(string $fromType, int $fromId, string $toType, int $toId, float $amount, ?string $date = null, ?string $description = null): void
    {
        $tenantId = (int) Auth::tenantId();
        if (!isset(self::DEFS[$fromType]) || !isset(self::DEFS[$toType])) {
            throw new ValidationException(['to' => __('validation.in')]);
        }
        if ($fromType === $toType && $fromId === $toId) {
            throw new ValidationException(['to_id' => __('cash.same_account')]);
        }
        if ($amount <= 0) {
            throw new ValidationException(['amount' => __('validation.min')]);
        }
        $from = $this->resolve($fromType, $fromId, $tenantId);
        $to = $this->resolve($toType, $toId, $tenantId);
        if (!$from || !$to) {
            throw new ValidationException(['to_id' => __('validation.in')]);
        }

        $d = $date ?: date('Y-m-d');
        $desc = $description ?: __('cash.virman');

        DB::transaction(function () use ($tenantId, $fromType, $from, $toType, $to, $d, $amount, $desc): void {
            $this->record($fromType, (int) $from['id'], $tenantId, (int) $from['company_id'], $d, -$amount, $desc);
            $this->record($toType, (int) $to['id'], $tenantId, (int) $to['company_id'], $d, $amount, $desc);
            AuditLogService::record('account.transfer', 'account', 'accounts', (string) $from['id'], null, ['from_type' => $fromType, 'to_type' => $toType, 'to' => (int) $to['id'], 'amount' => $amount], (int) $from['company_id'], $tenantId);
        });
    }

    /** List all destinations (cash + bank accounts of the tenant) for a source. */
    public function destinations(string $sourceType, int $sourceId): array
    {
        $tenantId = (int) Auth::tenantId();
        $out = [];
        foreach (self::DEFS as $type => $def) {
            $cols = $type === 'cash' ? 'id, name, code' : 'id, COALESCE(account_name, bank_name) AS name, iban AS code';
            $order = $type === 'cash' ? 'name' : 'bank_name';
            $rows = DB::select(
                'SELECT ' . $cols . ' FROM ' . DB::quoteIdentifier($def['table'])
                    . ' WHERE tenant_id = :t AND deleted_at IS NULL' . ($type === $sourceType ? ' AND id != :src' : '') . ' ORDER BY ' . $order,
                $type === $sourceType ? ['t' => $tenantId, 'src' => $sourceId] : ['t' => $tenantId]
            );
            foreach ($rows as $r) {
                $r['account_type'] = $type;
                $r['label'] = $def['label'];
                $out[] = $r;
            }
        }
        return $out;
    }

    private function resolve(string $type, int $id, int $tenantId): ?array
    {
        return DB::first(
            'SELECT id, company_id FROM ' . DB::quoteIdentifier(self::DEFS[$type]['table'])
                . ' WHERE id = :id AND tenant_id = :t AND deleted_at IS NULL',
            ['id' => $id, 't' => $tenantId]
        );
    }

    private function record(string $type, int $acctId, int $tenantId, int $companyId, string $date, float $amount, string $desc): void
    {
        if ($type === 'cash') {
            CashService::addTransaction($tenantId, $companyId, $acctId, 'transfer', $date, $amount, $desc);
        } else {
            BankService::addTransaction($tenantId, $companyId, $acctId, 'transfer', $date, $amount, $desc);
        }
    }
}

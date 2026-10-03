<?php

declare(strict_types=1);

namespace Muh\Services;

use Muh\Core\DB;
use Muh\Core\ValidationException;
use Muh\Core\Request;

/**
 * Fiscal period lifecycle (Phase 8): closing a period and carrying forward
 * balances to the next period (dönem kapanış & devir).
 *
 * Closing posts a P&L closing voucher in the source period (income/expense
 * closed to 590 Dönem Net Kârı/Zararı) and writes the resulting balance-sheet
 * opening balances onto the target period's chart of accounts, so the target
 * period's mizan/bilanço start with the correct opening figures.
 */
final class PeriodService
{
    /** P&L (retained) account used by the closing voucher. */
    public const PNL_ACCOUNT = '590';

    /**
     * Close a fiscal period and carry its balances into a target (next) period.
     *
     * @throws ValidationException
     */
    public static function close(int $tenantId, int $companyId, int $sourcePeriodId, ?int $targetPeriodId = null): array
    {
        $source = DB::first(
            'SELECT * FROM fiscal_periods WHERE id = :id AND company_id = :c AND deleted_at IS NULL',
            ['id' => $sourcePeriodId, 'c' => $companyId]
        );
        if (!$source) {
            throw new ValidationException(['period' => __('period.not_found')]);
        }
        if ((int) $source['is_closed']) {
            throw new ValidationException(['period' => __('period.already_closed')]);
        }

        // Pick the target (next) period.
        if ($targetPeriodId) {
            $target = DB::first(
                'SELECT * FROM fiscal_periods WHERE id = :id AND company_id = :c AND deleted_at IS NULL',
                ['id' => $targetPeriodId, 'c' => $companyId]
            );
            if (!$target) {
                throw new ValidationException(['period' => __('period.target_not_found')]);
            }
        } else {
            $target = DB::first(
                'SELECT * FROM fiscal_periods WHERE company_id = :c AND start_date > :sd AND deleted_at IS NULL ORDER BY start_date ASC LIMIT 1',
                ['c' => $companyId, 'sd' => $source['start_date']]
            );
        }
        if (!$target) {
            throw new ValidationException(['period' => __('period.no_next')]);
        }
        $targetPeriodId = (int) $target['id'];
        if ($targetPeriodId === (int) $sourcePeriodId) {
            throw new ValidationException(['period' => __('period.same_period')]);
        }

        // ---- Net balances per account at the end of the source period ----
        // trialBalance returns debit/credit = opening + posted entries.
        $rows = AccountingService::trialBalance($companyId, (int) $source['id']);

        $netByCode = [];   // code => net (debit-balance positive)
        $incomeTotal = 0.0; // credit balances (income)
        $expenseTotal = 0.0; // debit balances (expense)
        foreach ($rows as $r) {
            $code = (string) $r['code'];
            $net = (float) $r['debit'] - (float) $r['credit'];
            $netByCode[$code] = $net;
            if ($r['type'] === 'income') {
                $incomeTotal += $net; // income has credit balance => net is negative
            } elseif ($r['type'] === 'expense') {
                $expenseTotal += $net; // expense has debit balance => net positive
            }
        }

        // Income/net convention: income credit balance (net negative), expense debit (net positive).
        $netProfit = -$incomeTotal - $expenseTotal; // income(-) minus expense(+): revenue - expenses

        // ---- Post the closing voucher in the SOURCE period (P&L -> 590) ----
        $closingLines = [];
        foreach ($rows as $r) {
            $net = (float) $r['debit'] - (float) $r['credit'];
            if ($r['type'] === 'income' && abs($net) > 0.009) {
                // Close credit balance: debit income account by its balance.
                $closingLines[] = ['account_code' => (string) $r['code'], 'debit' => abs($net), 'credit' => 0.0];
            } elseif ($r['type'] === 'expense' && abs($net) > 0.009) {
                // Close debit balance: credit expense account by its balance.
                $closingLines[] = ['account_code' => (string) $r['code'], 'debit' => 0.0, 'credit' => abs($net)];
            }
        }
        if (abs($netProfit) > 0.009) {
            if ($netProfit > 0) {
                $closingLines[] = ['account_code' => static::PNL_ACCOUNT, 'debit' => 0.0, 'credit' => $netProfit];
            } else {
                $closingLines[] = ['account_code' => static::PNL_ACCOUNT, 'debit' => abs($netProfit), 'credit' => 0.0];
            }
        }
        if ($closingLines) {
            // Only post if there were income/expense balances (skip a fully flat close).
            AccountingService::postEntry(
                $tenantId, $companyId, (int) $source['id'],
                'closing',
                $source['end_date'],
                __('period.closing_entry', ['period' => $source['name']]),
                $closingLines,
                null, 'period_close', (string) $source['id']
            );
        }

        // ---- Carry final balance-sheet balances to the target period ----
        $carried = [];
        foreach ($netByCode as $code => $net) {
            $code = (string) $code;
            $type = null;
            foreach ($rows as $r) {
                if ((string) $r['code'] === $code) {
                    $type = $r['type'];
                    break;
                }
            }
            // Income/expense close to zero; only carry balance-sheet + P&L(590).
            if (in_array($type, ['income', 'expense'], true)) {
                $net = 0.0;
            }
            if (abs($net) < 0.005) {
                $net = 0.0;
            }
            $carried[] = ['code' => $code, 'net' => $net, 'type' => $type, 'name' => static::accountName($companyId, (int) $source['id'], $code)];
        }

        DB::transaction(function () use ($tenantId, $companyId, $targetPeriodId, $carried, $source) {
            foreach ($carried as $c) {
                static::applyOpening($tenantId, $companyId, $targetPeriodId, $c['code'], $c['net'], $c['name']);
            }
            // Mark source closed & switch current to target.
            DB::execute(
                'UPDATE fiscal_periods SET is_closed = 1, is_current = 0, updated_at = NOW() WHERE id = :id',
                ['id' => (int) $source['id']]
            );
            DB::execute(
                'UPDATE fiscal_periods SET is_current = 1, updated_at = NOW() WHERE id = :id',
                ['id' => $targetPeriodId]
            );
            DB::execute(
                'UPDATE fiscal_periods SET is_current = 0 WHERE company_id = :c AND id != :id AND deleted_at IS NULL',
                ['c' => $companyId, 'id' => $targetPeriodId]
            );
        });

        return ['source' => (int) $source['id'], 'target' => $targetPeriodId];
    }

    /** Upsert an account in the target period with the given opening balance. */
    private static function applyOpening(int $tenantId, int $companyId, int $targetPeriodId, string $code, float $net, ?string $name): void
    {
        if (abs($net) < 0.005) {
            return; // nothing to carry
        }
        $debit = $net > 0 ? $net : 0.0;
        $credit = $net < 0 ? abs($net) : 0.0;

        $existing = DB::first(
            'SELECT id, name, type FROM accounting_accounts WHERE company_id = :c AND fiscal_period_id = :p AND code = :code',
            ['c' => $companyId, 'p' => $targetPeriodId, 'code' => $code]
        );
        if ($existing) {
            DB::execute(
                'UPDATE accounting_accounts SET opening_debit = :d, opening_credit = :c, updated_at = NOW() WHERE id = :id',
                ['d' => $debit, 'c' => $credit, 'id' => (int) $existing['id']]
            );
            return;
        }
        // Create the account in the target period if it does not exist yet.
        DB::insert('accounting_accounts', [
            'tenant_id'        => $tenantId,
            'company_id'       => $companyId,
            'fiscal_period_id' => $targetPeriodId,
            'code'             => $code,
            'name'             => $name ?? __('accounting.title'),
            'type'             => static::guessType($code),
            'group'            => substr($code, 0, 1),
            'is_header'        => 0,
            'currency'         => 'TRY',
            'opening_debit'    => $debit,
            'opening_credit'   => $credit,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    private static function accountName(int $companyId, int $periodId, string $code): ?string
    {
        $r = DB::first(
            'SELECT name FROM accounting_accounts WHERE company_id = :c AND code = :code ORDER BY fiscal_period_id LIMIT 1',
            ['c' => $companyId, 'code' => $code]
        );
        return $r['name'] ?? null;
    }

    private static function guessType(string $code): string
    {
        $h = (int) substr($code, 0, 1);
        switch ($h) {
            case 1: return 'asset';
            case 2: return 'asset';
            case 3: return 'liability';
            case 4: return 'liability';
            case 5: return 'equity';
            case 6: return 'expense';
            case 7: return 'expense';
            case 8: return 'expense';
            case 9: return 'income';
            default: return 'asset';
        }
    }
}

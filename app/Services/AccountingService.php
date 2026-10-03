<?php

declare(strict_types=1);

namespace Muh\Services;

use Muh\Core\DB;
use Muh\Core\Validator;
use Muh\Core\ValidationException;
use Muh\Core\Request;

/**
 * Double-entry accounting engine (Phase 8).
 *
 * Posts journal vouchers (yevmiye/mahsup/tahsil/tediye/açılış/kapanış/devir) and
 * enforces the balancing rule Σ(debit) == Σ(credit) before persisting.
 * Charts of accounts are per (company, fiscal period); each line resolves an
 * account by its code.
 */
final class AccountingService
{
    private const EPSILON = 0.009; // rounding tolerance for balancing

    /**
     * Post an accounting entry (voucher).
     *
     * @param array $lines [ ['account_code'=>string, 'debit'=>float, 'credit'=>float, 'description'=>?string], ... ]
     * @return int entry id
     */
    public static function postEntry(
        int $tenantId,
        int $companyId,
        int $fiscalPeriodId,
        string $voucherType,
        string $date,
        string $description,
        array $lines,
        ?Request $request = null,
        ?string $refType = null,
        ?string $refId = null
    ): int {
        if (empty($lines)) {
            throw new ValidationException(['lines' => __('accounting.no_lines')]);
        }

        // --- Validate & resolve accounts, sum debits/credits ---
        $prepared = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($lines as $line) {
            $code = $line['account_code'] ?? '';
            $debit = (float) ($line['debit'] ?? 0);
            $credit = (float) ($line['credit'] ?? 0);

            if ($code === '' || ($debit <= 0 && $credit <= 0)) {
                continue; // skip empty lines
            }

            $account = DB::first(
                'SELECT id, code, name FROM accounting_accounts
                  WHERE company_id = :c AND fiscal_period_id = :p AND code = :code',
                ['c' => $companyId, 'p' => $fiscalPeriodId, 'code' => $code]
            );
            if (!$account) {
                // Fall back to any account with that code for the company.
                $account = DB::first(
                    'SELECT id, code, name FROM accounting_accounts
                      WHERE company_id = :c AND code = :code',
                    ['c' => $companyId, 'code' => $code]
                );
            }
            if (!$account) {
                throw new ValidationException(['accounts' => __('accounting.account_not_found', ['code' => $code])]);
            }

            $totalDebit += $debit;
            $totalCredit += $credit;

            $prepared[] = [
                'account_id'   => (int) $account['id'],
                'account_code' => $code,
                'account_name' => $account['name'],
                'debit'        => $debit,
                'credit'       => $credit,
                'description'  => $line['description'] ?? null,
            ];
        }

        // --- Enforce double-entry balance ---
        if (abs($totalDebit - $totalCredit) > static::EPSILON) {
            throw new ValidationException(['balance' => __('accounting.not_balanced')]);
        }
        if ($totalDebit <= 0) {
            throw new ValidationException(['lines' => __('accounting.no_lines')]);
        }

        // --- Voucher number: YIL-SEQUENCE ---
        $year = (int) substr($date, 0, 4);
        $seq = ((int) DB::scalar(
            'SELECT COUNT(*)+1 FROM accounting_entries
              WHERE company_id = :c AND fiscal_period_id = :p AND created_at >= :start AND created_at < :end',
            ['c' => $companyId, 'p' => $fiscalPeriodId, 'start' => $year . '-01-01 00:00:00', 'end' => ($year + 1) . '-01-01 00:00:00']
        ));
        $number = $year . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);

        $entryId = (int) DB::transaction(function () use (
            $tenantId, $companyId, $fiscalPeriodId, $voucherType,
            $date, $description, $prepared, $totalDebit, $totalCredit, $number,
            $refType, $refId
        ) {
            $entryId = (int) DB::insert('accounting_entries', [
                'tenant_id'       => $tenantId,
                'company_id'      => $companyId,
                'fiscal_period_id'=> $fiscalPeriodId,
                'created_by'      => \Muh\Core\Auth::id(),
                'voucher_type'    => $voucherType,
                'number'          => $number,
                'date'            => $date,
                'description'     => $description,
                'debit_total'     => $totalDebit,
                'credit_total'    => $totalCredit,
                'status'          => 'posted',
                'reference_type'  => $refType,
                'reference_id'    => $refId,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            $rows = [];
            foreach ($prepared as $l) {
                $rows[] = [
                    'tenant_id'    => $tenantId,
                    'company_id'   => $companyId,
                    'entry_id'     => $entryId,
                    'account_id'   => $l['account_id'],
                    'debit'        => $l['debit'],
                    'credit'       => $l['credit'],
                    'description'  => $l['description'],
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ];
            }
            DB::insertMany('accounting_entry_lines', $rows);

            AuditLogService::record(
                'accounting.entry.post',
                'accounting',
                'accounting_entries',
                (string) $entryId,
                null,
                ['number' => $number, 'debit' => $totalDebit, 'credit' => $totalCredit, 'lines' => $prepared],
                $companyId,
                $tenantId,
                null
            );

            return $entryId;
        });

        return $entryId;
    }

    /** Resolve an account by code for a company (any period). */
    public static function findAccount(int $companyId, string $code): ?array
    {
        return DB::first('SELECT * FROM accounting_accounts WHERE company_id = :c AND code = :code', ['c' => $companyId, 'code' => $code]);
    }

    /** Trial balance (mizan) for a company/period. */
    public static function trialBalance(int $companyId, int $periodId): array
    {
        return DB::select(
            'SELECT a.code, a.name, a.type,
                    COALESCE(b.debit, 0)  + a.opening_debit  AS debit,
                    COALESCE(b.credit, 0) + a.opening_credit AS credit
               FROM accounting_accounts a
               LEFT JOIN (
                    SELECT el.account_id,
                           SUM(el.debit)  AS debit,
                           SUM(el.credit) AS credit
                      FROM accounting_entry_lines el
                      JOIN accounting_entries e ON e.id = el.entry_id
                     WHERE e.company_id = :c1 AND e.fiscal_period_id = :p1 AND e.status = :s1
                     GROUP BY el.account_id
               ) b ON b.account_id = a.id
              WHERE a.company_id = :c2 AND a.fiscal_period_id = :p2
              ORDER BY a.code ASC',
            ['c1' => $companyId, 'p1' => $periodId, 's1' => 'posted', 'c2' => $companyId, 'p2' => $periodId]
        );
    }

    /** Journal (yevmiye) for a company/period. */
    public static function journal(int $companyId, int $periodId): array
    {
        return DB::select(
            'SELECT e.*, GROUP_CONCAT(CONCAT(l.account_code, \'#\', l.debit, \'/\', l.credit) SEPARATOR \'; \') AS lines_summary
               FROM accounting_entries e
               LEFT JOIN (SELECT el.entry_id, a.code AS account_code, el.debit, el.credit
                            FROM accounting_entry_lines el
                            JOIN accounting_accounts a ON a.id = el.account_id) l
                 ON l.entry_id = e.id
              WHERE e.company_id = :c AND e.fiscal_period_id = :p
              GROUP BY e.id ORDER BY e.date ASC, e.id ASC',
            ['c' => $companyId, 'p' => $periodId]
        );
    }

    /** Balance sheet (bilanço): asset/liability/equity account balances. */
    public static function balanceSheet(int $companyId, int $periodId): array
    {
        $rows = static::trialBalance($companyId, $periodId);
        $asset = [];
        $liability = [];
        $equity = [];
        foreach ($rows as $r) {
            if ($r['type'] === 'asset') {
                $asset[] = $r;
            } elseif ($r['type'] === 'liability') {
                $liability[] = $r;
            } elseif ($r['type'] === 'equity') {
                $equity[] = $r;
            }
        }
        return ['asset' => $asset, 'liability' => $liability, 'equity' => $equity];
    }

    /** Income statement (gelir tablosu). */
    public static function incomeStatement(int $companyId, int $periodId): array
    {
        $rows = static::trialBalance($companyId, $periodId);
        $income = [];
        $expense = [];
        foreach ($rows as $r) {
            if ($r['type'] === 'income') {
                $income[] = $r;
            } elseif ($r['type'] === 'expense') {
                $expense[] = $r;
            }
        }
        return ['income' => $income, 'expense' => $expense];
    }
}

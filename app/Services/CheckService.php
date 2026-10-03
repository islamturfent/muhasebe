<?php

declare(strict_types=1);

namespace Muh\Services;

use Muh\Core\Auth;
use Muh\Core\DB;
use Muh\Core\Validator;
use Muh\Core\ValidationException;

/**
 * Checks (çek) & promissory notes (senet) portfolio — Phase 7.
 */
final class CheckService
{
    private const STATUSES = ['in_portfolio', 'banked', 'collected', 'endorsed', 'returned', 'unpaid', 'cancelled'];

    /** @param string $kind 'check'|'note' */
    public function list(?int $companyId = null, string $kind = 'check', ?string $from = null, ?string $to = null, ?string $status = null): array
    {
        $table = $kind === 'check' ? 'checks' : 'promissory_notes';
        $sql = 'SELECT t.*, c.name AS company_name, ca.name AS account_name
                 FROM ' . DB::quoteIdentifier($table) . ' t
                 JOIN companies c ON c.id = t.company_id
                 LEFT JOIN current_accounts ca ON ca.id = t.current_account_id
                WHERE t.tenant_id = :ten AND t.deleted_at IS NULL';
        $params = ['ten' => Auth::tenantId()];
        if ($companyId) {
            $sql .= ' AND t.company_id = :c';
            $params['c'] = $companyId;
        }
        if ($from) {
            $sql .= ' AND t.due_date >= :from';
            $params['from'] = $from;
        }
        if ($to) {
            $sql .= ' AND t.due_date <= :to';
            $params['to'] = $to;
        }
        if ($status) {
            $sql .= ' AND t.status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY t.due_date ASC';
        return paginate($sql, $params, 25);
    }

    /** @param string $kind 'check'|'note' */
    public function find(int $id, string $kind = 'check'): ?array
    {
        $table = $kind === 'check' ? 'checks' : 'promissory_notes';
        return DB::first(
            'SELECT t.*, c.name AS company_name, ca.name AS account_name, ca.code AS account_code
               FROM ' . DB::quoteIdentifier($table) . ' t
               LEFT JOIN companies c ON c.id = t.company_id
               LEFT JOIN current_accounts ca ON ca.id = t.current_account_id
              WHERE t.id = :id AND t.tenant_id = :ten AND t.deleted_at IS NULL',
            ['id' => $id, 'ten' => (int) Auth::tenantId()]
        );
    }

    /**
     * Due-tracking (vade takibi) overview for the check/note portfolio:
     * portfolio totals, counts, overdue amount and maturing (within 7/30 days).
     * @param string $kind 'check'|'note'
     * @return array{total:float,count:int,portfolio:float,portfolio_count:int,overdue:float,overdue_count:int,due7:float,due30:float,incoming:float,incoming_count:int}
     */
    public function overview(?int $companyId, string $kind = 'check'): array
    {
        $table = $kind === 'check' ? 'checks' : 'promissory_notes';
        $tenantId = (int) Auth::tenantId();
        $sql = 'SELECT * FROM ' . DB::quoteIdentifier($table) . ' WHERE tenant_id = :t AND deleted_at IS NULL';
        $params = ['t' => $tenantId];
        if ($companyId) {
            $sql .= ' AND company_id = :c';
            $params['c'] = $companyId;
        }
        $rows = DB::select($sql, $params);
        $today = strtotime(date('Y-m-d'));
        $o = ['total'=>0.0,'count'=>0,'portfolio'=>0.0,'portfolio_count'=>0,'overdue'=>0.0,'overdue_count'=>0,'due7'=>0.0,'due30'=>0.0,'incoming'=>0.0,'incoming_count'=>0];
        foreach ($rows as $r) {
            $amt = (float) $r['amount'];
            $o['total'] += $amt;
            $o['count']++;
            if ($r['direction'] === 'incoming') {
                $o['incoming'] += $amt;
                $o['incoming_count']++;
            }
            if ($r['status'] === 'in_portfolio') {
                $o['portfolio'] += $amt;
                $o['portfolio_count']++;
            } elseif ($r['status'] === 'cancelled') {
                $o['total'] -= $amt;
                $o['count']--;
                continue;
            }
            if ($r['status'] === 'in_portfolio' && !empty($r['due_date'])) {
                $due = strtotime($r['due_date']);
                $days = (int) floor(($due - $today) / 86400);
                if ($days < 0) {
                    $o['overdue'] += $amt;
                    $o['overdue_count']++;
                } elseif ($days <= 7) {
                    $o['due7'] += $amt;
                }
                if ($days <= 30) {
                    $o['due30'] += $amt;
                }
            }
        }
        return $o;
    }

    public function create(array $data, string $kind = 'check'): int
    {
        $tenantId = (int) Auth::tenantId();
        $table = $kind === 'check' ? 'checks' : 'promissory_notes';
        $v = new Validator();
        $v->validateOrFail($data, [
            'company_id'         => 'required',
            'due_date'           => 'required|date',
            'amount'             => 'required|decimal',
            'direction'          => 'required|in:incoming,outgoing',
        ]);
        $companyId = (int) $data['company_id'];
        if (!DB::first('SELECT id FROM companies WHERE id = :id AND tenant_id = :t AND deleted_at IS NULL', ['id' => $companyId, 't' => $tenantId])) {
            throw new ValidationException(['company_id' => __('validation.in')]);
        }

        $noCol = $kind === 'check' ? 'check_no' : 'note_no';
        $id = (int) DB::insert($table, [
            'tenant_id'          => $tenantId,
            'company_id'         => $companyId,
            'current_account_id' => $data['current_account_id'] ? ((int) $data['current_account_id']) : null,
            'direction'          => $data['direction'],
            'status'             => 'in_portfolio',
            $noCol               => $data['number'] ?? null,
            'issue_date'         => $data['issue_date'] ?? null,
            'due_date'           => $data['due_date'],
            'amount'             => (float) $data['amount'],
            'bank'               => $data['bank'] ?? null,
            'notes'              => $data['notes_text'] ?? null,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
        AuditLogService::record($kind . '.create', 'check', $table, (string)$id, null, $data, $companyId, $tenantId);
        return $id;
    }

    public function updateStatus(string $kind, int $id, string $status): bool
    {
        $tenantId = (int) Auth::tenantId();
        if (!in_array($status, self::STATUSES, true)) {
            throw new ValidationException(['status' => __('validation.in')]);
        }
        $table = $kind === 'check' ? 'checks' : 'promissory_notes';
        $record = DB::first(
            'SELECT * FROM ' . DB::quoteIdentifier($table) . ' WHERE id = :id AND tenant_id = :t AND deleted_at IS NULL',
            ['id' => $id, 't' => $tenantId]
        );
        if (!$record) {
            throw new ValidationException(['id' => __('validation.in')]);
        }

        DB::update($table, ['status' => $status], 'id = :id AND tenant_id = :t', ['id' => $id, 't' => $tenantId]);

        // Financial effect: when a check/note is collected, post the cari + kasa
        // + journal entry once (guarded by posted_at).
        $alreadyPosted = !empty($record['posted_at']);
        if ($status === 'collected' && !$alreadyPosted) {
            DB::transaction(function () use ($tenantId, $record, $kind, $id, $table): void {
                $this->postCollection($tenantId, $record, $kind, $id, $table);
            });
        }

        AuditLogService::record($kind . '.status', 'check', $table, (string)$id, null, ['status' => $status], (int) ($record['company_id'] ?? 0), $tenantId);
        return true;
    }

    /**
     * Post the financial effect of a collected check/note: current-account
     * movement, a cash (kasa) transaction and a balanced journal entry.
     * Cash is routed to the company's default cash account (account code 100
     * in the journal); customer/supplier cleared via 120/320 respectively.
     */
    private function postCollection(int $tenantId, array $record, string $kind, int $id, string $table): void
    {
        $companyId = (int) $record['company_id'];
        $amount = (float) $record['amount'];
        if ($amount <= 0) {
            return;
        }
        $date = $record['due_date'] ?: date('Y-m-d');
        $no = $record[$kind === 'check' ? 'check_no' : 'note_no'] ?: ('#' . $id);
        $refType = $kind === 'check' ? 'check' : 'note';
        $refId = (string) $id;
        $desc = __('check.fin_post_description', ['no' => $no]);
        $currentAccountId = (int) ($record['current_account_id'] ?? 0);

        $cash = DB::first(
            'SELECT id FROM cash_accounts WHERE company_id = :c AND tenant_id = :t AND deleted_at IS NULL ORDER BY id ASC LIMIT 1',
            ['c' => $companyId, 't' => $tenantId]
        );

        $outgoing = $record['direction'] === 'outgoing';

        if ($outgoing) {
            // We pay the supplier: clear payable, cash decreases.
            if ($currentAccountId) {
                \Muh\Services\CurrentAccountService::addMovement($tenantId, $companyId, $currentAccountId, 'payment', $date, $amount, $desc, $refType, $refId);
            }
            if ($cash) {
                \Muh\Services\CashService::addTransaction($tenantId, $companyId, (int) $cash['id'], 'payment', $date, $amount, $desc);
            }
            $lines = [
                ['account_code' => '320', 'debit' => $amount, 'credit' => 0],
                ['account_code' => '100', 'debit' => 0, 'credit' => $amount],
            ];
        } else {
            // Customer pays us: clear receivable, cash increases.
            if ($currentAccountId) {
                \Muh\Services\CurrentAccountService::addMovement($tenantId, $companyId, $currentAccountId, 'collection', $date, $amount, $desc, $refType, $refId);
            }
            if ($cash) {
                \Muh\Services\CashService::addTransaction($tenantId, $companyId, (int) $cash['id'], 'collection', $date, $amount, $desc);
            }
            $lines = [
                ['account_code' => '100', 'debit' => $amount, 'credit' => 0],
                ['account_code' => '120', 'debit' => 0, 'credit' => $amount],
            ];
        }

        $periodId = (int) DB::scalar(
            'SELECT id FROM fiscal_periods WHERE company_id = :c AND :d BETWEEN start_date AND end_date ORDER BY start_date DESC LIMIT 1',
            ['c' => $companyId, 'd' => $date]
        ) ?: (int) DB::scalar('SELECT id FROM fiscal_periods WHERE company_id = :c ORDER BY start_date DESC LIMIT 1', ['c' => $companyId]);

        if ($periodId) {
            \Muh\Services\AccountingService::postEntry($tenantId, $companyId, $periodId, 'journal', $date, $desc, $lines, null, $refType, $refId);
        }

        DB::update($table, ['posted_at' => now()], 'id = :id', ['id' => $id]);
    }
}

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
        $where = 'id = :id AND tenant_id = :t';
        DB::update($table, ['status' => $status], $where, ['id' => $id, 't' => $tenantId]);
        AuditLogService::record($kind . '.status', 'check', $table, (string)$id, null, ['status' => $status], null, $tenantId);
        return true;
    }
}

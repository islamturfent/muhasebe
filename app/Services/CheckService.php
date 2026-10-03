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
    public function list(?int $companyId = null, string $kind = 'check'): array
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
        $sql .= ' ORDER BY t.due_date ASC';
        return DB::select($sql, $params);
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

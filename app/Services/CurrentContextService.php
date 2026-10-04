<?php

declare(strict_types=1);

namespace Muh\Services;

use Muh\Core\Auth;
use Muh\Core\DB;

/**
 * Service for switching the active company (workspace) within the session.
 */
final class CurrentContextService
{
    public static function switchCompany(int $companyId): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }
        // Verify the company belongs to the user's tenant before switching.
        $company = DB::first(
            'SELECT id FROM companies WHERE id = :id AND tenant_id = :t AND deleted_at IS NULL',
            ['id' => $companyId, 't' => $user['tenant_id']]
        );
        if (!$company) {
            return false;
        }
        // Non-owner users may only switch into companies assigned to them.
        if (empty($user['is_owner']) && empty($user['is_system_admin'])) {
            $granted = DB::first(
                'SELECT id FROM user_company WHERE user_id = :u AND company_id = :c',
                ['u' => (int) $user['id'], 'c' => $companyId]
            );
            if (!$granted) {
                return false;
            }
        }
        SessionContext::setCompany($companyId);
        return true;
    }

    /**
     * Whether the active user may access the given company (owner/super-admin:
     * any tenant company; others: only companies assigned via user_company).
     */
    public static function canAccessCompany(int $companyId): bool
    {
        $user = Auth::user();
        if (!$user || !$user['tenant_id']) {
            return false;
        }
        $company = DB::first(
            'SELECT id FROM companies WHERE id = :id AND tenant_id = :t AND deleted_at IS NULL',
            ['id' => $companyId, 't' => (int) $user['tenant_id']]
        );
        if (!$company) {
            return false;
        }
        if (!empty($user['is_owner']) || !empty($user['is_system_admin'])) {
            return true;
        }
        return (bool) DB::first(
            'SELECT id FROM user_company WHERE user_id = :u AND company_id = :c',
            ['u' => (int) $user['id'], 'c' => $companyId]
        );
    }

    /** Throw a 403 if the active user may not access the company. */
    public static function guardCompany(int $companyId): void
    {
        if (!self::canAccessCompany($companyId)) {
            throw new \Muh\Core\ForbiddenException();
        }
    }

    /**
     * Object/record-level guard: if a record (owned by a company of the active
     * tenant) exists and its company is not accessible to the user, throw 403.
     * Returns the record's company_id (or 0/null) so callers can 404 otherwise.
     *
     * @return int|null company_id if the record exists, else null
     */
    public static function guardRecord(int $tenantId, string $table, int $id, string $companyColumn = 'company_id'): ?int
    {
        $col = DB::quoteIdentifier($companyColumn);
        $row = DB::first(
            'SELECT ' . $col . ' AS cid FROM ' . $table . ' WHERE id = :id AND tenant_id = :t',
            ['id' => $id, 't' => $tenantId]
        );
        if (!$row) {
            return null; // not found → caller decides (404/redirect)
        }
        $cid = (int) ($row['cid'] ?? 0);
        if ($cid > 0) {
            self::guardCompany($cid);
        }
        return $cid;
    }

    /** Companies visible to the active user (all for owner/admin, else assigned). */
    public static function companiesForUser(): array
    {
        $user = Auth::user();
        if (!$user) {
            return [];
        }
        if (!empty($user['is_owner']) || !empty($user['is_system_admin'])) {
            return DB::select(
                'SELECT * FROM companies WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY name',
                ['t' => (int) $user['tenant_id']]
            );
        }
        return DB::select(
            'SELECT c.* FROM companies c
              JOIN user_company uc ON uc.company_id = c.id
             WHERE uc.user_id = :u AND c.deleted_at IS NULL ORDER BY c.name',
            ['u' => (int) $user['id']]
        );
    }
}

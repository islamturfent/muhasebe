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

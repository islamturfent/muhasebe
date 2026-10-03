<?php

declare(strict_types=1);

namespace Muh\Models;

use Muh\Core\DB;
use Muh\Core\Auth;

/**
 * Trait that automatically constrains tenant-scoped model queries to the
 * current tenant so cross-tenant data leakage is structurally prevented.
 */
trait TenantScope
{
    /**
     * Apply tenant filtering to a SELECT statement builder.
     * Returns [$sqlConditions, $params].
     */
    protected function tenantScope(array $conditions = []): array
    {
        $tenantId = Auth::tenantId();
        $params = [];
        $where = [];

        if ($tenantId) {
            $where[] = DB::quoteIdentifier('tenant_id') . ' = :tenant_id';
            $params['tenant_id'] = $tenantId;
        }

        foreach ($conditions as $col => $value) {
            $where[] = DB::quoteIdentifier($col) . ' = :c_' . $col;
            $params['c_' . $col] = $value;
        }

        return [$where ? implode(' AND ', $where) : '1=1', $params];
    }
}

<?php

declare(strict_types=1);

namespace Muh\Services;

use Muh\Core\DB;
use Muh\Core\Auth;
use Muh\Core\Request;

/**
 * Central audit logging. Every critical operation records who, what, when,
 * where, and the before/after value (for traceability & compliance).
 */
final class AuditLogService
{
    public static function record(
        string $action,
        ?string $module = null,
        ?string $entityType = null,
        ?string $entityId = null,
        array|string|null $old = null,
        array|string|null $new = null,
        ?int $companyId = null,
        ?int $tenantId = null,
        ?int $userId = null,
        ?Request $request = null
    ): void {
        $tenantId = $tenantId ?? Auth::tenantId();
        $userId = $userId ?? Auth::id();
        $request = $request ?? new Request();

        $oldVal = is_array($old) ? json_encode($old, JSON_UNESCAPED_UNICODE) : (string) $old;
        $newVal = is_array($new) ? json_encode($new, JSON_UNESCAPED_UNICODE) : (string) $new;

        // Keep the log compact but complete.
        if (strlen((string) $oldVal) > 6000) {
            $oldVal = substr((string) $oldVal, 0, 6000);
        }
        if (strlen((string) $newVal) > 6000) {
            $newVal = substr((string) $newVal, 0, 6000);
        }

        DB::insert('audit_logs', [
            'tenant_id'   => $tenantId,
            'user_id'     => $userId,
            'company_id'  => $companyId,
            'action'      => $action,
            'module'      => $module,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'old_value'   => $oldVal ?: null,
            'new_value'   => $newVal ?: null,
            'ip'          => $request->ip(),
            'user_agent'  => substr($request->userAgent(), 0, 500),
            'created_at'  => now(),
        ]);
    }
}

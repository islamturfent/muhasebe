<?php

declare(strict_types=1);

use Muh\Core\DB;

/**
 * Performance indexes on hot query columns (Phase 14). Idempotent guard per
 * index so it can be run repeatedly. MySQL path; PostgreSQL is a no-op here.
 */
return new class {
    public function up(): void
    {
        $add = function (string $table, string $index, string $cols) {
            $exists = DB::first(
                "SELECT COUNT(*) AS c FROM information_schema.STATISTICS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND INDEX_NAME = :i",
                ['t' => $table, 'i' => $index]
            );
            if (($exists['c'] ?? 0) > 0) {
                return;
            }
            DB::execute('ALTER TABLE ' . DB::quoteIdentifier($table) . ' ADD INDEX ' . $index . ' (' . $cols . ')');
        };

        // Notifications: unread count for the bell + listing.
        $add('notifications', 'idx_notif_tenant_read', 'tenant_id, is_read');
        // Accounting entries: pending-approval counts + journal listing.
        $add('accounting_entries', 'idx_entries_tenant_approval', 'tenant_id, approval_status');
        // Invoices: e-Fatura polling + per company/period listing.
        $add('invoices', 'idx_inv_tenant_efatura', 'tenant_id, efatura_status');
        $add('invoices', 'idx_inv_company_period', 'company_id, fiscal_period_id');
        // Current accounts: filter by type.
        $add('current_accounts', 'idx_cur_tenant_type', 'tenant_id, `type`');
        // Products: inventory stock reports.
        $add('products', 'idx_prod_tenant_type', 'tenant_id, `type`');
        // Settings: tenant-scoped reads (incl. efatura/mail/platform).
        $add('settings', 'idx_settings_tenant_group', 'tenant_id, `group`');
        // Audit log: per-tenant chronological queries.
        $add('audit_logs', 'idx_audit_tenant_date', 'tenant_id, created_at');
    }

    public function down(): void
    {
    }
};

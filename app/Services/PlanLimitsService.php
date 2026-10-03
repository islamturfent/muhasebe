<?php

declare(strict_types=1);

namespace Muh\Services;

use Muh\Core\Auth;
use Muh\Core\DB;

/**
 * Plan-based usage limiting (Phase 10). Limits come from plans.features (JSON),
 * never hard-coded, so quotas are data-driven.
 */
final class PlanLimitsService
{
    private const RESOURCES = ['companies', 'users', 'warehouses', 'invoices'];

    public static function currentPlan(): ?array
    {
        $tenantId = Auth::tenantId();
        if (!$tenantId) {
            return null;
        }
        $sub = DB::first(
            'SELECT * FROM subscriptions WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY id DESC',
            ['t' => $tenantId]
        );
        if (!$sub || !$sub['plan_id']) {
            return null;
        }
        $plan = DB::first('SELECT * FROM plans WHERE id = :id', ['id' => $sub['plan_id']]);
        if (!$plan) {
            return null;
        }
        $plan['subscription'] = $sub;
        $plan['_features'] = json_decode($plan['features'] ?? '{}', true) ?: [];
        return $plan;
    }

    public static function features(): array
    {
        $plan = self::currentPlan();
        return $plan ? ($plan['_features'] ?? []) : [];
    }

    public static function usage(): array
    {
        $tenantId = Auth::tenantId();
        $usage = [
            'companies'  => (int) DB::scalar('SELECT COUNT(*) FROM companies WHERE tenant_id = :t AND deleted_at IS NULL', ['t' => $tenantId]),
            'users'      => (int) DB::scalar('SELECT COUNT(*) FROM users WHERE tenant_id = :t AND deleted_at IS NULL', ['t' => $tenantId]),
            'warehouses' => (int) DB::scalar('SELECT COUNT(*) FROM warehouses WHERE tenant_id = :t AND deleted_at IS NULL', ['t' => $tenantId]),
            'invoices'   => (int) DB::scalar('SELECT COUNT(*) FROM invoices WHERE tenant_id = :t AND deleted_at IS NULL', ['t' => $tenantId]),
            'documents_mb' => (int) (DB::scalar("SELECT COALESCE(SUM(size),0)/1024/1024 FROM documents WHERE tenant_id = :t AND deleted_at IS NULL", ['t' => $tenantId]) ?? 0),
        ];
        return $usage;
    }

    /** True when the given resource is already at its plan limit. */
    public static function atLimit(string $resource): bool
    {
        $features = self::features();
        $limit = (int) ($features[$resource] ?? PHP_INT_MAX);
        $current = self::usage()[$resource] ?? 0;
        return $current >= $limit;
    }

    /** Returns remaining allowance, or null if unlimited. */
    public static function remaining(string $resource): ?int
    {
        $features = self::features();
        $limit = (int) ($features[$resource] ?? PHP_INT_MAX);
        if ($limit >= PHP_INT_MAX / 2) {
            return null;
        }
        $current = self::usage()[$resource] ?? 0;
        return max(0, $limit - $current);
    }

    public static function limitOf(string $resource): int
    {
        $features = self::features();
        return (int) ($features[$resource] ?? 0);
    }

    /** Throw a friendly, localized error if the resource limit is reached. */
    public static function assertCanCreate(string $resource): void
    {
        if (self::atLimit($resource)) {
            $limit = self::limitOf($resource);
            throw new \Muh\Core\ValidationException([
                'limit' => __('subscription.limit_reached', ['resource' => __('subscription.resource_' . $resource), 'limit' => $limit]),
            ]);
        }
    }
}

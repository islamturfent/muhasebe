<?php

declare(strict_types=1);

namespace Muh\Models;

use Muh\Core\DB;
use Muh\Core\Model;

/**
 * Tenant = an accounting office (the top level of the data hierarchy).
 * Every tenant owns its own rows; isolation is enforced in the service layer
 * and the TenantScope trait.
 */
final class Tenant extends Model
{
    protected string $table = 'tenants';

    public function companies(): array
    {
        return DB::select(
            'SELECT * FROM companies WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY id DESC',
            ['t' => $this->getId()]
        );
    }

    public function userCount(): int
    {
        return (int) DB::scalar(
            'SELECT COUNT(*) FROM users WHERE tenant_id = :t AND deleted_at IS NULL',
            ['t' => $this->getId()]
        );
    }

    /** Current (latest) subscription for this tenant, or null. */
    public function subscription(): ?array
    {
        return DB::first(
            'SELECT * FROM subscriptions WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY id DESC',
            ['t' => $this->getId()]
        );
    }
}

<?php

declare(strict_types=1);

namespace Muh\Models;

use Muh\Core\DB;
use Muh\Core\Model;

/**
 * User model. Users belong to a tenant (accounting office) unless they are
 * system admins.
 */
final class User extends Model
{
    protected string $table = 'users';
    protected bool $softDeletes = true;

    public static function findByEmail(string $email): ?array
    {
        return DB::first(
            'SELECT * FROM users WHERE email = :email AND deleted_at IS NULL',
            ['email' => strtolower(trim($email))]
        );
    }

    public static function countForTenant(int $tenantId): int
    {
        return (int) DB::scalar(
            'SELECT COUNT(*) FROM users WHERE tenant_id = :t AND deleted_at IS NULL',
            ['t' => $tenantId]
        );
    }

    /** Assign a role to a user (idempotent via unique constraint). */
    public static function assignRole(int $userId, int $roleId): void
    {
        DB::execute(
            'INSERT IGNORE INTO user_role (user_id, role_id) VALUES (:u, :r)',
            ['u' => $userId, 'r' => $roleId]
        );
    }

    public static function rolesOf(int $userId): array
    {
        return DB::select(
            'SELECT r.* FROM roles r
              JOIN user_role ur ON ur.role_id = r.id
             WHERE ur.user_id = :u',
            ['u' => $userId]
        );
    }
}

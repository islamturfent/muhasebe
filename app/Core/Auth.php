<?php

declare(strict_types=1);

namespace Muh\Core;

/**
 * Authentication service backed by the session and the users table.
 */
final class Auth
{
    private const SESSION_USER = 'auth_user';
    private const REMEMBER_COOKIE = 'muh_remember';
    private const REMEMBER_DAYS = 30;

    public static function attempt(string $email, string $password): ?array
    {
        $user = DB::first(
            'SELECT * FROM users WHERE email = :email AND deleted_at IS NULL',
            ['email' => strtolower(trim($email))]
        );

        if (!$user || !Hash::check($password, $user['password'] ?? '')) {
            return null;
        }

        // Password rehash maintenance.
        if (Hash::needsRehash($user['password'])) {
            DB::update('users', ['password' => Hash::make($password)], 'id = :id', ['id' => $user['id']]);
        }

        self::login($user);
        return $user;
    }

    public static function loginById(int $userId): ?array
    {
        $user = DB::first('SELECT * FROM users WHERE id = :id AND deleted_at IS NULL', ['id' => $userId]);
        if (!$user) {
            return null;
        }
        self::login($user);
        return $user;
    }

    /**
     * Establish the authenticated session for a user row.
     */
    public static function login(array $user): void
    {
        Session::regenerateForLogin((int) $user['id']);

        // Load the user's roles to derive permission context.
        $user['_permissions'] = self::loadPermissions((int) $user['id']);
        Session::set(self::SESSION_USER, $user);
    }

    public static function check(): bool
    {
        return Session::has(self::SESSION_USER);
    }

    public static function user(): ?array
    {
        return Session::get(self::SESSION_USER);
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user ? (int) $user['id'] : null;
    }

    public static function tenantId(): ?int
    {
        $user = self::user();
        return $user && !empty($user['tenant_id']) ? (int) $user['tenant_id'] : null;
    }

    public static function logout(): void
    {
        Session::forget(self::SESSION_USER);
        Session::forget('active_company_id');
        Session::forget('active_fiscal_period_id');
    }

    /**
     * Persist a long-lived 'remember me' login: store a hashed token in the
     * user row and set a secure HTTP-only cookie (30 days by default).
     */
    public static function setRememberMe(int $userId): void
    {
        $token = bin2hex(random_bytes(32));
        DB::update('users', ['remember_token' => hash('sha256', $token)], 'id = :id', ['id' => $userId]);
        self::writeRememberCookie((int) $userId . ':' . $token, self::REMEMBER_DAYS * 86400);
    }

    /** Clear the stored token and expire the cookie (used for logout/invalid). */
    public static function clearRememberMe(?int $userId = null): void
    {
        if ($userId) {
            DB::update('users', ['remember_token' => null], 'id = :id', ['id' => $userId]);
        }
        if (isset($_COOKIE[self::REMEMBER_COOKIE])) {
            self::writeRememberCookie('', -42000);
        }
    }

    /**
     * Restore a session from the remember-me cookie (called at boot when no
     * session user is present). Rotates the token after each restore.
     */
    public static function attemptRememberMe(): void
    {
        if (self::check()) {
            return;
        }
        $raw = $_COOKIE[self::REMEMBER_COOKIE] ?? null;
        if (!is_string($raw) || strpos($raw, ':') === false) {
            return;
        }
        [$uid, $token] = explode(':', $raw, 2);
        $uid = (int) $uid;
        if ($uid <= 0 || !$token) {
            return;
        }
        $user = DB::first('SELECT * FROM users WHERE id = :id AND deleted_at IS NULL', ['id' => $uid]);
        if (!$user) {
            return;
        }
        $expected = $user['remember_token'] ?? null;
        if ($expected === null || !hash_equals((string) $expected, hash('sha256', $token))) {
            self::clearRememberMe($uid);
            return;
        }
        self::login($user);
        self::setRememberMe($uid); // rotate token
        if (!empty($user['locale'])) {
            Session::set('locale', $user['locale']);
        }
    }

    private static function writeRememberCookie(string $value, int $maxAge): void
    {
        setcookie(self::REMEMBER_COOKIE, $value, [
            'expires' => time() + $maxAge,
            'path'    => '/',
            'secure'  => !empty(Config::get('app.session.secure', false)),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Load the set of permission keys granted to a user through their roles.
     * Returns an associative array of key => true for O(1) lookups.
     */
    public static function loadPermissions(int $userId): array
    {
        $rows = DB::select(
            'SELECT p.key AS perm
               FROM permissions p
               JOIN role_permission rp ON rp.permission_id = p.id
               JOIN user_role ur ON ur.role_id = rp.role_id
              WHERE ur.user_id = :user_id',
            ['user_id' => $userId]
        );
        $permissions = [];
        foreach ($rows as $row) {
            $permissions[$row['perm']] = true;
        }
        return $permissions;
    }

    public static function can(string $permission): bool
    {
        $user = self::user();
        if (!$user) {
            return false;
        }
        // Office owner bypass (full access).
        if (!empty($user['is_owner'])) {
            return true;
        }
        // Global admin superuser.
        if (!empty($user['is_system_admin'])) {
            return true;
        }
        return !empty($user['_permissions'][$permission]);
    }

    public static function canAny(array $permissions): bool
    {
        foreach ($permissions as $p) {
            if (self::can($p)) {
                return true;
            }
        }
        return false;
    }

    public static function requireCan(string $permission): void
    {
        if (!self::can($permission)) {
            throw new ForbiddenException();
        }
    }
}

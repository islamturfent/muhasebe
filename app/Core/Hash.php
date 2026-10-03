<?php

declare(strict_types=1);

namespace Muh\Core;

/**
 * Password hashing & verification using PHP's native bcrypt/argon2.
 */
final class Hash
{
    public static function make(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function check(string $password, string $hash): bool
    {
        if ($hash === '') {
            return false;
        }
        return password_verify($password, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /** Constant-time string comparison (for CSRF tokens, API keys). */
    public static function constantEquals(string $a, string $b): bool
    {
        return hash_equals($a, $b);
    }
}

<?php

declare(strict_types=1);

namespace Muh\Services;

use Muh\Core\DB;

/**
 * One-time auth tokens for password reset and e-mail verification.
 * Tokens are stored hashed in `password_resets` (type: reset|verify).
 */
final class AuthTokenService
{
    public static function create(string $email, string $type = 'reset', int $minutes = 60): string
    {
        $email = strtolower(trim($email));
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + $minutes * 60);
        // Invalidate any previous tokens for this email+type.
        DB::execute("DELETE FROM password_resets WHERE email = :e AND type = :t", ['e' => $email, 't' => $type]);
        DB::insert('password_resets', [
            'email' => $email, 'token' => hash('sha256', $token), 'type' => $type,
            'expires_at' => $expires, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return $token;
    }

    public static function validate(string $email, string $token, string $type = 'reset'): bool
    {
        $email = strtolower(trim($email));
        $row = DB::first(
            'SELECT token, expires_at FROM password_resets WHERE email = :e AND type = :t',
            ['e' => $email, 't' => $type]
        );
        if (!$row) {
            return false;
        }
        if (!hash_equals((string) $row['token'], hash('sha256', $token))) {
            return false;
        }
        if ($row['expires_at'] !== null && strtotime((string) $row['expires_at']) < time()) {
            return false;
        }
        return true;
    }

    /** Validate and consume (invalidate) a token in one step. */
    public static function consume(string $email, string $token, string $type = 'reset'): bool
    {
        if (!self::validate($email, $token, $type)) {
            return false;
        }
        DB::execute('DELETE FROM password_resets WHERE email = :e AND type = :t', ['e' => strtolower(trim($email)), 't' => $type]);
        return true;
    }
}

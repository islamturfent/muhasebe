<?php

declare(strict_types=1);

namespace Muh\Core;

/**
 * Session manager backed by PHP's native session but with a small API.
 * Stores the authenticated user and selected locale/tenant context.
 */
final class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        $config = Config::get('app.session', []);
        session_name($config['name'] ?? 'muh_session');
        session_set_cookie_params([
            'lifetime' => ($config['lifetime'] ?? 120) * 60,
            'path'     => '/',
            'secure'   => $config['secure'] ?? false,
            'httponly' => $config['httponly'] ?? true,
            'samesite' => $config['samesite'] ?? 'Lax',
        ]);
        session_start();
        self::$started = true;

        // Prevent session fixation: rotate id on privilege change.
        if (!empty($_SESSION['_fresh'])) {
            session_regenerate_id(true);
            unset($_SESSION['_fresh']);
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function put(string $key, mixed $value): void
    {
        self::set($key, $value);
    }

    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    public static function flash(string $key, mixed $value): void
    {
        self::set('_flash.' . $key, $value);
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        self::start();
        $value = $_SESSION['_flash.' . $key] ?? $default;
        unset($_SESSION['_flash.' . $key]);
        return $value;
    }

    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        self::$started = false;
    }

    public static function id(): string
    {
        self::start();
        return session_id();
    }

    public static function regenerateForLogin(int $userId): void
    {
        self::start();
        session_regenerate_id(true);
    }
}

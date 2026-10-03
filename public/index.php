<?php

declare(strict_types=1);

/**
 * Web front controller.
 *
 * All requests are routed through this entry point.
 * (For Apache: a .htaccess rewrites to /index.php; for dev use the built-in
 * server: php -S localhost:8000 -t public public/index.php)
 */

use Muh\Core\Router;
use Muh\Core\Request;

require dirname(__DIR__) . '/app/bootstrap.php';

$basePath = dirname(__DIR__);
$router = new Router();

// Load route registrations.
foreach (glob($basePath . '/routes/*.php') as $routeFile) {
    $register = require $routeFile;
    $register($router);
}

$request = new Request();

// Production hardening: security headers + maintenance mode / debug off.
$debug = config('app.debug', false);

$isProduction = config('app.env', 'local') === 'production';
$isHttps = config('app.https', false);

// Enforce HTTPS when running in production behind a TLS terminator.
if ($isProduction && $isHttps) {
    $proto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ($_SERVER['HTTPS'] ?? '');
    if (strtolower((string) $proto) !== 'https') {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri  = $_SERVER['REQUEST_URI'] ?? '/';
        header('Location: https://' . $host . $uri, true, 301);
        exit;
    }
    // HSTS — only sent over HTTPS.
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-XSS-Protection: 1; mode=block');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' https://cdn.tailwindcss.com; style-src \'self\' \'unsafe-inline\'; img-src \'self\' data:; font-src \'self\'; connect-src \'self\'');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('Cache-Control: no-store, max-age=0');

// Platform maintenance can be set via env (APP_MAINTENANCE) or the super-admin
// panel (admin/settings -> settings table). Combine both at runtime.
$platformMaintenance = false;
try {
    $platformMaintenance = (string) \Muh\Core\DB::scalar("SELECT value FROM settings WHERE `group` = 'platform' AND `key` = 'maintenance'") === '1';
} catch (\Throwable $e) {
    // table/config not ready during setup
}
$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$isAdminOrLogin = str_contains($path, '/admin') || str_contains($path, '/login') || str_contains($path, '/logout');
if ((config('app.maintenance', false) || $platformMaintenance) && !$debug && !$isAdminOrLogin) {
    \Muh\Core\Session::forget('auth_user');
    header('HTTP/1.1 503 Service Unavailable');
    $announcement = '';
    try {
        $announcement = (string) \Muh\Core\DB::scalar("SELECT value FROM settings WHERE `group` = 'platform' AND `key` = 'announcement'");
    } catch (\Throwable $e) {
    }
    echo $announcement !== '' ? ('Sistem bakım modunda. / ' . htmlspecialchars($announcement, ENT_QUOTES, 'UTF-8')) : 'Sistem bakım modunda. / System maintenance in progress.';
    exit;
}

set_exception_handler(function (\Throwable $e): void {
    app_log('Unhandled: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine(), 'ERROR');
    $debug = config('app.debug', false);
    $payload = ['error' => $debug ? $e->getMessage() : 'Internal Server Error'];
    \Muh\Core\Response::json($payload, 500)->send();
});

try {
    $response = $router->dispatch($request->method(), $request->path(), $request);
} catch (\Muh\Core\HttpException $e) {
    $payload = ['error' => $e->getMessage(), 'errors' => method_exists($e, 'errors') ? $e->errors : null];
    $response = \Muh\Core\Response::json($payload, $e->getCode() ?: 500);
} catch (\Throwable $e) {
    app_log('Unhandled: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine(), 'ERROR');
    $response = config('app.debug', false)
        ? \Muh\Core\Response::json(['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 500)
        : \Muh\Core\Response::json(['error' => 'Internal Server Error'], 500);
}

$response->send();

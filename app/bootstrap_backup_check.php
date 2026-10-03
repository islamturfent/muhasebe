<?php

declare(strict_types=1);

/**
 * Application bootstrap shared by the web front controller and CLI.
 * Loads config, connects to the DB, and starts the session.
 */

use Muh\Core\Application;

if (PHP_SAPI !== 'cli') {
    date_default_timezone_set('Europe/Istanbul');
}

$basePath = dirname(__DIR__);

/** Minimal .env loader; real environment variables always win. */
function muh_load_env(string $dir): void
{
    $file = $dir . '/.env';
    if (!is_file($file)) {
        return;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }
        $key = trim(substr($line, 0, $pos));
        $value = trim(substr($line, $pos + 1));
        // Strip trailing inline comments (" # comment") from the value.
        $hashPos = strpos($value, ' #');
        if ($hashPos !== false) {
            $value = trim(substr($value, 0, $hashPos));
        }
        if (getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

muh_load_env($basePath);

require $basePath . '/app/autoload.php';
require $basePath . '/app/helpers.php';

Application::boot($basePath);

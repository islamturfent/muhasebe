<?php

declare(strict_types=1);

/**
 * PSR-4-ish autoloader for the application (no external composer required).
 * Maps the "Muh\" namespace to the app/ directory.
 *
 * @param string $class Fully qualified class name.
 */
function muh_autoload(string $class): void
{
    $prefix = 'Muh\\';
    // Map Muh\* to the app/ directory (this file lives in app/).
    $baseDir = __DIR__ . DIRECTORY_SEPARATOR;

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
}

spl_autoload_register('muh_autoload');

<?php

declare(strict_types=1);

namespace Muh\Database;

use Muh\Core\DB;
use Muh\Core\Hash;
use Muh\Models\Permission;

/**
 * Seeds reference data (plans, roles, permissions, tax rates, currencies)
 * required by the SaaS platform.
 */
final class Seeder
{
    public function run(string $dir): void
    {
        foreach (glob(rtrim($dir, '/') . '/[0-9]*_*.php') as $file) {
            $name = basename($file, '.php');
            echo "Seeding: {$name}\n";
            $seeder = require $file;
            $seeder();
        }
    }
}

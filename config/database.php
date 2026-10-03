<?php

declare(strict_types=1);

/*
 * Database abstraction via PDO.
 * The schema and queries are kept driver-agnostic where possible so the
 * application can transparently switch between MySQL and PostgreSQL.
 *
 * To switch to PostgreSQL just change 'default' to 'pgsql' and fill the values.
 */
return [
    'default' => getenv('DB_CONNECTION') ?: 'mysql',

    'connections' => [
        'mysql' => [
            'driver'   => 'mysql',
            'host'     => getenv('DB_HOST') ?: '127.0.0.1',
            'port'     => getenv('DB_PORT') ?: '3306',
            'database' => getenv('DB_DATABASE') ?: 'muh',
            'username' => getenv('DB_USERNAME') ?: 'root',
            'password' => getenv('DB_PASSWORD') ?: '',
            'charset'  => 'utf8mb4',
            'collation'=> 'utf8mb4_unicode_ci',
            'options'  => [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ],
        ],

        'pgsql' => [
            'driver'   => 'pgsql',
            'host'     => getenv('DB_HOST') ?: '127.0.0.1',
            'port'     => getenv('DB_PORT') ?: '5432',
            'database' => getenv('DB_DATABASE') ?: 'muh',
            'username' => getenv('DB_USERNAME') ?: 'postgres',
            'password' => getenv('DB_PASSWORD') ?: '',
            'schema'   => 'public',
            'options'  => [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ],
        ],

        'sqlite' => [
            'driver'   => 'sqlite',
            'database' => dirname(__DIR__) . '/storage/database.sqlite',
            'options'  => [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ],
        ],
    ],
];

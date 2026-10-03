<?php

declare(strict_types=1);

namespace Muh\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Database abstraction layer built on PDO.
 *
 * Supports multiple drivers (mysql, pgsql, sqlite) via config so the schema
 * can be run against PostgreSQL or MySQL. All queries are parameterized to
 * prevent SQL injection.
 *
 * Provides:
 *  - a fluent/query-builder style for common operations
 *  - raw query helpers
 *  - transactions
 *  - safe identifier quoting
 */
final class DB
{
    private static ?PDO $pdo = null;
    private static string $driver = 'mysql';

    public static function connect(array $config): void
    {
        $driver = $config['default'];
        $connection = $config['connections'][$driver] ?? null;

        if (!$connection) {
            throw new RuntimeException("Database connection '{$driver}' is not configured.");
        }

        self::$driver = $connection['driver'];

        $options = $connection['options'] ?? [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        if ($driver === 'sqlite') {
            $dsn = 'sqlite:' . $connection['database'];
        } elseif ($driver === 'pgsql') {
            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                $connection['host'],
                $connection['port'],
                $connection['database']
            );
        } else {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $connection['host'],
                $connection['port'],
                $connection['database'],
                $connection['charset'] ?? 'utf8mb4'
            );
        }

        try {
            self::$pdo = new PDO($dsn, $connection['username'] ?? null, $connection['password'] ?? null, $options);
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public static function pdo(): PDO
    {
        if (!self::$pdo) {
            throw new RuntimeException('Database has not been connected.');
        }
        return self::$pdo;
    }

    public static function driver(): string
    {
        return self::$driver;
    }

    /** Run a SELECT and return all rows. */
    public static function select(string $sql, array $params = []): array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute(self::normalizeParams($params));
        return $stmt->fetchAll();
    }

    /** Run a SELECT and return a single row or null. */
    public static function first(string $sql, array $params = []): ?array
    {
        $rows = self::select($sql, $params);
        return $rows[0] ?? null;
    }

    /** Run a SELECT and return the first column of the first row. */
    public static function scalar(string $sql, array $params = []): mixed
    {
        $row = self::first($sql, $params);
        if (!$row) {
            return null;
        }
        return reset($row);
    }

    /** Run an INSERT/UPDATE/DELETE and return affected row count. */
    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute(self::normalizeParams($params));
        return $stmt->rowCount();
    }

    public static function lastInsertId(?string $name = null): string
    {
        return self::pdo()->lastInsertId($name);
    }

    /**
     * Insert a row and return the new id.
     */
    public static function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn ($c) => ':' . $c, $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            self::quoteIdentifier($table),
            implode(', ', array_map([self::class, 'quoteIdentifier'], $columns)),
            implode(', ', $placeholders)
        );

        self::execute($sql, $data);
        return (int) self::lastInsertId();
    }

    /**
     * Insert multiple rows in one statement (batch insert).
     */
    public static function insertMany(string $table, array $rows): void
    {
        if (empty($rows)) {
            return;
        }
        $columns = array_keys($rows[0]);
        $colList = implode(', ', array_map([self::class, 'quoteIdentifier'], $columns));

        $sqlParts = [];
        $params = [];
        foreach ($rows as $index => $row) {
            $wrapped = [];
            foreach ($columns as $col) {
                $key = ':b' . $index . '_' . $col;
                $wrapped[] = $key;
                $params[$key] = $row[$col] ?? null;
            }
            $sqlParts[] = '(' . implode(', ', $wrapped) . ')';
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES %s',
            self::quoteIdentifier($table),
            $colList,
            implode(', ', $sqlParts)
        );

        self::execute($sql, $params);
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $sets = [];
        $params = [];
        foreach ($data as $col => $value) {
            $sets[] = self::quoteIdentifier($col) . ' = :set_' . $col;
            $params['set_' . $col] = $value;
        }
        foreach ($whereParams as $key => $value) {
            $params[$key] = $value;
        }

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            self::quoteIdentifier($table),
            implode(', ', $sets),
            $where
        );

        return self::execute($sql, $params);
    }

    public static function delete(string $table, string $where, array $params = []): int
    {
        $sql = sprintf(
            'DELETE FROM %s WHERE %s',
            self::quoteIdentifier($table),
            $where
        );
        return self::execute($sql, $params);
    }

    // ------------------------------------------------------------------
    // Transactions
    // ------------------------------------------------------------------

    public static function beginTransaction(): bool
    {
        return self::pdo()->beginTransaction();
    }

    public static function commit(): bool
    {
        return self::pdo()->commit();
    }

    public static function rollBack(): bool
    {
        return self::pdo()->rollBack();
    }

    public static function inTransaction(): bool
    {
        return self::pdo()->inTransaction();
    }

    private static int $txDepth = 0;

    /**
     * Execute a callback inside a database transaction. Rolls back on exception.
     *
     * Nested calls create SAVEPOINTs so inner transactions can commit/roll back
     * independently without collapsing the outer transaction.
     */
    public static function transaction(callable $callback): mixed
    {
        $level = ++self::$txDepth;
        $isRoot = $level === 1;

        if ($isRoot) {
            self::beginTransaction();
        } else {
            self::pdo()->exec('SAVEPOINT sp_' . $level);
        }

        try {
            $result = $callback();
            if ($isRoot) {
                // MySQL implicit-commits on DDL; only commit when still active.
                if (self::inTransaction()) {
                    self::commit();
                }
            } else {
                self::pdo()->exec('RELEASE SAVEPOINT sp_' . $level);
            }
            return $result;
        } catch (\Throwable $e) {
            if ($isRoot) {
                if (self::inTransaction()) {
                    self::rollBack();
                }
            } else {
                self::pdo()->exec('ROLLBACK TO SAVEPOINT sp_' . $level);
                self::pdo()->exec('RELEASE SAVEPOINT sp_' . $level);
            }
            throw $e;
        } finally {
            self::$txDepth--;
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /** Safely quote an identifier for the active driver. */
    public static function quoteIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);
        if (self::$driver === 'pgsql') {
            return '"' . str_replace('"', '""', $identifier) . '"';
        }
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    public static function quote(string $value): string
    {
        return self::pdo()->quote($value);
    }

    private static function normalizeParams(array $params): array
    {
        $normalized = [];
        foreach ($params as $key => $value) {
            if (is_string($key) && str_starts_with($key, ':')) {
                $normalized[substr($key, 1)] = $value;
            } else {
                $normalized[$key] = $value;
            }
        }
        return $normalized;
    }
}

<?php

declare(strict_types=1);

namespace Muh\Database;

use Muh\Core\DB;

/**
 * Tiny migration runner. Schema is declared via driver-agnostic builders that
 * translate to MySQL/PostgreSQL syntax based on the active driver.
 */
final class Migrator
{
    public function run(string $dir): void
    {
        $this->ensureMigrationsTable();
        $files = glob($dir . '/[0-9]*_*.php');
        sort($files);

        $ran = $this->ranMigrations();

        foreach ($files as $file) {
            $name = basename($file, '.php');
            if (isset($ran[$name])) {
                continue;
            }
            echo "Migrating: {$name}\n";
            DB::transaction(function () use ($file, $name) {
                $migration = require $file;
                $migration->up();
                DB::insert('migrations', ['migration' => $name, 'run_at' => now()]);
            });
        }
    }

    public function rollback(string $dir): void
    {
        $files = glob($dir . '/[0-9]*_*.php');
        rsort($files);
        $ran = $this->ranMigrations();

        foreach ($files as $file) {
            $name = basename($file, '.php');
            if (!isset($ran[$name])) {
                continue;
            }
            echo "Rolling back: {$name}\n";
            $migration = require $file;
            if (method_exists($migration, 'down')) {
                $migration->down();
            }
            DB::delete('migrations', 'migration = :m', ['m' => $name]);
        }
    }

    private function ensureMigrationsTable(): void
    {
        $driver = DB::driver();
        if ($driver === 'mysql') {
            DB::execute(
                "CREATE TABLE IF NOT EXISTS migrations (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    migration VARCHAR(255) NOT NULL,
                    run_at DATETIME NOT NULL,
                    UNIQUE KEY uniq_migration (migration)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } elseif ($driver === 'pgsql') {
            DB::execute(
                'CREATE TABLE IF NOT EXISTS migrations (
                    id SERIAL PRIMARY KEY,
                    migration VARCHAR(255) NOT NULL UNIQUE,
                    run_at TIMESTAMP NOT NULL
                )'
            );
        } else {
            DB::execute(
                'CREATE TABLE IF NOT EXISTS migrations (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    migration TEXT NOT NULL UNIQUE,
                    run_at TEXT NOT NULL
                )'
            );
        }
    }

    private function ranMigrations(): array
    {
        $rows = DB::select('SELECT migration FROM migrations');
        $map = [];
        foreach ($rows as $row) {
            $map[$row['migration']] = true;
        }
        return $map;
    }
}

/**
 * Cross-driver schema/table builder.
 */
final class Schema
{
    /**
     * Create a table from a definition callback.
     * Returns the generated SQL (executed immediately).
     */
    public static function create(string $table, callable $callback): string
    {
        $t = new Table($table);
        $callback($t);
        $sql = $t->build();
        DB::execute($sql);
        return $sql;
    }

    public static function drop(string $table): void
    {
        DB::execute('DROP TABLE IF EXISTS ' . DB::quoteIdentifier($table));
    }
}

/**
 * Fluent column/index builder producing CREATE TABLE DDL for MySQL & PG.
 */
final class Table
{
    private string $name;
    private array $columns = [];
    private array $constraints = [];   // primary/unique/index lines
    private array $indexes = [];
    private array $foreignKeys = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    // --- Column definitions -------------------------------------------------

    public function id(): void
    {
        if ($this->isPg()) {
            $this->columns[] = 'id BIGSERIAL NOT NULL';
        } else {
            $this->columns[] = 'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT';
        }
        $this->constraints['PRIMARY'] = 'PRIMARY KEY (id)';
    }

    public function bigId(): void
    {
        $this->id();
    }

    public function uuid(): void
    {
        $this->columns[] = 'uuid VARCHAR(64) NOT NULL';
        $this->unique('uuid');
    }

    public function string(string $name, int $length = 255, bool $nullable = false, string $default = ''): self
    {
        $sql = DB::quoteIdentifier($name) . ' VARCHAR(' . $length . ') ' . ($nullable ? 'NULL' : 'NOT NULL');
        if ($default !== '') {
            $sql .= " DEFAULT '" . addslashes($default) . "'";
        }
        $this->columns[] = $sql;
        return $this;
    }

    public function text(string $name, bool $nullable = false): self
    {
        $this->columns[] = DB::quoteIdentifier($name) . ' ' . ($this->isPg() ? 'TEXT' : 'TEXT') . ' ' . ($nullable ? 'NULL' : 'NOT NULL');
        return $this;
    }

    public function longText(string $name): self
    {
        $this->columns[] = DB::quoteIdentifier($name) . ' ' . ($this->isPg() ? 'TEXT' : 'LONGTEXT') . ' NULL';
        return $this;
    }

    public function json(string $name): self
    {
        $this->columns[] = DB::quoteIdentifier($name) . ' ' . ($this->isPg() ? 'JSONB' : 'JSON') . ' NULL';
        return $this;
    }

    public function decimal(string $name, int $precision = 15, int $scale = 2, bool $nullable = false, string $default = '0'): self
    {
        $sql = DB::quoteIdentifier($name) . ' DECIMAL(' . $precision . ',' . $scale . ') ' . ($nullable ? 'NULL' : 'NOT NULL') . " DEFAULT " . $default;
        $this->columns[] = $sql;
        return $this;
    }

    public function integer(string $name, bool $nullable = false, int $default = 0): self
    {
        $sql = DB::quoteIdentifier($name) . ' INT ' . ($nullable ? 'NULL' : 'NOT NULL') . ' DEFAULT ' . $default;
        $this->columns[] = $sql;
        return $this;
    }

    public function bigInteger(string $name, bool $nullable = false): self
    {
        $sql = DB::quoteIdentifier($name) . ' BIGINT ' . ($nullable ? 'NULL' : 'NOT NULL');
        $this->columns[] = $sql;
        return $this;
    }

    /** Foreign key column (matches the id() type). */
    public function foreignId(string $name): self
    {
        $type = $this->isPg() ? 'BIGINT' : 'BIGINT UNSIGNED';
        $this->columns[] = DB::quoteIdentifier($name) . ' ' . $type . ' NULL';
        return $this;
    }

    public function nullableForeignId(string $name): self
    {
        return $this->foreignId($name);
    }

    public function boolean(string $name, bool $default = false): self
    {
        $this->columns[] = DB::quoteIdentifier($name) . ' ' . ($this->isPg() ? 'BOOLEAN' : 'TINYINT(1)') . ' NOT NULL DEFAULT ' . ($default ? '1' : '0');
        return $this;
    }

    /** Datetime nullable (for date fields, soft deletes). */
    public function dateTime(string $name, bool $nullable = true): self
    {
        $type = $this->isPg() ? 'TIMESTAMP' : 'DATETIME';
        $this->columns[] = DB::quoteIdentifier($name) . ' ' . $type . ' ' . ($nullable ? 'NULL' : 'NOT NULL');
        return $this;
    }

    public function date(string $name, bool $nullable = true): self
    {
        $type = $this->isPg() ? 'DATE' : 'DATE';
        $this->columns[] = DB::quoteIdentifier($name) . ' ' . $type . ' ' . ($nullable ? 'NULL' : 'NOT NULL');
        return $this;
    }

    public function timestamps(): void
    {
        $created = $this->isPg() ? 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP' : 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP';
        $updated = $this->isPg() ? 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP' : 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP';
        $this->columns[] = DB::quoteIdentifier('created_at') . ' ' . $created;
        $this->columns[] = DB::quoteIdentifier('updated_at') . ' ' . $updated;
    }

    public function softDeletes(): void
    {
        $type = $this->isPg() ? 'TIMESTAMP' : 'DATETIME';
        $this->columns[] = DB::quoteIdentifier('deleted_at') . ' ' . $type . ' NULL';
    }

    // --- Indexes / constraints ----------------------------------------------

    public function unique(string|array $column, string $name = ''): void
    {
        $colKey = is_array($column) ? implode('_', $column) : $column;
        $name = $name ?: $this->tableName() . '_' . $colKey . '_unique';
        $this->indexes[] = ['type' => 'UNIQUE', 'name' => $name, 'cols' => is_array($column) ? $column : [$column]];
    }

    public function index(string|array $column, string $name = ''): void
    {
        $name = $name ?: $this->tableName() . '_' . (is_array($column) ? implode('_', $column) : $column) . '_index';
        $this->indexes[] = ['type' => 'INDEX', 'name' => $name, 'cols' => is_array($column) ? $column : [$column]];
    }

    public function foreign(string $column, string $refTable, string $refColumn = 'id'): void
    {
        $this->foreignKeys[] = [
            'col' => $column,
            'ref' => $refTable,
            'refCol' => $refColumn,
        ];
    }

    // --- Build ------------------------------------------------------------

    public function build(): string
    {
        $driver = DB::driver();
        $lines = array_merge($this->columns);

        if ($this->isPg()) {
            foreach ($this->foreignKeys as $fk) {
                $lines[] = 'CONSTRAINT fk_' . $this->tableName() . '_' . $fk['col']
                    . ' FOREIGN KEY (' . DB::quoteIdentifier($fk['col']) . ') REFERENCES '
                    . DB::quoteIdentifier($fk['ref']) . '(' . DB::quoteIdentifier($fk['refCol']) . ') ON DELETE SET NULL';
            }
        }

        // Inline unique/primary for both, using named constraints where possible.
        $allLines = $lines;

        if ($driver === 'mysql') {
            // MySQL: add PRIMARY/UNIQUE/INDEX/foreign as table options
            $opt = [];
            if (!empty($this->constraints['PRIMARY'])) {
                $opt[] = 'PRIMARY KEY (id)';
            }
            foreach ($this->indexes as $ix) {
                $cols = implode(', ', array_map([DB::class, 'quoteIdentifier'], $ix['cols']));
                $opt[] = ($ix['type'] === 'UNIQUE' ? 'UNIQUE KEY' : 'KEY') . ' ' . DB::quoteIdentifier($ix['name']) . ' (' . $cols . ')';
            }
            foreach ($this->foreignKeys as $fk) {
                $opt[] = 'CONSTRAINT ' . DB::quoteIdentifier('fk_' . $this->tableName() . '_' . $fk['col'])
                    . ' FOREIGN KEY (' . DB::quoteIdentifier($fk['col']) . ') REFERENCES '
                    . DB::quoteIdentifier($fk['ref']) . '(' . DB::quoteIdentifier($fk['refCol']) . ') ON DELETE SET NULL';
            }
            $body = implode(",\n    ", $allLines);
            $body .= ($opt ? ",\n    " . implode(",\n    ", $opt) : '');
            $sql = "CREATE TABLE IF NOT EXISTS " . DB::quoteIdentifier($this->name) . " (\n    " . $body . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        } else {
            // PostgreSQL
            $pgLines = $allLines;
            foreach ($this->indexes as $ix) {
                $pgLines[] = 'CONSTRAINT ' . DB::quoteIdentifier($ix['name']) . ' ' . ($ix['type'] === 'UNIQUE' ? 'UNIQUE' : '') . ' (' . implode(', ', array_map([DB::class, 'quoteIdentifier'], $ix['cols'])) . ')';
            }
            $body = implode(",\n    ", $pgLines);
            $sql = "CREATE TABLE IF NOT EXISTS " . DB::quoteIdentifier($this->name) . " (\n    " . $body . "\n)";
        }

        return $sql;
    }

    private function isPg(): bool
    {
        return DB::driver() === 'pgsql';
    }

    private function tableName(): string
    {
        return $this->name;
    }
}

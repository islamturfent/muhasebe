<?php

declare(strict_types=1);

namespace Muh\Database;

use Muh\Core\Config;
use Muh\Core\DB;

/**
 * Simple DB backup (Phase 12). Uses mysqldump when available, otherwise a
 * native PDO export that reproduces the schema + data as SQL.
 */
final class Backup
{
    public function run(): string
    {
        $dir = dirname(__DIR__, 2) . '/storage/backups';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $file = $dir . '/muh-' . date('Ymd-His') . '.sql';

        $driver = DB::driver();
        if ($driver === 'mysql') {
            $this->mysqldump($file) || $this->pdoDump($file);
        } else {
            $this->pdoDump($file);
        }

        return $file;
    }

    private function mysqldump(string $file): bool
    {
        $cfg = Config::get('database.connections.mysql', []);
        $bin = 'mysqldump';
        $cmd = sprintf(
            '"%s" -h %s -P %s -u %s %s %s > "%s" 2>/dev/null',
            $bin,
            $cfg['host'] ?? '127.0.0.1',
            $cfg['port'] ?? '3306',
            $cfg['username'] ?? 'root',
            $cfg['password'] !== '' ? '-p' . $cfg['password'] : '',
            $cfg['database'] ?? 'muh',
            $file
        );
        @exec($cmd, $out, $code);
        return $code === 0 && is_file($file) && filesize($file) > 0;
    }

    private function pdoDump(string $file): void
    {
        $pdo = DB::pdo();
        $out = "-- MUH database backup\n-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
        $out .= "SET FOREIGN_KEY_CHECKS=0;\n";

        $names = [];
        foreach (DB::select('SHOW TABLES') as $row) {
            $names[] = reset($row);
        }

        foreach ($names as $table) {
            $create = $pdo->query('SHOW CREATE TABLE ' . DB::quoteIdentifier($table))->fetch(\PDO::FETCH_NUM);
            $out .= ($create ? $create[1] : '') . ";\n\n";

            $rows = DB::select('SELECT * FROM ' . DB::quoteIdentifier($table));
            foreach ($rows as $row) {
                $cols = array_map([DB::class, 'quoteIdentifier'], array_keys($row));
                $vals = [];
                foreach (array_values($row) as $v) {
                    $vals[] = $v === null ? 'NULL' : DB::quote((string) $v);
                }
                $out .= 'INSERT INTO ' . DB::quoteIdentifier($table) . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ");\n";
            }
            $out .= "\n";
        }

        $out .= "SET FOREIGN_KEY_CHECKS=1;\n";
        file_put_contents($file, $out);
    }
}

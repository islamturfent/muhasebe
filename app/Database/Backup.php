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

    /**
     * Off-box upload: copy a backup file to BACKUP_REMOTE_DIR (local mount or
     * mounted network drive) when configured. Returns the remote path or null.
     */
    public static function upload(string $file): ?string
    {
        $remote = (string) (getenv('BACKUP_REMOTE_DIR') ?: '');
        if ($remote === '' || !is_file($file)) {
            return null;
        }
        if (!is_dir($remote)) {
            @mkdir($remote, 0775, true);
        }
        $dest = rtrim($remote, '/') . '/' . basename($file);
        return @copy($file, $dest) ? $dest : null;
    }

    /**
     * Restore a .sql backup. Uses the mysql client when available; otherwise a
     * best-effort statement-by-statement PDO import.
     *
     * @return int number of statements executed (best-effort)
     */
    public static function restore(string $file): int
    {
        if (!is_file($file)) {
            throw new \InvalidArgumentException("Backup file not found: {$file}");
        }
        $driver = DB::driver();
        if ($driver === 'mysql') {
            $cfg = Config::get('database.connections.mysql', []);
            $bin = 'mysql';
            $cmd = sprintf(
                '"%s" -h %s -P %s -u %s %s %s < "%s" 2>/dev/null',
                $bin,
                $cfg['host'] ?? '127.0.0.1',
                $cfg['port'] ?? '3306',
                $cfg['username'] ?? 'root',
                $cfg['password'] !== '' ? '-p' . $cfg['password'] : '',
                $cfg['database'] ?? 'muh',
                $file
            );
            @exec($cmd, $out, $code);
            if ($code === 0) {
                return 1;
            }
        }
        // Best-effort PDO import.
        $pdo = DB::pdo();
        $sql = (string) file_get_contents($file);
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        $n = 0;
        foreach ($statements as $stmt) {
            if ($stmt === '') {
                continue;
            }
            try {
                $pdo->exec($stmt);
                $n++;
            } catch (\Throwable $e) {
                // ignore per-statement failures for partial/targeted restores
            }
        }
        return $n;
    }

    /**
     * Retention: keep only the newest N backup files in a directory (local
     * storage/backups and, when set, BACKUP_REMOTE_DIR). Returns the number of
     * files pruned. N is read from BACKUP_RETENTION (default 14).
     */
    public static function prune(int $keep = 0): int
    {
        $keep = $keep > 0 ? $keep : (int) (getenv('BACKUP_RETENTION') ?: 14);
        if ($keep < 1) {
            $keep = 14;
        }
        $pruned = 0;
        $local = dirname(__DIR__, 2) . '/storage/backups';
        foreach ([$local, (string) (getenv('BACKUP_REMOTE_DIR') ?: '')] as $dir) {
            if ($dir === '' || !is_dir($dir)) {
                continue;
            }
            $files = glob(rtrim($dir, '/') . '/muh-*.sql');
            if (!$files) {
                continue;
            }
            // newest first (filename embeds Ymd-His)
            usort($files, fn ($a, $b) => strcmp(basename($b), basename($a)));
            foreach (array_slice($files, $keep) as $old) {
                if (@unlink($old)) {
                    $pruned++;
                }
            }
        }
        return $pruned;
    }

    /** DB health ping: returns the database driver + a true/false reachability. */
    public static function ping(): array
    {
        try {
            $ok = DB::scalar('SELECT 1') != null;
            return ['ok' => true, 'driver' => DB::driver(), 'detail' => 'DB erişilebilir'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'driver' => DB::driver(), 'detail' => $e->getMessage()];
        }
    }

    /** Confirm the backup documents directory is writable. */
    public static function storageWritable(): array
    {
        $dir = dirname(__DIR__, 2) . '/storage/backups';
        $ok = is_dir($dir) ? is_writable($dir) : is_writable(dirname($dir));
        return ['ok' => $ok, 'dir' => $dir];
    }

    /** List backups (newest first) for the health indicator / management. */
    public static function list(int $limit = 10): array
    {
        $dir = dirname(__DIR__, 2) . '/storage/backups';
        if (!is_dir($dir)) {
            return [];
        }
        $files = glob(rtrim($dir, '/') . '/muh-*.sql');
        if (!$files) {
            return [];
        }
        usort($files, fn ($a, $b) => strcmp(basename($b), basename($a)));
        $out = [];
        foreach (array_slice($files, 0, $limit) as $f) {
            $out[] = ['file' => basename($f), 'path' => $f, 'size' => filesize($f), 'time' => filemtime($f)];
        }
        return $out;
    }
}

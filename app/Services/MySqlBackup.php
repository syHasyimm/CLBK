<?php

namespace App\Services;

use App\Support\Database\MySqlDatabase;
use App\Support\Database\ReadSnapshot;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Throwable;

/** Portable signed archives retain the existing SQLite container format, never the live database. */
final class MySqlBackup
{
    public function __construct(private MySqlDatabase $database) {}

    public function export(string $file): void
    {
        app(ReadSnapshot::class)->run(function () use ($file) {
            $source = DB::connection()->getPdo();
            // Preserve IDs of deleted drafts too; cached table statistics can lag inserts.
            $source->exec('SET SESSION information_schema_stats_expiry=0');
            $target = new PDO('sqlite:'.$file, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $target->exec('PRAGMA foreign_keys=OFF');
            $target->beginTransaction();
            $keys = $this->database->foreignKeys();
            foreach ($this->database->tables() as $table) {
                $columns = $this->database->columns($table);
                $definitions = [];
                $primary = array_values(array_filter($columns, fn ($column) => $column['Key'] === 'PRI'));
                $auto = null;
                foreach ($columns as $column) {
                    $type = str_contains(strtolower($column['Type']), 'int') ? 'INTEGER' : 'TEXT';
                    $definition = $this->database->quote($column['Field']).' '.$type;
                    if (str_contains($column['Extra'], 'auto_increment')) {
                        $auto = $column['Field'];
                        $definition .= ' PRIMARY KEY AUTOINCREMENT';
                    } elseif ($column['Null'] === 'NO') {
                        $definition .= ' NOT NULL';
                    }
                    $definitions[] = $definition;
                }
                if ($auto === null && $primary !== []) {
                    $definitions[] = 'PRIMARY KEY ('.implode(',', array_map(fn ($column) => $this->database->quote($column['Field']), $primary)).')';
                }
                foreach ($keys as $key) {
                    if ($key['TABLE_NAME'] === $table) {
                        $definitions[] = 'FOREIGN KEY ('.$this->database->quote($key['COLUMN_NAME']).') REFERENCES '.$this->database->quote($key['REFERENCED_TABLE_NAME']).' ('.$this->database->quote($key['REFERENCED_COLUMN_NAME']).')';
                    }
                }
                $quoted = $this->database->quote($table);
                $target->exec('CREATE TABLE '.$quoted.' ('.implode(',', $definitions).')');
                $names = implode(',', array_map(fn ($column) => $this->database->quote($column['Field']), $columns));
                $insert = $target->prepare('INSERT INTO '.$quoted.' ('.$names.') VALUES ('.implode(',', array_fill(0, count($columns), '?')).')');
                foreach ($source->query('SELECT '.$names.' FROM '.$quoted, PDO::FETCH_NUM) as $row) {
                    $insert->execute($row);
                }
                if ($auto !== null) {
                    $statement = $source->prepare('SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
                    $statement->execute([$table]);
                    $next = (int) $statement->fetchColumn();
                    $target->prepare('DELETE FROM sqlite_sequence WHERE name=?')->execute([$table]);
                    $target->prepare('INSERT INTO sqlite_sequence(name,seq) VALUES (?,?)')->execute([$table, max(0, $next - 1)]);
                }
            }
            $target->commit();
            $target->exec('PRAGMA foreign_keys=ON');
            if ($target->query('PRAGMA foreign_key_check')->fetchAll() !== []) {
                throw new RuntimeException('Snapshot MySQL gagal pemeriksaan foreign key.');
            }
        });
    }

    public function assertEmpty(): void
    {
        $runtime = ['migrations', 'database_write_locks', 'sessions', 'password_reset_tokens', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];
        foreach (array_diff($this->database->tables(), $runtime) as $table) {
            if (DB::table($table)->exists()) {
                throw new RuntimeException('--new-server hanya untuk database MySQL tanpa data usaha. Jalankan migrate tanpa seed sebelum restore.');
            }
        }
    }

    public function restore(string $file, array $expected): array
    {
        if (! app()->isDownForMaintenance()) {
            throw new RuntimeException('Restore MySQL hanya boleh dijalankan dalam maintenance mode.');
        }
        $this->database->assertVersion();
        if (! $this->database->guardsPresent()) {
            throw new RuntimeException('Schema/trigger MySQL belum lengkap. Jalankan migrasi pada database kosong sebelum restore.');
        }
        $source = new PDO('sqlite:'.$file, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $source->exec('PRAGMA query_only=ON');
        $tables = $this->database->tables();
        $sourceTables = $source->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
        if ($tables !== $sourceTables) {
            throw new RuntimeException('Schema cadangan berbeda dari MySQL tujuan. Gunakan cadangan MySQL dari versi aplikasi yang sama.');
        }
        foreach ($tables as $table) {
            $actual = array_column($this->database->columns($table), 'Field');
            $saved = array_column($source->query('PRAGMA table_info('.$this->database->quote($table).')')->fetchAll(PDO::FETCH_ASSOC), 'name');
            if ($actual !== $saved) {
                throw new RuntimeException('Kolom cadangan berbeda dari MySQL tujuan: '.$table);
            }
        }
        $pdo = DB::connection()->getPdo();
        $triggers = [];
        foreach ($pdo->query('SHOW TRIGGERS', PDO::FETCH_ASSOC) as $trigger) {
            $definition = $pdo->query('SHOW CREATE TRIGGER '.$this->database->quote($trigger['Trigger']))->fetch(PDO::FETCH_ASSOC);
            $triggers[$trigger['Trigger']] = $definition['SQL Original Statement'];
        }
        try {
            // DDL commits implicitly in MySQL. Drop guards before the data transaction,
            // then restore them in finally even if the data replacement rolls back.
            foreach (array_keys($triggers) as $name) {
                $pdo->exec('DROP TRIGGER '.$this->database->quote($name));
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            $pdo->beginTransaction();
            foreach ($tables as $table) {
                $quoted = $this->database->quote($table);
                $pdo->exec('DELETE FROM '.$quoted);
                $columns = array_column($this->database->columns($table), 'Field');
                $names = implode(',', array_map($this->database->quote(...), $columns));
                $insert = $pdo->prepare('INSERT INTO '.$quoted.' ('.$names.') VALUES ('.implode(',', array_fill(0, count($columns), '?')).')');
                foreach ($source->query('SELECT '.$names.' FROM '.$quoted, PDO::FETCH_NUM) as $row) {
                    $insert->execute($row);
                }
            }
            if (! $this->database->integrity()) {
                throw new RuntimeException('Rekonsiliasi foreign key MySQL gagal.');
            }
            $check = $file.'.restored';
            try {
                $this->export($check);
                $actual = app(BackupSnapshot::class)->inspect($check);
                if ($actual !== $expected) {
                    throw new RuntimeException('Rekonsiliasi setelah restore MySQL gagal; aplikasi tetap maintenance.');
                }
            } finally {
                if (is_file($check)) {
                    unlink($check);
                }
            }
            $pdo->commit();
        } finally {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            $existing = $pdo->query('SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()')->fetchAll(PDO::FETCH_COLUMN);
            $failures = [];
            foreach ($triggers as $name => $definition) {
                if (! in_array($name, $existing, true)) {
                    try {
                        $pdo->exec($definition);
                    } catch (Throwable $error) {
                        $failures[] = $error;
                    }
                }
            }
            if ($failures !== []) {
                throw new RuntimeException('Trigger MySQL tidak dapat dipulihkan. Tetap maintenance dan periksa izin TRIGGER sebelum membuka aplikasi.', previous: $failures[0]);
            }
        }
        foreach ($source->query('SELECT name,seq FROM sqlite_sequence', PDO::FETCH_ASSOC) as $sequence) {
            if (in_array($sequence['name'], $tables, true)) {
                $next = max(1, (int) $sequence['seq'] + 1);
                $pdo->exec('ALTER TABLE '.$this->database->quote($sequence['name']).' AUTO_INCREMENT='.$next);
            }
        }

        return $actual;
    }
}

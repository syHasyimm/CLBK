<?php

namespace App\Services;

use App\Support\Database\MySqlDatabase;
use App\Support\Database\ReadSnapshot;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Throwable;

final class MySqlBackup
{
    public function __construct(private MySqlDatabase $database) {}

    public function export(string $file): void
    {
        app(ReadSnapshot::class)->run(function () use ($file) {
            if (! $this->database->integrity()) {
                throw new RuntimeException('Integritas MySQL tidak valid; cadangan dibatalkan.');
            }
            $stream = fopen($file, 'xb');
            if (! $stream) {
                throw new RuntimeException('File data cadangan tidak dapat dibuat.');
            }
            try {
                foreach (app(MySqlBackupData::class)->live() as $record) {
                    $line = json_encode($record, JSON_THROW_ON_ERROR)."\n";
                    if (fwrite($stream, $line) !== strlen($line)) {
                        throw new RuntimeException('Data cadangan gagal ditulis.');
                    }
                }
            } finally {
                fclose($stream);
            }
        });
    }

    public function assertEmpty(): void
    {
        $runtime = ['migrations', 'database_write_locks', 'sessions', 'password_reset_tokens', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];
        foreach (array_diff($this->database->tables(), $runtime) as $table) {
            if (DB::table($table)->exists()) {
                throw new RuntimeException('--new-server hanya untuk database tujuan MySQL tanpa data usaha. Jalankan migrate tanpa seed sebelum restore.');
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
        $tables = $this->database->tables();
        $savedTables = [];
        $sequences = [];
        foreach (app(MySqlBackupData::class)->read($file) as $record) {
            if (isset($record['table'])) {
                $table = $record['table'];
                if (! in_array($table, $tables, true) || array_column($this->database->columns($table), 'Field') !== $record['columns']) {
                    throw new RuntimeException('Schema cadangan berbeda dari MySQL tujuan. Gunakan versi aplikasi yang sama.');
                }
                $savedTables[] = $table;
                if ($record['auto_increment'] !== null) {
                    $sequences[$table] = (int) $record['auto_increment'];
                }
            }
        }
        if ($tables !== $savedTables) {
            throw new RuntimeException('Tabel cadangan berbeda dari MySQL tujuan.');
        }
        $pdo = DB::connection()->getPdo();
        $triggers = [];
        foreach ($pdo->query('SHOW TRIGGERS', PDO::FETCH_ASSOC) as $trigger) {
            $definition = $pdo->query('SHOW CREATE TRIGGER '.$this->database->quote($trigger['Trigger']))->fetch(PDO::FETCH_ASSOC);
            $triggers[$trigger['Trigger']] = $definition['SQL Original Statement'];
        }
        try {
            // MySQL DDL commits implicitly; guards must be restored even after rollback.
            foreach (array_keys($triggers) as $name) {
                $pdo->exec('DROP TRIGGER '.$this->database->quote($name));
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            $pdo->beginTransaction();
            $insert = null;
            foreach (app(MySqlBackupData::class)->read($file) as $record) {
                if (isset($record['table'])) {
                    $quoted = $this->database->quote($record['table']);
                    $pdo->exec('DELETE FROM '.$quoted);
                    $names = implode(',', array_map($this->database->quote(...), $record['columns']));
                    $insert = $pdo->prepare('INSERT INTO '.$quoted.' ('.$names.') VALUES ('.implode(',', array_fill(0, count($record['columns']), '?')).')');
                } elseif (isset($record['row'])) {
                    $insert->execute(array_map(fn ($cell) => $cell === null ? null : base64_decode($cell, true), $record['row']));
                }
            }
            if (! $this->database->integrity()) {
                throw new RuntimeException('Rekonsiliasi foreign key MySQL gagal.');
            }
            $actual = app(BackupSnapshot::class)->inspect();
            if ($actual !== $expected) {
                throw new RuntimeException('Rekonsiliasi setelah restore MySQL gagal; aplikasi tetap maintenance.');
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
                throw new RuntimeException('Trigger MySQL tidak dapat dipulihkan. Tetap maintenance dan periksa izin TRIGGER.', previous: $failures[0]);
            }
        }
        foreach ($sequences as $table => $next) {
            $pdo->exec('ALTER TABLE '.$this->database->quote($table).' AUTO_INCREMENT='.$next);
        }

        return $actual;
    }
}

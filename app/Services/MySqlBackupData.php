<?php

namespace App\Services;

use App\Support\Database\MySqlDatabase;
use Generator;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

/** A streaming data archive; string cells use base64 to preserve arbitrary bytes. */
final class MySqlBackupData
{
    public function live(): Generator
    {
        $database = app(MySqlDatabase::class);
        $pdo = DB::connection()->getPdo();
        yield ['format' => 2, 'driver' => 'mysql'];
        foreach ($database->tables() as $table) {
            $columns = $database->columns($table);
            $names = array_column($columns, 'Field');
            $primary = array_column(array_filter($columns, fn ($column) => $column['Key'] === 'PRI'), 'Field');
            $order = implode(',', array_map($database->quote(...), $primary ?: $names));
            $statement = $pdo->prepare('SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
            $statement->execute([$table]);
            yield ['table' => $table, 'columns' => $names, 'auto_increment' => $statement->fetchColumn() ?: null];
            foreach ($pdo->query('SELECT * FROM '.$database->quote($table).' ORDER BY '.$order, PDO::FETCH_NUM) as $row) {
                yield ['row' => array_map(fn ($value) => $value === null ? null : base64_encode((string) $value), $row)];
            }
        }
    }

    public function read(string $file): Generator
    {
        $stream = fopen($file, 'rb');
        if (! $stream) {
            throw new RuntimeException('Data cadangan tidak dapat dibaca.');
        }
        $tables = [];
        $columns = null;
        try {
            $header = json_decode(fgets($stream) ?: '', true, flags: JSON_THROW_ON_ERROR);
            if ($header !== ['format' => 2, 'driver' => 'mysql']) {
                throw new RuntimeException('Format data cadangan MySQL tidak dikenali.');
            }
            yield $header;
            while (($line = fgets($stream)) !== false) {
                $record = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                if (isset($record['table'])) {
                    if (! is_string($record['table']) || ! preg_match('/\A[a-z][a-z0-9_]*\z/', $record['table'])
                        || in_array($record['table'], $tables, true) || ! is_array($record['columns'] ?? null)
                        || ! array_is_list($record['columns']) || $record['columns'] === []
                        || count(array_unique($record['columns'])) !== count($record['columns'])) {
                        throw new RuntimeException('Definisi tabel cadangan tidak valid.');
                    }
                    foreach ($record['columns'] as $column) {
                        if (! is_string($column) || ! preg_match('/\A[a-z][a-z0-9_]*\z/', $column)) {
                            throw new RuntimeException('Kolom cadangan tidak valid.');
                        }
                    }
                    $next = $record['auto_increment'] ?? null;
                    if ($next !== null && (! is_numeric($next) || (string) (int) $next !== (string) $next || (int) $next < 1)) {
                        throw new RuntimeException('Urutan ID cadangan tidak valid.');
                    }
                    $tables[] = $record['table'];
                    $columns = $record['columns'];
                } elseif (isset($record['row']) && is_array($record['row']) && array_is_list($record['row']) && $columns !== null && count($record['row']) === count($columns)) {
                    foreach ($record['row'] as $cell) {
                        if ($cell !== null && (! is_string($cell) || base64_decode($cell, true) === false)) {
                            throw new RuntimeException('Isi baris cadangan tidak valid.');
                        }
                    }
                } else {
                    throw new RuntimeException('Struktur data cadangan tidak valid.');
                }
                yield $record;
            }
            if (! feof($stream) || $tables === []) {
                throw new RuntimeException('Data cadangan tidak lengkap.');
            }
        } finally {
            fclose($stream);
        }
    }
}

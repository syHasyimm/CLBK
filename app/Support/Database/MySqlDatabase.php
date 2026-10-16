<?php

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

final class MySqlDatabase
{
    public function quote(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }

    public function tables(): array
    {
        return DB::connection()->getPdo()->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
    }

    public function columns(string $table): array
    {
        return DB::connection()->getPdo()->query('SHOW COLUMNS FROM '.$this->quote($table))->fetchAll(PDO::FETCH_ASSOC);
    }

    public function foreignKeys(): array
    {
        return DB::connection()->getPdo()->query('SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function assertVersion(): void
    {
        if (! preg_match('/\A8\.4\./', (string) DB::selectOne('SELECT VERSION() AS version')->version)) {
            throw new RuntimeException('Aplikasi ini memerlukan MySQL 8.4.');
        }
    }

    public function guardsPresent(): bool
    {
        preg_match_all('/CREATE TRIGGER `([^`]+)`/i', file_get_contents(database_path('schema/clbk.sql')), $matches);
        $actual = DB::connection()->getPdo()->query('SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()')->fetchAll(PDO::FETCH_COLUMN);

        return $matches[1] !== [] && array_diff($matches[1], $actual) === []
            && DB::table('database_write_locks')->where('id', 1)->exists();
    }

    public function integrity(): bool
    {
        $engines = DB::selectOne("SELECT COUNT(*) AS invalid FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' AND ENGINE<>'InnoDB'");
        if ((int) $engines->invalid !== 0) {
            return false;
        }
        $constraints = [];
        foreach ($this->foreignKeys() as $key) {
            $constraints[$key['TABLE_NAME'].'.'.$key['CONSTRAINT_NAME']][] = $key;
        }
        foreach ($constraints as $keys) {
            $table = $this->quote($keys[0]['TABLE_NAME']);
            $reference = $this->quote($keys[0]['REFERENCED_TABLE_NAME']);
            $notNull = implode(' AND ', array_map(fn ($key) => 'c.'.$this->quote($key['COLUMN_NAME']).' IS NOT NULL', $keys));
            $join = implode(' AND ', array_map(fn ($key) => 'p.'.$this->quote($key['REFERENCED_COLUMN_NAME']).'=c.'.$this->quote($key['COLUMN_NAME']), $keys));
            if (DB::selectOne("SELECT EXISTS(SELECT 1 FROM {$table} c WHERE {$notNull} AND NOT EXISTS(SELECT 1 FROM {$reference} p WHERE {$join})) AS invalid")->invalid) {
                return false;
            }
        }

        return true;
    }
}

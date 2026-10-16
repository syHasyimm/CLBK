<?php

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class MySqlSchema
{
    public function install(): void
    {
        $database = app(MySqlDatabase::class);
        $database->assertVersion();
        foreach (array_diff($database->tables(), ['migrations']) as $table) {
            $query = DB::table($table);
            if ($table === 'database_write_locks') {
                $query->where('id', '<>', 1);
            }
            if ($query->exists()) {
                throw new RuntimeException('Schema awal hanya boleh dipasang pada database tanpa data usaha.');
            }
        }

        $pdo = DB::connection()->getPdo();
        $foreignKeys = (int) $pdo->query('SELECT @@FOREIGN_KEY_CHECKS')->fetchColumn();
        $delimiter = ';';
        $statement = '';
        try {
            foreach (file(database_path('schema/clbk.sql')) as $line) {
                $trimmed = trim($line);
                if ($trimmed === '' || str_starts_with($trimmed, '--')) {
                    continue;
                }
                if (str_starts_with($trimmed, 'DELIMITER ')) {
                    $delimiter = trim(substr($trimmed, 10));

                    continue;
                }
                $statement .= $line;
                if (str_ends_with(rtrim($statement), $delimiter)) {
                    $pdo->exec(substr(rtrim($statement), 0, -strlen($delimiter)));
                    $statement = '';
                }
            }
            if (trim($statement) !== '') {
                throw new RuntimeException('Pernyataan schema MySQL tidak lengkap.');
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS='.$foreignKeys);
        }
    }
}

<?php

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;

class ReadSnapshot
{
    // PDO begins a deferred SQLite read transaction, without reserving the writer lock.
    public function run(callable $read, ?string $connectionName = null): mixed
    {
        $pdo = DB::connection($connectionName)->getPdo();
        if ($pdo->inTransaction()) {
            return $read();
        }
        $pdo->beginTransaction();
        try {
            $result = $read();
            $pdo->commit();

            return $result;
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }
}

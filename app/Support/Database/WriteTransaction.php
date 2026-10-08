<?php

namespace App\Support\Database;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use LogicException;
use PDOException;

class WriteTransaction
{
    /**
     * Semua pembacaan saldo dan perubahan terkait berada di dalam callback.
     * Callback dapat diulang; efek di luar database dijalankan melalui afterCommit.
     * Panggil dari batas terluar use case, bukan dari transaksi yang sudah aktif.
     */
    public function run(Closure $callback, ?string $connectionName = null, int $attempts = 3): mixed
    {
        $connection = DB::connection($connectionName);

        if ($connection->transactionLevel() !== 0) {
            throw new LogicException('WriteTransaction harus dimulai di luar transaksi aktif.');
        }

        if ($attempts < 1 || $attempts > 5) {
            throw new LogicException('Jumlah percobaan transaksi harus antara 1 dan 5.');
        }

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $callbackCompleted = false;
            try {
                // Laravel mengelola commit/rollback; konfigurasi SQLite memakai BEGIN IMMEDIATE.
                return $connection->transaction(function ($transaction) use ($callback, &$callbackCompleted) {
                    $result = $callback($transaction);
                    $callbackCompleted = true;

                    return $result;
                }, 1);
            } catch (PDOException $exception) {
                // Jangan mengulang perubahan yang mungkin sudah commit ketika afterCommit gagal.
                if ($callbackCompleted) {
                    throw $exception;
                }

                // QueryException juga merupakan turunan PDOException.
                $driverCode = $exception->errorInfo[1] ?? null;
                if ($exception instanceof QueryException && $exception->getPrevious() instanceof PDOException) {
                    $driverCode = $exception->getPrevious()->errorInfo[1] ?? $driverCode;
                }

                $sqliteCode = is_numeric($driverCode) ? ((int) $driverCode & 0xFF) : null;
                if (! in_array($sqliteCode, [5, 6], true)) {
                    throw $exception;
                }

                if ($attempt === $attempts) {
                    throw new WriteConflict('Server sedang memproses transaksi lain. Silakan coba lagi.', previous: $exception);
                }

                usleep(25000 * (2 ** ($attempt - 1)) + random_int(0, 10000));
            }
        }

        throw new LogicException('Transaksi tidak menghasilkan hasil.');
    }
}

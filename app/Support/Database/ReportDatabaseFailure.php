<?php

namespace App\Support\Database;

use Illuminate\Support\Facades\Log;
use PDOException;
use Throwable;

final class ReportDatabaseFailure
{
    public function report(Throwable $exception): ?bool
    {
        if (! app()->isProduction()) {
            return null;
        }

        $cause = $exception;
        while (! $cause instanceof PDOException && $cause->getPrevious()) {
            $cause = $cause->getPrevious();
        }

        if (! $cause instanceof PDOException) {
            return null;
        }

        // Neither exception messages nor stack arguments/SQL bindings belong in production logs.
        Log::error('Database operation failed.', [
            'exception_type' => $exception::class,
            'sql_state' => $cause->errorInfo[0] ?? null,
            'driver_code' => $cause->errorInfo[1] ?? null,
        ]);

        return false;
    }
}

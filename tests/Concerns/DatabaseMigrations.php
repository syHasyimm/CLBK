<?php

namespace Tests\Concerns;

use Illuminate\Foundation\Testing\DatabaseMigrations as LaravelDatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;

trait DatabaseMigrations
{
    use LaravelDatabaseMigrations;

    public function runDatabaseMigrations(): void
    {
        $this->beforeRefreshingDatabase();
        $this->refreshTestDatabase();
        $this->afterRefreshingDatabase();

        // Each test owns a disposable database; protected business migrations cannot be rolled back.
        $this->beforeApplicationDestroyed(function () {
            RefreshDatabaseState::$migrated = false;
            DB::disconnect();
        });
    }
}

<?php

namespace Tests;

use App\Support\Database\MySqlDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function databaseEnvironment(): array
    {
        $settings = config('database.connections.mysql');

        return ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => $settings['host'],
            'DB_PORT' => (string) $settings['port'], 'DB_DATABASE' => $settings['database'],
            'DB_USERNAME' => $settings['username'], 'DB_PASSWORD' => $settings['password'], 'DB_URL' => '',
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'];
    }

    protected function assertForeignKeysAreValid(): void
    {
        $this->assertTrue(app(MySqlDatabase::class)->integrity());
    }
}

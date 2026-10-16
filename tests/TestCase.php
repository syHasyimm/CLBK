<?php

namespace Tests;

use App\Support\Database\MySqlDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function assertForeignKeysAreValid(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            $this->assertTrue(app(MySqlDatabase::class)->integrity());
        } else {
            $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        }
    }
}

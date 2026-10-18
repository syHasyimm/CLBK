<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\Weighing;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\DatabaseMigrations;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use DatabaseMigrations;

    public function test_default_seeder_initializes_all_local_and_testing_data_without_duplicates(): void
    {
        foreach (['local', 'testing'] as $environment) {
            $this->inEnvironment($environment, function () {
                $this->artisan('db:seed', ['--force' => true])->assertSuccessful();
                $this->assertDatabaseCount('app_settings', 4);
                $this->assertDatabaseCount('cash_accounts', 1);
                $this->assertDatabaseCount('expense_categories', 6);
                $this->assertDatabaseCount('cash_entries', 0);
                $this->assertDatabaseCount('users', 3);
                $this->assertSame(3, User::query()->distinct()->count('role'));
                $this->assertDatabaseCount('vehicles', 1);
                $this->assertSame('BK 2026 UAT', Vehicle::query()->sole()->plate_number);
                $this->assertDatabaseCount('weighings', 5);
                $this->assertSame(3, Weighing::whereBetween('business_date', ['2026-10-10', '2026-10-12'])->count());

                $tables = ['app_settings', 'cash_accounts', 'expense_categories', 'users', 'vehicles', 'weighings', 'audit_logs'];
                $before = [];
                foreach ($tables as $table) {
                    $before[$table] = DB::table($table)->get()->toJson();
                }
                $this->artisan('db:seed', ['--force' => true])->assertSuccessful();
                foreach ($tables as $table) {
                    $this->assertSame($before[$table], DB::table($table)->get()->toJson(), $table);
                }
            });
        }
    }

    public function test_default_seeder_in_production_only_creates_business_defaults(): void
    {
        $this->inEnvironment('production', function () {
            $this->artisan('db:seed', ['--force' => true])->assertSuccessful();
            $this->assertDatabaseCount('app_settings', 4);
            $this->assertDatabaseCount('cash_accounts', 1);
            $this->assertDatabaseCount('expense_categories', 6);
            foreach (['users', 'vehicles', 'customers', 'weighings', 'price_schedules', 'cash_entries', 'audit_logs'] as $table) {
                $this->assertDatabaseCount($table, 0);
            }
        });
    }

    private function inEnvironment(string $environment, callable $callback): void
    {
        $previous = app()->environment();
        app()->instance('env', $environment);
        try {
            $callback();
        } finally {
            app()->instance('env', $previous);
        }
    }
}

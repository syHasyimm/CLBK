<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Weighing;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\DatabaseMigrations;
use Tests\TestCase;

class SeedDateFilterCommandTest extends TestCase
{
    use DatabaseMigrations;

    private function fixtureOptions(array $overrides = []): array
    {
        return array_replace([
            '--staging-origin' => 'https://staging.clbk.test',
            '--database' => DB::connection()->getDatabaseName(),
            '--manager' => 'manager@staging.test', '--driver' => 'driver@staging.test',
        ], $overrides);
    }

    public function test_staging_command_requires_explicit_matching_origin_database_and_accounts_before_writing(): void
    {
        config(['app.url' => 'https://staging.clbk.test']);
        $this->artisan('clbk:seed-date-filter')->assertFailed();
        $this->artisan('clbk:seed-date-filter', $this->fixtureOptions(['--staging-origin' => 'https://production.clbk.test']))->assertFailed();
        $this->artisan('clbk:seed-date-filter', $this->fixtureOptions(['--database' => 'wrong-database']))->assertFailed();
        $this->artisan('clbk:seed-date-filter', $this->fixtureOptions())->assertFailed();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('vehicles', 0);
        $this->assertDatabaseCount('weighings', 0);
    }

    public function test_staging_command_works_with_production_settings_and_existing_accounts_without_creating_demo_users(): void
    {
        config(['app.url' => 'https://staging.clbk.test']);
        User::factory()->manager()->create(['email' => 'manager@staging.test']);
        $driver = User::factory()->create(['email' => 'driver@staging.test']);
        $environment = app()->environment();
        app()->instance('env', 'production');
        try {
            $this->artisan('clbk:seed-date-filter', $this->fixtureOptions())->assertSuccessful();
            $before = Weighing::orderBy('id')->get()->toJson();
            $this->artisan('clbk:seed-date-filter', $this->fixtureOptions())->assertSuccessful();
            $this->assertSame($before, Weighing::orderBy('id')->get()->toJson());
            $this->assertDatabaseCount('users', 2);
            $this->assertDatabaseCount('vehicles', 1);
            $this->assertDatabaseCount('weighings', 5);
            $this->assertSame(5, Weighing::where('created_by', $driver->id)->count());
            $this->assertSame(3, Weighing::whereBetween('business_date', ['2026-10-10', '2026-10-12'])->count());
            $this->assertForeignKeysAreValid();
        } finally {
            app()->instance('env', $environment);
        }
    }

    public function test_staging_command_rejects_inactive_or_wrong_role_accounts(): void
    {
        config(['app.url' => 'https://staging.clbk.test']);
        User::factory()->manager()->inactive()->create(['email' => 'manager@staging.test']);
        User::factory()->cashier()->create(['email' => 'driver@staging.test']);
        $this->artisan('clbk:seed-date-filter', $this->fixtureOptions())->assertFailed();
        $this->assertDatabaseCount('vehicles', 0);
        $this->assertDatabaseCount('weighings', 0);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Support\Database\MySqlDatabase;
use App\Support\Database\WriteTransaction;
use Illuminate\Cache\RateLimiter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\DatabaseMigrations;
use Tests\TestCase;

class MySqlDatabaseTest extends TestCase
{
    use DatabaseMigrations;

    protected function beforeRefreshingDatabase(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL integration suite only.');
        }
    }

    public function test_baseline_installs_real_foreign_keys_checks_and_business_guards(): void
    {
        $database = app(MySqlDatabase::class);
        $database->assertVersion();
        $this->assertTrue($database->guardsPresent());
        $this->assertTrue($database->integrity());
        $this->assertGreaterThan(50, count($database->foreignKeys()));
        $this->assertDatabaseCount('migrations', count(glob(database_path('migrations/*.php'))));
        $this->expectException(QueryException::class);
        DB::table('customers')->insert(['code' => 'INVALID', 'name' => 'Invalid', 'is_active' => 2]);
    }

    public function test_foreign_keys_reject_unknown_master_references(): void
    {
        $this->expectException(QueryException::class);
        DB::table('vehicles')->insert(['code' => 'FK', 'plate_number' => 'FK', 'default_driver_id' => 999999]);
    }

    public function test_reference_search_ignores_case_and_preserves_literal_backslashes(): void
    {
        $manager = User::factory()->manager()->create();
        Customer::create(['code' => 'AbC', 'name' => 'Andi\\Kebun', 'is_active' => true]);
        Customer::create(['code' => 'abc', 'name' => 'Other customer', 'is_active' => true]);
        $this->actingAs($manager)->getJson(route('references.index', ['resource' => 'customers', 'q' => 'andi\\kebun']))
            ->assertOk()->assertJsonPath('data.0.name', 'Andi\\Kebun');
        $this->assertDatabaseCount('customers', 2);
    }

    public function test_database_rate_limiter_accepts_long_email_and_ip_keys(): void
    {
        $email = str_repeat('a', 64).'@'.str_repeat('b', 63).'.'.str_repeat('c', 63).'.'.str_repeat('d', 57);
        $key = 'login:'.$email.'|2001:db8:1111:2222:3333:4444:5555:6666';
        $limiter = new RateLimiter(Cache::store('database'));
        $limiter->hit($key, 60);
        $this->assertSame(1, $limiter->attempts($key));
    }

    public function test_business_writes_hold_a_mysql_row_lock(): void
    {
        config(['database.connections.mysql_contender' => config('database.connections.mysql')]);
        $contender = DB::connection('mysql_contender');
        $contender->statement('SET SESSION innodb_lock_wait_timeout=1');
        try {
            app(WriteTransaction::class)->run(function () use ($contender) {
                try {
                    $contender->table('database_write_locks')->where('id', 1)->update(['id' => 1]);
                    $this->fail('Concurrent business writer did not wait for the mutex.');
                } catch (QueryException $error) {
                    $this->assertSame(1205, (int) $error->errorInfo[1]);
                }
            });
            $this->assertSame(0, DB::connection()->transactionLevel());
        } finally {
            DB::purge('mysql_contender');
        }
    }

    public function test_ready_command_keeps_new_installation_closed_until_operator_finishes(): void
    {
        $this->artisan('clbk:database-ready')->assertSuccessful();
        $this->assertTrue($this->app->isDownForMaintenance());
        User::factory()->manager()->create();
        $this->artisan('clbk:database-ready')->assertSuccessful();
        $this->assertTrue($this->app->isDownForMaintenance());
    }

    public function test_incomplete_schema_can_serve_maintenance_but_fails_the_strict_check(): void
    {
        DB::unprepared('DROP TRIGGER attachment_source_guard');
        $this->artisan('clbk:database-ready')->assertFailed();
        $this->assertTrue($this->app->isDownForMaintenance());
        $this->artisan('clbk:database-ready', ['--serve-maintenance' => true])->assertSuccessful();
        $this->assertTrue($this->app->isDownForMaintenance());
        $this->get('/')->assertStatus(503);
        $this->getJson('/dashboard')->assertStatus(503);
        $this->artisan('clbk:database-ready')->assertFailed();
    }

    public function test_initializer_recovers_an_empty_interrupted_baseline_import(): void
    {
        DB::table('migrations')->delete();
        DB::unprepared('DROP TRIGGER attachment_source_guard');
        $this->artisan('clbk:mysql-initialize')->assertSuccessful();
        $this->assertTrue(app(MySqlDatabase::class)->guardsPresent());
        $this->assertTrue(app(MySqlDatabase::class)->integrity());
        $this->assertDatabaseCount('migrations', count(glob(database_path('migrations/*.php'))));
        $this->assertDatabaseCount('users', 0);
        $this->assertTrue($this->app->isDownForMaintenance());
    }

    public function test_initializer_refuses_to_reload_a_baseline_over_existing_data(): void
    {
        $manager = User::factory()->manager()->create();
        DB::table('migrations')->delete();
        DB::unprepared('DROP TRIGGER attachment_source_guard');
        $this->artisan('clbk:mysql-initialize')->expectsOutputToContain('sudah berisi data')->assertFailed();
        $this->assertSame($manager->name, $manager->fresh()->name);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('migrations', 0);
        $this->assertFalse(app(MySqlDatabase::class)->guardsPresent());
        $this->assertTrue($this->app->isDownForMaintenance());
    }

    public function test_initializer_refuses_incomplete_schema_with_existing_migration_history(): void
    {
        $manager = User::factory()->manager()->create();
        DB::unprepared('DROP TRIGGER attachment_source_guard');
        $this->artisan('clbk:mysql-initialize')->expectsOutputToContain('Riwayat migrasi sudah terisi')->assertFailed();
        $this->assertSame($manager->name, $manager->fresh()->name);
        $this->assertDatabaseCount('migrations', count(glob(database_path('migrations/*.php'))));
        $this->assertFalse(app(MySqlDatabase::class)->guardsPresent());
    }

    public function test_initializer_leaves_an_initialized_database_unchanged(): void
    {
        $manager = User::factory()->manager()->create();
        $this->artisan('clbk:mysql-initialize')->assertSuccessful();
        $this->assertSame($manager->name, $manager->fresh()->name);
        $this->assertDatabaseCount('users', 1);
        $this->assertTrue(app(MySqlDatabase::class)->guardsPresent());
        $this->assertTrue($this->app->isDownForMaintenance());
    }
}

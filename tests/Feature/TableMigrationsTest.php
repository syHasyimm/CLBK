<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Database\LegacyMySqlSchema;
use App\Support\Database\MySqlDatabase;
use App\Support\Database\MySqlSchemaMetadata;
use Database\Seeders\BusinessDefaultsSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Concerns\DatabaseMigrations;
use Tests\TestCase;

class TableMigrationsTest extends TestCase
{
    use DatabaseMigrations;

    public function test_per_table_migrations_preserve_the_complete_previous_schema(): void
    {
        $this->assertSame(LegacyMySqlSchema::FINGERPRINT, app(MySqlSchemaMetadata::class)->fingerprint());
        $this->assertCount(41, app(MySqlDatabase::class)->tables()); // 40 application tables + migrations.
        $this->assertCount(85, app(MySqlDatabase::class)->foreignKeys());
        $this->assertTrue(app(MySqlDatabase::class)->guardsPresent());
        $this->assertSame(107, (int) DB::selectOne('SELECT COUNT(*) AS total FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()')->total);
        $this->assertSame(137, (int) DB::selectOne("SELECT COUNT(*) AS total FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND CONSTRAINT_TYPE='CHECK'")->total);
    }

    public function test_rerunning_migrate_preserves_existing_users_and_data(): void
    {
        $this->seed(BusinessDefaultsSeeder::class);
        $manager = User::factory()->manager()->create();
        $before = $manager->fresh()->getRawOriginal();
        $fingerprint = app(MySqlSchemaMetadata::class)->fingerprint();

        $this->artisan('migrate', ['--force' => true])->assertSuccessful();

        $this->assertTrue($before === $manager->fresh()->getRawOriginal(), 'The existing account must remain unchanged.');
        $this->assertSame($fingerprint, app(MySqlSchemaMetadata::class)->fingerprint());
        $this->assertDatabaseCount('migrations', count(glob(database_path('migrations/*.php'))));
    }

    public function test_existing_baseline_is_adopted_without_recreating_tables_or_changing_data(): void
    {
        $this->seed(BusinessDefaultsSeeder::class);
        $manager = User::factory()->manager()->create();
        $before = $manager->fresh()->getRawOriginal();
        $settings = DB::table('app_settings')->orderBy('key')->get()->toJson();
        $this->recordLegacyHistory();
        $ddl = [];
        DB::listen(function (QueryExecuted $query) use (&$ddl) {
            if (preg_match('/\A\s*(?:CREATE|ALTER|DROP|RENAME|TRUNCATE)\s/i', $query->sql)) {
                $ddl[] = $query->sql;
            }
        });

        $this->artisan('migrate', ['--force' => true])->assertSuccessful();

        $this->assertSame([], $ddl, 'Adopting the historical schema must not execute DDL.');
        $this->assertTrue($before === $manager->fresh()->getRawOriginal(), 'The existing account must remain unchanged.');
        $this->assertSame($settings, DB::table('app_settings')->orderBy('key')->get()->toJson());
        $this->assertDatabaseHas('migrations', ['migration' => LegacyMySqlSchema::MIGRATION, 'batch' => 1]);
        $this->assertDatabaseCount('migrations', count(glob(database_path('migrations/*.php'))) + 1);
        $this->assertSame(LegacyMySqlSchema::FINGERPRINT, app(MySqlSchemaMetadata::class)->fingerprint());
        $this->assertTrue(app(MySqlDatabase::class)->integrity());
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    }

    public function test_incomplete_legacy_schema_is_rejected_before_any_new_migration_is_recorded(): void
    {
        $manager = User::factory()->manager()->create();
        $this->recordLegacyHistory();
        DB::statement('ALTER TABLE users DROP FOREIGN KEY users_fk_0');

        try {
            $this->artisan('migrate', ['--force' => true])->run();
            $this->fail('Incomplete legacy schema was accepted.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Schema lama tidak cocok', $error->getMessage());
        }

        $this->assertDatabaseCount('migrations', 1);
        $this->assertSame($manager->name, $manager->fresh()->name);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_migrate_does_not_overwrite_untracked_existing_data(): void
    {
        $manager = User::factory()->manager()->create();
        DB::table('migrations')->delete();

        try {
            $this->artisan('migrate', ['--force' => true])->run();
            $this->fail('Existing untracked tables were overwritten.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Database sudah memiliki tabel', $error->getMessage());
        }

        $this->assertDatabaseCount('migrations', 0);
        $this->assertSame($manager->name, $manager->fresh()->name);
        $this->assertSame(LegacyMySqlSchema::FINGERPRINT, app(MySqlSchemaMetadata::class)->fingerprint());
    }

    public function test_empty_interrupted_foreign_key_and_trigger_migrations_can_resume(): void
    {
        DB::table('migrations')->where('migration', '>=', '2026_10_18_000100')->delete();
        DB::statement('ALTER TABLE users DROP FOREIGN KEY users_fk_0');
        DB::unprepared('DROP TRIGGER attachment_source_guard');

        $this->artisan('clbk:mysql-initialize')->assertSuccessful();

        $this->assertDatabaseCount('migrations', count(glob(database_path('migrations/*.php'))));
        $this->assertDatabaseCount('users', 0);
        $this->assertTrue($this->app->isDownForMaintenance());
        $this->assertTrue(app(MySqlDatabase::class)->integrity());
        $this->assertSame(LegacyMySqlSchema::FINGERPRINT, app(MySqlSchemaMetadata::class)->fingerprint());
    }

    public function test_initializer_does_not_resume_an_interrupted_installation_over_business_data(): void
    {
        $manager = User::factory()->manager()->create();
        DB::table('migrations')->where('migration', '>=', '2026_10_18_000100')->delete();
        DB::unprepared('DROP TRIGGER attachment_source_guard');
        $history = DB::table('migrations')->count();

        $this->artisan('clbk:mysql-initialize')->expectsOutputToContain('sudah berisi data')->assertFailed();

        $this->assertDatabaseCount('migrations', $history);
        $this->assertSame($manager->name, $manager->fresh()->name);
        $this->assertFalse(app(MySqlDatabase::class)->guardsPresent());
    }

    private function recordLegacyHistory(): void
    {
        DB::table('migrations')->delete();
        DB::table('migrations')->insert(['migration' => LegacyMySqlSchema::MIGRATION, 'batch' => 1]);
    }
}

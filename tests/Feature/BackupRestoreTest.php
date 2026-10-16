<?php

namespace Tests\Feature;

use App\Actions\Finance\ManualCash;
use App\Actions\Finance\PostFinance;
use App\Actions\Sales\SaveDirectSale;
use App\Actions\Weighings\CreateDraftWeighing;
use App\Actions\Weighings\PostWeighing;
use App\Actions\Weighings\SaveDirectWeighing;
use App\Enums\FinanceKind;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\Factory;
use App\Models\FactoryPriceSchedule;
use App\Models\LoadingWorker;
use App\Models\PriceSchedule;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Weighing;
use App\Services\BackupService;
use App\Services\BackupSnapshot;
use App\Support\BusinessClock;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Concerns\DatabaseMigrations;
use Tests\TestCase;
use ZipArchive;

class BackupRestoreTest extends TestCase
{
    use DatabaseMigrations;

    private string $directory;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/clbk-backup-tests-'.Str::uuid();
        File::ensureDirectoryExists($this->directory);
        config(['backups.path' => $this->directory.'/backups', 'backups.minimum_free_bytes' => 0, 'backups.require_separate_disk' => true,
            'filesystems.disks.local.root' => $this->directory.'/private',
            'app.maintenance.driver' => 'cache', 'app.maintenance.store' => 'array', 'session.driver' => 'database']);
        DB::purge();
        Storage::forgetDisk('local');
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->seed();
        $this->manager = User::factory()->manager()->create();
    }

    protected function tearDown(): void
    {
        $this->app->maintenanceMode()->deactivate();
        DB::purge();
        Storage::forgetDisk('local');
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    private function proof(): string
    {
        $time = app(BusinessClock::class)->local(app(BusinessClock::class)->now());
        app(ManualCash::class)->post($this->manager, ['kind' => 'opening', 'amount_idr' => 1000000,
            'occurred_at' => $time, 'reason' => 'Modal uji', 'idempotency_key' => (string) Str::uuid()]);
        $expense = app(PostFinance::class)->handle($this->manager, FinanceKind::Expense, [
            'expense_category_id' => ExpenseCategory::firstOrFail()->id, 'amount_idr' => 50000,
            'method' => 'cash', 'occurred_at' => $time, 'description' => 'Biaya uji', 'idempotency_key' => (string) Str::uuid()]);
        $path = 'finance-proofs/test-proof.pdf';
        $contents = "%PDF-1.4\nBackup proof\n%%EOF";
        Storage::disk('local')->put($path, $contents);
        DB::table('attachments')->insert(['entity_type' => 'expense', 'entity_id' => $expense->id,
            'storage_path' => $path, 'original_name' => 'bukti.pdf', 'mime_type' => 'application/pdf',
            'size_bytes' => strlen($contents), 'uploaded_by' => $this->manager->id, 'created_at' => now()]);

        return $path;
    }

    public function test_backup_includes_committed_mysql_data_and_private_proof(): void
    {
        $path = $this->proof();
        $service = app(BackupService::class);
        $backup = $service->create($this->manager);
        $manifest = $service->verify($service->path($backup['id']));
        $this->assertSame(950000, $manifest['state']['metrics']['cash_idr']);
        $this->assertSame(1, $manifest['state']['tables']['daily_expenses']['count']);
        $this->assertSame(hash('sha256', Storage::disk('local')->get($path)), $manifest['entries']['proofs/'.$path]['sha256']);
        $this->assertSame($backup['state'], $manifest['state']);
        $this->assertCount(1, $service->listing());
    }

    public function test_restore_requires_maintenance_and_preserves_current_data_when_rejected(): void
    {
        $service = app(BackupService::class);
        $backup = $service->create();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('maintenance');
        $service->restore($service->path($backup['id']));
    }

    public function test_restore_reconciles_proof_cash_and_documents_and_keeps_a_safety_backup(): void
    {
        $proof = $this->proof();
        DB::table('sessions')->insert(['id' => 'historical-session', 'payload' => 'payload', 'last_activity' => time(), 'user_id' => $this->manager->id]);
        $service = app(BackupService::class);
        $backup = $service->create();
        $name = $this->manager->name;
        $this->manager->update(['name' => 'Changed after backup']);
        Storage::disk('local')->delete($proof);
        // A missing current proof must block the safety backup; repair it before a real restore.
        $this->app->maintenanceMode()->activate(['time' => time()]);
        try {
            $service->restore($service->path($backup['id']));
            $this->fail('Missing proof in current state was not rejected.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Bukti', $error->getMessage());
        }
        $this->assertSame('Changed after backup', $this->manager->fresh()->name);
        Storage::disk('local')->put($proof, "%PDF-1.4\nBackup proof\n%%EOF");
        $result = $service->restore($service->path($backup['id']));
        $this->assertSame($backup['state'], $result['state']);
        $this->assertSame($name, User::findOrFail($this->manager->id)->name);
        $this->assertNotNull($result['safety_backup_id']);
        $this->assertFileExists($service->path($result['safety_backup_id']));
        $this->assertSame(0, DB::table('sessions')->count());
        $this->assertSame(0, DB::table('users')->whereNotNull('remember_token')->count());
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'backup-restored')->count());
        $this->assertTrue($this->app->isDownForMaintenance());
        Storage::disk('local')->assertExists($proof);
    }

    public function test_modified_archive_and_wrong_key_are_rejected_before_restore(): void
    {
        $this->proof();
        $service = app(BackupService::class);
        $backup = $service->create();
        $archive = $service->path($backup['id']);
        $zip = new ZipArchive;
        $zip->open($archive);
        $zip->addFromString('database.jsonl', 'tampered database');
        $zip->close();
        $this->app->maintenanceMode()->activate(['time' => time()]);
        try {
            $service->restore($archive);
            $this->fail('Tampered backup was accepted.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Checksum', $error->getMessage());
        }
        $this->assertSame(1, DB::table('daily_expenses')->count());
        $backup = $service->create();
        config(['app.key' => 'wrong-key']);
        $this->expectExceptionMessage('Tanda tangan');
        $service->verify($service->path($backup['id']));
    }

    public function test_manager_can_set_retention_but_other_roles_cannot_access_backups(): void
    {
        $this->actingAs($this->manager)->patch(route('settings.business.update'), [
            'business_name' => 'CLBK', 'receipt_prefix' => 'CLBK', 'backup_keep_daily' => 10, 'backup_keep_weekly' => 6,
        ])->assertSessionHasNoErrors();
        $this->assertSame(['daily' => 10, 'weekly' => 6], app(BackupService::class)->retention());
        foreach ([User::factory()->cashier()->create(), User::factory()->create()] as $user) {
            $this->actingAs($user)->post(route('backups.store'), ['current_password' => 'password'])->assertForbidden();
            $this->get(route('backups.download', ['backup' => 'clbk-20261016T000000Z-aaaaaaaaaaaa']))->assertForbidden();
        }
    }

    public function test_download_requires_password_confirmation_and_never_accepts_path_traversal(): void
    {
        $service = app(BackupService::class);
        $backup = $service->create();
        $url = route('backups.download', ['backup' => $backup['id']]);
        $this->actingAs($this->manager)->get($url)->assertRedirect(route('password.confirm'));
        $this->withSession(['auth.password_confirmed_at' => time()])->get($url)->assertDownload($backup['id'].'.zip');
        $this->expectExceptionMessage('ID cadangan');
        $service->path('../database');
    }

    public function test_command_verifies_without_modifying_the_live_database(): void
    {
        $backup = app(BackupService::class)->create();
        $before = app(BackupSnapshot::class)->inspect();
        $this->artisan('clbk:restore', ['archive' => app(BackupService::class)->path($backup['id']), '--verify-only' => true])->assertSuccessful();
        $this->assertSame($before, app(BackupSnapshot::class)->inspect());
    }

    public function test_restore_on_new_machine_restores_proofs_and_refuses_nonempty_database(): void
    {
        $proof = $this->proof();
        $service = app(BackupService::class);
        $backup = $service->create();
        $archive = $service->path($backup['id']);
        DB::purge();
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
        config(['filesystems.disks.local.root' => $this->directory.'/replacement-proofs']);
        Storage::forgetDisk('local');
        $this->app->maintenanceMode()->activate(['time' => time()]);
        $result = $service->restore($archive, true);
        $this->assertNull($result['safety_backup_id']);
        $this->assertSame($backup['state'], $result['state']);
        Storage::disk('local')->assertExists($proof);
        $this->expectExceptionMessage('database tujuan');
        $service->restore($archive, true);
    }

    public function test_scheduled_retention_keeps_daily_weekly_and_manual_safety_copies(): void
    {
        $service = app(BackupService::class);
        $files = [];
        for ($day = 0; $day < 35; $day++) {
            $backup = $service->create(reason: 'scheduled');
            $path = $service->path($backup['id']);
            touch($path, time() - $day * 86400);
            $files[$day] = $backup['id'];
        }
        $manual = $service->create(reason: 'pre-upgrade');
        touch($service->path($manual['id']), time() - 50 * 86400);
        $service->prune();
        $remaining = array_column($service->listing(), 'id');
        foreach (array_slice($files, 0, 7) as $id) {
            $this->assertContains($id, $remaining);
        }
        $this->assertContains($manual['id'], $remaining);
        $this->assertLessThanOrEqual(12, count($remaining));
        $this->assertGreaterThanOrEqual(8, count($remaining));
    }

    public function test_nonzero_debts_receivables_price_snapshots_and_access_survive_recovery(): void
    {
        $this->proof();
        $clock = app(BusinessClock::class);
        $vehicle = Vehicle::create(['code' => 'REC-M', 'plate_number' => 'BK REC 6', 'is_active' => true]);
        $driver = User::factory()->create(['assigned_vehicle_id' => $vehicle->id]);
        $customer = Customer::create(['code' => 'REC-K', 'name' => 'Konsumen pemulihan', 'is_active' => true]);
        $worker = LoadingWorker::create(['code' => 'REC-A', 'name' => 'Anggota pemulihan', 'is_active' => true]);
        $factory = Factory::create(['code' => 'REC-P', 'name' => 'Pabrik pemulihan', 'is_active' => true]);
        $price = PriceSchedule::create(['business_date' => $clock->date($clock->now()), 'effective_from' => $clock->now()->subHour(),
            'tbs_buy_price_idr' => 3000, 'sort_buy_price_idr' => 2000, 'loading_rate_idr' => 100, 'published_by' => $this->manager->id]);
        $factoryPrice = FactoryPriceSchedule::create(['factory_id' => $factory->id, 'effective_from' => $clock->now()->subHour(),
            'tbs_sell_price_idr' => 3500, 'published_by' => $this->manager->id]);
        $key = fn ($data) => $data + ['idempotency_key' => (string) Str::uuid()];
        $ticket = app(SaveDirectWeighing::class)->handle($driver, $key([
            'customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'vehicle_assignment_version' => 1,
            'tbs_kg' => '1000', 'sort_kg' => '100', 'worker_ids' => [$worker->id], 'price_schedule_id' => $price->id]));
        app(SaveDirectSale::class)->handle($driver, 'factory', $key(['vehicle_id' => $vehicle->id,
            'vehicle_assignment_version' => 1, 'factory_id' => $factory->id, 'factory_price_schedule_id' => $factoryPrice->id,
            'accepted_kg' => '900', 'deduction_idr' => 0]));
        $service = app(BackupService::class);
        $backup = $service->create();
        $this->assertSame(['cash_idr' => 950000, 'customer_debt_idr' => 3200000, 'payroll_debt_idr' => 110000,
            'receivable_idr' => 3150000, 'historical_stock_grams' => 0], $backup['state']['metrics']);
        $this->app->maintenanceMode()->activate(['time' => time()]);
        $started = microtime(true);
        $result = $service->restore($service->path($backup['id']));
        $this->assertLessThan(7200, microtime(true) - $started);
        $this->assertSame($backup['state'], $result['state']);
        $this->assertSame(3200000, Weighing::findOrFail($ticket->id)->total_purchase_idr);
        $this->app->maintenanceMode()->deactivate();
        $proofId = DB::table('attachments')->value('id');
        $this->actingAs(User::findOrFail($driver->id))->get(route('finance-proofs.download', $proofId))->assertForbidden();
        $this->actingAs(User::findOrFail($this->manager->id))->get(route('finance-proofs.download', $proofId))->assertDownload('bukti.pdf');
        $this->get('/up')->assertOk();
    }

    public function test_manual_backup_requires_the_managers_current_password_and_is_audited(): void
    {
        $this->actingAs($this->manager)->post(route('backups.store'), ['current_password' => 'incorrect'])
            ->assertSessionHasErrors('current_password');
        $this->assertSame([], app(BackupService::class)->listing());
        $this->post(route('backups.store'), ['current_password' => 'password'])->assertSessionHasNoErrors();
        $this->assertCount(1, app(BackupService::class)->listing());
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'backup-created')->where('actor_id', $this->manager->id)->count());
    }

    public function test_nonzero_historical_stock_and_its_cost_are_preserved_on_a_replacement_database(): void
    {
        $clock = app(BusinessClock::class);
        $vehicle = Vehicle::create(['code' => 'OLD-M', 'plate_number' => 'BK OLD 6', 'is_active' => true]);
        $driver = User::factory()->create();
        $customer = Customer::create(['code' => 'OLD-K', 'name' => 'Histori', 'is_active' => true]);
        $worker = LoadingWorker::create(['code' => 'OLD-A', 'name' => 'Histori', 'is_active' => true]);
        $trip = Trip::create(['number' => 'OLD-TRIP', 'vehicle_id' => $vehicle->id, 'driver_id' => $driver->id,
            'business_date' => $clock->date($clock->now()), 'status' => 'loading', 'created_by' => $this->manager->id]);
        $price = PriceSchedule::create(['business_date' => $clock->date($clock->now()), 'effective_from' => $clock->now()->subHour(),
            'tbs_buy_price_idr' => 3000, 'sort_buy_price_idr' => 2000, 'loading_rate_idr' => 100, 'published_by' => $this->manager->id]);
        $ticket = app(CreateDraftWeighing::class)->handle($this->manager, [
            'trip_id' => $trip->id, 'customer_id' => $customer->id, 'tbs_kg' => '0', 'sort_kg' => '10',
            'worker_ids' => [$worker->id], 'weighed_at' => $clock->local($clock->now())])->fresh();
        app(PostWeighing::class)->handle($this->manager, $ticket, [
            'version' => $ticket->version, 'price_schedule_id' => $price->id, 'idempotency_key' => (string) Str::uuid()]);
        $service = app(BackupService::class);
        $backup = $service->create();
        $archive = $service->path($backup['id']);
        DB::purge();
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
        $this->app->maintenanceMode()->activate(['time' => time()]);
        $restored = $service->restore($archive, true);
        $this->assertSame(10000, $restored['state']['metrics']['historical_stock_grams']);
        $this->assertSame(20000, (int) DB::table('sort_stock_movements')->sum('purchase_cost_delta_idr'));
        $this->assertSame(1000, (int) DB::table('sort_stock_movements')->sum('loading_cost_delta_idr'));
        $this->assertSame($backup['state'], $restored['state']);
    }

    public function test_backup_rejects_public_directories_and_same_volume_in_production(): void
    {
        $this->app['env'] = 'production';
        config(['backups.require_separate_disk' => false]);
        $this->app->usePublicPath($this->directory.'/public');
        File::ensureDirectoryExists(public_path());
        foreach ([public_path('backups'), $this->directory.'/public-disk/backups'] as $path) {
            File::ensureDirectoryExists($this->directory.'/public-disk');
            config(['backups.path' => $path, 'filesystems.disks.public.root' => $this->directory.'/public-disk']);
            try {
                app(BackupService::class)->listing();
                $this->fail('Public backup path accepted.');
            } catch (RuntimeException $error) {
                $this->assertStringContainsString('publik', $error->getMessage());
            }
        }
        config(['backups.path' => $this->directory.'/backups', 'backups.require_separate_disk' => true]);
        $this->expectExceptionMessage('volume berbeda');
        app(BackupService::class)->listing();
    }

    public function test_local_folder_backup_can_be_verified_and_restored_in_production(): void
    {
        $this->app['env'] = 'production';
        config(['backups.require_separate_disk' => false]);
        $proof = $this->proof();
        $service = app(BackupService::class);
        $backup = $service->create();
        $archive = $service->path($backup['id']);
        $this->assertSame($backup['state'], $service->verify($archive)['state']);
        $this->assertSame($backup['id'], $service->listing()[0]['id']);
        $name = $this->manager->name;
        $this->manager->update(['name' => 'Changed after local backup']);
        $this->app->maintenanceMode()->activate(['time' => time()]);
        $restored = $service->restore($archive);
        $this->assertSame($backup['state'], $restored['state']);
        $this->assertSame($name, $this->manager->fresh()->name);
        $this->assertNotNull($restored['safety_backup_id']);
        $this->assertFileExists($service->path($restored['safety_backup_id']));
        Storage::disk('local')->assertExists($proof);
        $this->assertTrue($this->app->isDownForMaintenance());
    }

    public function test_local_backup_folder_must_not_overlap_private_storage(): void
    {
        $this->app['env'] = 'production';
        config(['backups.require_separate_disk' => false]);
        foreach ([$this->directory.'/private', $this->directory.'/private/backups', $this->directory] as $path) {
            config(['backups.path' => $path]);
            try {
                app(BackupService::class)->listing();
                $this->fail('Backup folder overlaps private storage.');
            } catch (RuntimeException $error) {
                $this->assertStringContainsString('Direktori cadangan harus terpisah', $error->getMessage());
            }
        }
    }

    public function test_signed_path_traversal_and_mismatched_migration_release_are_rejected(): void
    {
        $service = app(BackupService::class);
        $backup = $service->create();
        $archive = $service->path($backup['id']);
        $this->rewriteManifest($archive, function ($manifest, $zip) {
            $entry = 'proofs/finance-proofs/../../escaped.txt';
            $zip->addFromString($entry, 'escaped');
            $manifest['entries'][$entry] = ['bytes' => 7, 'sha256' => hash('sha256', 'escaped')];

            return $manifest;
        });
        try {
            $service->verify($archive);
            $this->fail('Traversal entry accepted.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Lokasi bukti', $error->getMessage());
        }
        $this->assertFileDoesNotExist($this->directory.'/backups/escaped.txt');
        $backup = $service->create();
        $archive = $service->path($backup['id']);
        $this->rewriteManifest($archive, function ($manifest) {
            $manifest['migration_fingerprint'] = str_repeat('0', 64);

            return $manifest;
        });
        $before = app(BackupSnapshot::class)->inspect();
        $this->app->maintenanceMode()->activate(['time' => time()]);
        try {
            $service->restore($archive);
            $this->fail('Different release accepted.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Versi migrasi', $error->getMessage());
        }
        $this->assertSame($before, app(BackupSnapshot::class)->inspect());
    }

    public function test_doctor_reports_missing_and_corrupt_backups_as_machine_readable_json(): void
    {
        config(['backups.heartbeat_path' => $this->directory.'/heartbeat']);
        $this->assertSame(1, Artisan::call('clbk:doctor', ['--json' => true]));
        $missing = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertFalse($missing['healthy']);
        $this->assertFalse($missing['checks']['backup_within_24_hours']);
        $service = app(BackupService::class);
        $backup = $service->create();
        file_put_contents($this->directory.'/heartbeat', gmdate('c'));
        $this->assertSame(0, Artisan::call('clbk:doctor', ['--json' => true]));
        $this->assertTrue(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['healthy']);
        file_put_contents($service->path($backup['id']), 'corrupt archive');
        $this->assertSame(1, Artisan::call('clbk:doctor', ['--json' => true]));
        $corrupt = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertFalse($corrupt['healthy']);
        $this->assertFalse($corrupt['checks']['backup_integrity']);
    }

    private function rewriteManifest(string $archive, callable $change): void
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($archive));
        $manifest = json_decode($zip->getFromName('manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        $json = json_encode($change($manifest, $zip), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $zip->addFromString('manifest.json', $json);
        $zip->addFromString('manifest.hmac', hash_hmac('sha256', $json, config('app.key')));
        $this->assertTrue($zip->close());
    }
}

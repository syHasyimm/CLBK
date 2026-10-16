<?php

namespace Tests\Feature;

use App\Actions\Finance\ManualCash;
use App\Actions\Finance\PostFinance;
use App\Enums\FinanceKind;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\BackupService;
use App\Services\BackupSnapshot;
use App\Services\MySqlBackup;
use App\Support\Database\MySqlDatabase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PDO;
use PDOException;
use Tests\Concerns\DatabaseMigrations;
use Tests\TestCase;

class MySqlBackupTest extends TestCase
{
    use DatabaseMigrations;

    protected function beforeRefreshingDatabase(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL integration suite only.');
        }
    }

    public function test_signed_mysql_backup_restores_cash_users_and_security_guards(): void
    {
        $directory = sys_get_temp_dir().'/clbk-mysql-backup-'.Str::uuid();
        config(['backups.path' => $directory, 'backups.minimum_free_bytes' => 0, 'backups.require_separate_disk' => false, 'session.driver' => 'database']);
        Storage::fake('local');
        config(['filesystems.disks.local.root' => Storage::disk('local')->path('')]);
        $this->seed();
        $manager = User::factory()->manager()->create(['name' => 'Before backup', 'remember_token' => 'old-token']);
        $deletedDraftUser = User::factory()->create();
        $deletedId = $deletedDraftUser->id;
        $deletedDraftUser->delete();
        app(ManualCash::class)->post($manager, ['kind' => 'opening', 'amount_idr' => 500000, 'occurred_at' => now()->timezone('Asia/Jakarta')->format('Y-m-d\TH:i'), 'reason' => 'MySQL backup test', 'idempotency_key' => (string) Str::uuid()]);
        $description = str_repeat('x', 1000);
        $expense = app(PostFinance::class)->handle($manager, FinanceKind::Expense, [
            'expense_category_id' => ExpenseCategory::firstOrFail()->id, 'amount_idr' => 50000,
            'method' => 'cash', 'occurred_at' => now()->timezone('Asia/Jakarta')->format('Y-m-d\TH:i'),
            'description' => $description, 'idempotency_key' => (string) Str::uuid(),
        ]);
        $proofPath = 'finance-proofs/mysql-proof.pdf';
        $proof = "%PDF-1.4\nMySQL backup proof\n%%EOF";
        Storage::disk('local')->put($proofPath, $proof);
        DB::table('attachments')->insert(['entity_type' => 'expense', 'entity_id' => $expense->id,
            'storage_path' => $proofPath, 'original_name' => 'bukti.pdf', 'mime_type' => 'application/pdf',
            'size_bytes' => strlen($proof), 'uploaded_by' => $manager->id, 'created_at' => now()]);
        DB::table('sessions')->insert(['id' => 'old-session', 'user_id' => $manager->id, 'payload' => 'old', 'last_activity' => time()]);
        $this->app['env'] = 'production';
        $service = app(BackupService::class);
        try {
            $backup = $service->create();
            $manifest = $service->verify($service->path($backup['id']));
            $this->assertSame('mysql', $manifest['database_driver']);
            $this->assertSame(450000, $manifest['state']['metrics']['cash_idr']);
            $manager->update(['name' => 'After backup']);
            Storage::disk('local')->put($proofPath, str_repeat('x', strlen($proof)));
            $this->app->maintenanceMode()->activate(['time' => time()]);
            $result = $service->restore($service->path($backup['id']));
            $this->assertSame($backup['state'], $result['state']);
            $this->assertNotNull($result['safety_backup_id']);
            $this->assertSame('Before backup', $manager->fresh()->name);
            $this->assertSame($description, $expense->fresh()->description);
            $this->assertSame($proof, Storage::disk('local')->get($proofPath));
            $this->assertDatabaseCount('sessions', 0);
            $this->assertNull($manager->fresh()->remember_token);
            $this->assertTrue(app(MySqlDatabase::class)->guardsPresent());
            $this->assertTrue(app(MySqlDatabase::class)->integrity());
            $this->assertTrue($this->app->isDownForMaintenance());
            $this->assertGreaterThan($deletedId, User::factory()->create()->id);
            $this->expectException(QueryException::class);
            DB::table('audit_logs')->delete();
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_failed_mysql_restore_rolls_back_data_and_reinstalls_all_guards(): void
    {
        $directory = sys_get_temp_dir().'/clbk-mysql-rollback-'.Str::uuid();
        File::ensureDirectoryExists($directory);
        $this->seed();
        $manager = User::factory()->manager()->create(['name' => 'Keep current user']);
        $customer = Customer::create(['code' => 'SAFE', 'name' => 'Keep current customer', 'is_active' => true]);
        $backup = app(MySqlBackup::class);
        try {
            $backup->export($directory.'/invalid.sqlite');
            $pdo = new PDO('sqlite:'.$directory.'/invalid.sqlite');
            $pdo->exec('UPDATE customers SET is_active=2');
            $pdo = null;
            $state = app(BackupSnapshot::class)->inspect($directory.'/invalid.sqlite');
            $this->app->maintenanceMode()->activate(['time' => time()]);
            try {
                $backup->restore($directory.'/invalid.sqlite', $state);
                $this->fail('MySQL CHECK violation was accepted during restore.');
            } catch (PDOException $error) {
                $this->assertSame(3819, (int) $error->errorInfo[1]);
            }
            $this->assertSame('Keep current user', $manager->fresh()->name);
            $this->assertTrue($customer->fresh()->is_active);
            $this->assertTrue(app(MySqlDatabase::class)->guardsPresent());
            $this->assertTrue(app(MySqlDatabase::class)->integrity());
            $this->assertFalse(DB::connection()->getPdo()->inTransaction());
            $this->assertSame(1, (int) DB::selectOne('SELECT @@FOREIGN_KEY_CHECKS AS enabled')->enabled);
            $this->assertTrue($this->app->isDownForMaintenance());
        } finally {
            File::deleteDirectory($directory);
        }
    }
}

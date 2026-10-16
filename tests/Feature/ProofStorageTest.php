<?php

namespace Tests\Feature;

use App\Actions\Finance\ManualCash;
use App\Actions\Finance\PostFinance;
use App\Actions\Sales\SaveSale;
use App\Actions\Trips\OpenTrip;
use App\Enums\FinanceKind;
use App\Models\CashAccount;
use App\Models\DailyExpense;
use App\Models\ExpenseCategory;
use App\Models\SortSale;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\AuditService;
use App\Support\BusinessClock;
use App\Support\Database\WriteTransaction;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use PDOException;
use Symfony\Component\Process\Process;
use Tests\Concerns\DatabaseMigrations;
use Tests\TestCase;

class ProofStorageTest extends TestCase
{
    use DatabaseMigrations;

    private const PDF = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF";

    private User $manager;

    private User $cashier;

    private User $driver;

    private FilesystemAdapter $disk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->startOfSecond());
        $this->disk = Storage::fake('proof-quota-tests-'.getmypid());
        Storage::set('local', $this->disk);
        $this->seed();
        $this->manager = User::factory()->manager()->create();
        $this->cashier = User::factory()->cashier()->create();
        $this->driver = User::factory()->create();
        app(ManualCash::class)->post($this->manager, [
            'kind' => 'opening', 'amount_idr' => 1000, 'reason' => 'Modal pengujian',
            'occurred_at' => $this->localNow(), 'idempotency_key' => (string) Str::uuid(),
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->disk)) {
            $this->disk->delete($this->disk->allFiles());
        }
        parent::tearDown();
    }

    private function localNow(): string
    {
        return app(BusinessClock::class)->local(app(BusinessClock::class)->now());
    }

    private function expense(): DailyExpense
    {
        return app(PostFinance::class)->handle($this->cashier, FinanceKind::Expense, [
            'expense_category_id' => ExpenseCategory::firstOrFail()->id,
            'cash_account_id' => CashAccount::firstOrFail()->id, 'amount_idr' => 1,
            'description' => 'Biaya pengujian', 'method' => 'cash', 'occurred_at' => $this->localNow(),
            'idempotency_key' => (string) Str::uuid(),
        ]);
    }

    private function legacySale(): SortSale
    {
        $vehicle = Vehicle::create(['code' => 'M-PROOF', 'plate_number' => 'BK PROOF', 'is_active' => true]);
        $trip = app(OpenTrip::class)->handle($this->manager, ['vehicle_id' => $vehicle->id, 'driver_id' => $this->driver->id]);

        return app(SaveSale::class)->handle($this->driver, 'sort', [
            'trip_id' => $trip->id, 'sold_kg' => '1', 'buyer_name' => 'Pembeli uji',
            'sell_price_snapshot_idr' => 100, 'deduction_idr' => 0,
        ]);
    }

    private function pdf(string $name = 'bukti.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, self::PDF);
    }

    private function url(DailyExpense $expense): string
    {
        return route('finance.proofs', ['kind' => 'expense', 'document' => $expense->id]);
    }

    public function test_document_limit_rejects_the_sixth_upload_without_creating_another_file(): void
    {
        $expense = $this->expense();
        $this->actingAs($this->cashier);
        for ($i = 0; $i < 5; $i++) {
            $this->post($this->url($expense), ['proof' => $this->pdf()])->assertSessionHasNoErrors();
        }
        $this->post($this->url($expense), ['proof' => $this->pdf()])->assertSessionHasErrors('proof');
        $this->assertDatabaseCount('attachments', 5);
        $this->assertCount(5, $this->disk->allFiles());
        $this->assertSame(5, DB::table('audit_logs')->where('action', 'attach-finance-proof')->count());
    }

    public function test_user_quota_accepts_exact_boundary_and_applies_across_documents(): void
    {
        $first = $this->expense();
        $second = $this->expense();
        config(['proofs.max_bytes_per_user' => 2 * strlen(self::PDF)]);
        $this->actingAs($this->cashier)->post($this->url($first), ['proof' => $this->pdf()])->assertSessionHasNoErrors();
        $this->post($this->url($second), ['proof' => $this->pdf()])->assertSessionHasNoErrors();
        $this->post($this->url($second), ['proof' => $this->pdf()])->assertSessionHasErrors('proof');
        $this->assertSame(2 * strlen(self::PDF), (int) DB::table('attachments')->where('uploaded_by', $this->cashier->id)->sum('size_bytes'));
        $this->actingAs($this->manager)->post($this->url($second), ['proof' => $this->pdf()])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('attachments', 3);
        $this->assertCount(3, $this->disk->allFiles());
    }

    public function test_global_quota_counts_uploads_from_different_users_and_documents(): void
    {
        $first = $this->expense();
        $second = $this->expense();
        config(['proofs.max_bytes_total' => 2 * strlen(self::PDF)]);
        $this->actingAs($this->cashier)->post($this->url($first), ['proof' => $this->pdf()])->assertSessionHasNoErrors();
        $this->actingAs($this->manager)->post($this->url($second), ['proof' => $this->pdf()])->assertSessionHasNoErrors();
        $other = User::factory()->cashier()->create();
        $this->actingAs($other)->post($this->url($first), ['proof' => $this->pdf()])->assertSessionHasErrors('proof');
        $this->assertDatabaseCount('attachments', 2);
        $this->assertCount(2, $this->disk->allFiles());
    }

    public function test_sale_and_finance_uploads_share_global_capacity_and_keep_ownership_checks(): void
    {
        $sale = $this->legacySale();
        $expense = $this->expense();
        $saleUrl = route('sort-sales.proofs', ['sale' => $sale->id]);
        config(['proofs.max_bytes_total' => strlen(self::PDF)]);
        $otherDriver = User::factory()->create();
        $this->actingAs($otherDriver)->post($saleUrl, ['proof' => $this->pdf()])->assertForbidden();
        $this->actingAs($this->cashier)->post($saleUrl, ['proof' => $this->pdf()])->assertForbidden();
        $this->actingAs($this->driver)->post($saleUrl, ['proof' => $this->pdf()])->assertSessionHasNoErrors();
        $this->actingAs($this->cashier)->post($this->url($expense), ['proof' => $this->pdf()])->assertSessionHasErrors('proof');
        $this->assertDatabaseCount('attachments', 1);
        $this->assertCount(1, $this->disk->allFiles('sale-proofs'));
        $this->assertSame([], $this->disk->allFiles('finance-proofs'));
    }

    public function test_quota_is_rechecked_when_another_upload_commits_after_preflight(): void
    {
        $expense = $this->expense();
        config(['proofs.max_per_document' => 1]);
        $transactions = app(WriteTransaction::class);
        $this->mock(WriteTransaction::class)->shouldReceive('run')->once()->andReturnUsing(function ($callback) use ($expense, $transactions) {
            $this->disk->put('finance-proofs/competing.pdf', self::PDF);
            DB::table('attachments')->insert([
                'entity_type' => 'expense', 'entity_id' => $expense->id, 'storage_path' => 'finance-proofs/competing.pdf',
                'original_name' => 'bukti.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => strlen(self::PDF),
                'uploaded_by' => $this->cashier->id, 'created_at' => now(),
            ]);

            return $transactions->run($callback);
        });
        $this->actingAs($this->cashier)->post($this->url($expense), ['proof' => $this->pdf()])->assertSessionHasErrors('proof');
        $this->assertDatabaseCount('attachments', 1);
        $this->assertSame(['finance-proofs/competing.pdf'], $this->disk->allFiles());
    }

    public function test_fresh_role_is_checked_before_any_file_is_written(): void
    {
        $expense = $this->expense();
        $transactions = app(WriteTransaction::class);
        $this->mock(WriteTransaction::class)->shouldReceive('run')->once()->andReturnUsing(function ($callback) use ($transactions) {
            DB::table('users')->where('id', $this->cashier->id)->update(['role' => 'driver']);

            return $transactions->run($callback);
        });
        $this->actingAs($this->cashier)->post($this->url($expense), ['proof' => $this->pdf()])->assertForbidden();
        $this->assertDatabaseCount('attachments', 0);
        $this->assertSame([], $this->disk->allFiles());
    }

    public function test_audit_failure_rolls_back_metadata_and_cleans_the_private_file(): void
    {
        $expense = $this->expense();
        $this->mock(AuditService::class)->shouldReceive('record')->once()->andThrow(new \RuntimeException('proof audit failed'));
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($this->cashier)->post($this->url($expense), ['proof' => $this->pdf()]);
            $this->fail('Expected audit failure.');
        } catch (\RuntimeException $error) {
            $this->assertSame('proof audit failed', $error->getMessage());
        }
        $this->assertDatabaseCount('attachments', 0);
        $this->assertSame([], $this->disk->allFiles());
    }

    public function test_partial_failed_file_write_is_removed_without_metadata(): void
    {
        $expense = $this->expense();
        $disk = $this->disk;
        $adapter = Mockery::mock($disk);
        $adapter->shouldReceive('putFileAs')->once()->andReturnUsing(function ($directory, $file, $name) use ($disk) {
            $disk->put($directory.'/'.$name, 'partial');

            return false;
        });
        Storage::set('local', $adapter);
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($this->cashier)->post($this->url($expense), ['proof' => $this->pdf()]);
            $this->fail('Expected failed file write.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Bukti tidak dapat disimpan.', $error->getMessage());
        }
        $this->assertDatabaseCount('attachments', 0);
        $this->assertSame([], $disk->allFiles());
    }

    public function test_transaction_retry_reuses_one_file_and_does_not_consume_quota_twice(): void
    {
        $expense = $this->expense();
        config(['proofs.max_per_document' => 1, 'proofs.max_bytes_total' => strlen(self::PDF)]);
        $busy = new PDOException('Lock wait timeout exceeded');
        $busy->errorInfo = ['HY000', 1205, 'Lock wait timeout exceeded'];
        $this->mock(AuditService::class)->shouldReceive('record')->twice()->andReturnUsing(function () use (&$busy) {
            if ($busy !== null) {
                $error = $busy;
                $busy = null;
                throw $error;
            }
        });
        $disk = $this->disk;
        $adapter = Mockery::mock($disk);
        $adapter->shouldReceive('putFileAs')->once()->andReturnUsing(fn (...$arguments) => $disk->putFileAs(...$arguments));
        Storage::set('local', $adapter);
        $this->actingAs($this->cashier)->post($this->url($expense), ['proof' => $this->pdf()])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('attachments', 1);
        $this->assertCount(1, $disk->allFiles());
        $disk->assertExists(DB::table('attachments')->value('storage_path'));
    }

    public function test_later_after_commit_failure_does_not_delete_an_already_committed_proof(): void
    {
        $expense = $this->expense();
        $this->mock(AuditService::class)->shouldReceive('record')->once()->andReturnUsing(function () {
            DB::afterCommit(fn () => throw new \RuntimeException('later callback failed'));
        });
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($this->cashier)->post($this->url($expense), ['proof' => $this->pdf()]);
            $this->fail('Expected callback failure.');
        } catch (\RuntimeException $error) {
            $this->assertSame('later callback failed', $error->getMessage());
        }
        $this->assertDatabaseCount('attachments', 1);
        $this->assertCount(1, $this->disk->allFiles());
        $this->disk->assertExists(DB::table('attachments')->value('storage_path'));
    }

    public function test_two_upload_processes_cannot_exceed_the_global_byte_quota(): void
    {
        $expense = $this->expense();
        $uploadFile = tempnam(sys_get_temp_dir(), 'clbk-proof-file-');
        $processes = [];
        try {
            file_put_contents($uploadFile, self::PDF);
            foreach ([$this->cashier, $this->manager] as $actor) {
                $processes[] = new Process([
                    PHP_BINARY, base_path('tests/Support/store-proof-process.php'), (string) $actor->id,
                    (string) $expense->id, $uploadFile, $this->disk->path('concurrent'), (string) strlen(self::PDF),
                ], base_path(), $this->databaseEnvironment());
            }
            foreach ($processes as $process) {
                $process->start();
            }
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            }
            $outputs = array_map(fn ($process) => json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR), $processes);
            $this->assertCount(1, array_filter($outputs, fn ($output) => isset($output['stored'])));
            $this->assertCount(1, array_filter($outputs, fn ($output) => isset($output['errors']['proof'])));
            $pdo = DB::connection()->getPdo();
            $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM attachments')->fetchColumn());
            $this->assertSame(strlen(self::PDF), (int) $pdo->query('SELECT SUM(size_bytes) FROM attachments')->fetchColumn());
            $this->assertForeignKeysAreValid();
            $pdo = null;
            $this->assertCount(1, $this->disk->allFiles('concurrent'));
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            if (is_file($uploadFile)) {
                unlink($uploadFile);
            }
            $this->disk->delete($this->disk->allFiles('concurrent'));
        }
    }
}

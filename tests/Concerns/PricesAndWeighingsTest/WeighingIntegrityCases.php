<?php

namespace Tests\Concerns\PricesAndWeighingsTest;

use App\Actions\Weighings\PostWeighing;
use App\Services\AuditService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

trait WeighingIntegrityCases
{
    public function test_audit_failure_rolls_back_posting_receipt_snapshots_and_idempotency(): void
    {
        $record = $this->draft();
        $this->mock(AuditService::class, function ($mock) {
            $mock->shouldReceive('snapshot')->andReturn([]);
            $mock->shouldReceive('record')->andThrow(new \RuntimeException('audit failure'));
        });
        try {
            app(PostWeighing::class)->handle($this->manager, $record, $this->posting($record));
            $this->fail('Expected rollback');
        } catch (\RuntimeException $exception) {
            $this->assertSame('audit failure', $exception->getMessage());
        }
        $this->assertSame('draft', $record->fresh()->status);
        $this->assertNull($record->items()->first()->purchase_amount_idr);
        $this->assertDatabaseCount('sort_lots', 0);
        $this->assertDatabaseCount('sort_stock_movements', 0);
        $this->assertDatabaseCount('idempotency_requests', 0);
    }

    public function test_prices_posted_details_and_ledger_are_immutable_in_database(): void
    {
        $record = $this->postTicket($this->draft());
        foreach ([fn () => $this->price->update(['tbs_buy_price_idr' => 1]), fn () => $record->update(['total_purchase_idr' => 1]),
            fn () => $record->items()->update(['weight_grams' => 1]), fn () => $record->loaders()->delete(),
            fn () => DB::table('sort_stock_movements')->delete(), fn () => $record->delete()] as $attempt) {
            try {
                $attempt();
                $this->fail('Expected database constraint');
            } catch (QueryException $exception) {
                $this->assertStringContainsString('19', $exception->getMessage());
            }
        }
        $this->assertSame(3200000, $record->fresh()->total_purchase_idr);
    }

    public function test_two_independent_processes_posting_the_same_key_create_one_ticket_receipt(): void
    {
        $record = $this->draft();
        $path = tempnam(sys_get_temp_dir(), 'clbk-post-');
        unlink($path);
        DB::statement('VACUUM INTO ?', [$path]);
        // VACUUM INTO menghasilkan mode DELETE; samakan dengan DB aplikasi yang sudah dimigrasi sebelum membuka dua koneksi.
        $prepared = new \PDO('sqlite:'.$path);
        $this->assertSame('wal', $prepared->query('PRAGMA journal_mode = WAL')->fetchColumn());
        $prepared = null;
        $key = (string) Str::uuid();
        $command = [PHP_BINARY, base_path('tests/Support/post-weighing-process.php'), (string) $this->manager->id,
            (string) $record->id, (string) $record->version, (string) $this->price->id, $key];
        $environment = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $path];
        $first = new Process($command, base_path(), $environment);
        $second = new Process($command, base_path(), $environment);
        try {
            $first->start();
            $second->start();
            $first->wait();
            $second->wait();
            $this->assertTrue($first->isSuccessful(), $first->getErrorOutput());
            $this->assertTrue($second->isSuccessful(), $second->getErrorOutput());
            $this->assertSame(['id' => $record->id, 'status' => 'posted'], json_decode($first->getOutput(), true, flags: JSON_THROW_ON_ERROR));
            $this->assertSame($first->getOutput(), $second->getOutput());
            $database = new \PDO('sqlite:'.$path);
            $this->assertSame(1, (int) $database->query('SELECT COUNT(*) FROM sort_lots')->fetchColumn());
            $this->assertSame(1, (int) $database->query('SELECT COUNT(*) FROM sort_stock_movements')->fetchColumn());
            $this->assertSame(1, (int) $database->query('SELECT COUNT(*) FROM idempotency_requests')->fetchColumn());
            $this->assertSame(110000, (int) $database->query('SELECT SUM(share_amount_idr) FROM weighing_loaders')->fetchColumn());
            $database = null;
        } finally {
            foreach (['', '-wal', '-shm'] as $suffix) {
                if (is_file($path.$suffix)) {
                    unlink($path.$suffix);
                }
            }
        }
    }

    public function test_inconsistent_snapshot_allocations_and_fractional_integer_columns_are_rejected(): void
    {
        $record = $this->draft();
        try {
            DB::table('weighing_items')->where('weighing_id', $record->id)->update(['weight_grams' => 1.5]);
            $this->fail('Fractional gram must fail');
        } catch (QueryException $error) {
            $this->assertStringContainsString('CHECK constraint failed', $error->getMessage());
        }
        try {
            $record->update(['status' => 'posted', 'price_schedule_id' => $this->price->id, 'loading_rate_snapshot_idr' => 100,
                'loading_basis_grams' => 1100000, 'total_purchase_idr' => 3200000, 'total_loading_wage_idr' => 110000,
                'posted_at' => now(), 'posted_by' => $this->manager->id]);
            $this->fail('Incomplete allocation must fail');
        } catch (QueryException $error) {
            $this->assertStringContainsString('allocations do not reconcile', $error->getMessage());
        }
        $this->assertSame('draft', $record->fresh()->status);
    }
}

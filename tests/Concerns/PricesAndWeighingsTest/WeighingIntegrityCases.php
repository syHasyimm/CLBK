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
                $this->assertSame(1644, (int) $exception->errorInfo[1]);
            }
        }
        $this->assertSame(3200000, $record->fresh()->total_purchase_idr);
    }

    public function test_two_independent_processes_posting_the_same_key_create_one_ticket_receipt(): void
    {
        $record = $this->draft();
        $key = (string) Str::uuid();
        $command = [PHP_BINARY, base_path('tests/Support/post-weighing-process.php'), (string) $this->manager->id,
            (string) $record->id, (string) $record->version, (string) $this->price->id, $key];
        $environment = $this->databaseEnvironment();
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
            $database = DB::connection()->getPdo();
            $this->assertSame(1, (int) $database->query('SELECT COUNT(*) FROM sort_lots')->fetchColumn());
            $this->assertSame(1, (int) $database->query('SELECT COUNT(*) FROM sort_stock_movements')->fetchColumn());
            $this->assertSame(1, (int) $database->query('SELECT COUNT(*) FROM idempotency_requests')->fetchColumn());
            $this->assertSame(110000, (int) $database->query('SELECT SUM(share_amount_idr) FROM weighing_loaders')->fetchColumn());
            $database = null;
        } finally {
            foreach ([$first, $second] as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
        }
    }

    public function test_subgram_input_invalid_weights_and_inconsistent_allocations_are_rejected(): void
    {
        $this->assertLegacyDraftErrors($this->payload(['tbs_kg' => '1.0005']), 'tbs_kg');
        $record = $this->draft();
        try {
            DB::table('weighing_items')->where('weighing_id', $record->id)->update(['weight_grams' => 0]);
            $this->fail('Zero weight must fail');
        } catch (QueryException $error) {
            $this->assertSame(3819, (int) $error->errorInfo[1]);
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

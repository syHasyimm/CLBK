<?php

namespace Tests\Concerns\SalesAndInventoryTest;

use App\Actions\Sales\PostSale;
use App\Domain\Inventory\Stock;
use App\Models\SortLot;
use App\Models\Trip;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Process\Process;

trait StockAllocationCases
{
    public function test_partial_fifo_releases_proportional_cost_and_last_sale_empties_exactly(): void
    {
        $this->factoryPosted();
        $lot = SortLot::firstOrFail();
        $sale = $this->postSale($this->draft('sort'));
        $this->assertSame(60000, $sale->revenue_idr);
        $this->assertSame(90000, $sale->purchase_cost_released_idr);
        $this->assertSame(3000, $sale->loading_cost_released_idr);
        $balance = app(Stock::class)->balance($lot);
        $this->assertSame([50000, 150000, 5000], [$balance['grams'], $balance['purchase'], $balance['loading']]);
        $this->postSale($this->draft('sort', ['sold_kg' => '50']));
        $balance = app(Stock::class)->balance($lot);
        $this->assertSame([0, 0, 0], [$balance['grams'], $balance['purchase'], $balance['loading']]);
    }

    public function test_fifo_multiple_lots_and_gram_rounding_reconcile_header_and_items(): void
    {
        $this->weigh('0', '0.001');
        $this->weigh('0', '0.001');
        $sale = $this->postSale($this->draft('sort', ['sold_kg' => '0.002', 'sell_price_snapshot_idr' => 500]));
        $this->assertSame(2, $sale->items()->count());
        $this->assertSame(2, $sale->revenue_idr); // Each 0.5 rupiah line rounds half up.
        $this->assertSame([1, 2], $sale->items()->orderBy('id')->pluck('lot_id')->all());
        $this->assertSame(0, SortLot::all()->sum(fn ($lot) => app(Stock::class)->balance($lot)['grams']));
    }

    public function test_oversell_and_changed_fifo_preview_are_rejected_without_partial_writes(): void
    {
        $this->factoryPosted();
        $first = $this->draft('sort');
        $hash = app(Stock::class)->preview($first)['preview_hash'];
        $this->postSale($this->draft('sort', ['sold_kg' => '20']));
        $this->post(route('sort-sales.post', ['sale' => $first->id]), $this->key(['version' => $first->version, 'preview_hash' => $hash]))->assertSessionHasErrors('operation');
        $this->assertSame('draft', $first->fresh()->status);
        $oversell = $this->draft('sort', ['sold_kg' => '61']);
        $this->get(route('sort-sales.show', ['sale' => $oversell->id]))->assertInertia(fn (Assert $p) => $p->where('preview', null)->where('previewError', fn ($v) => str_contains($v, 'melebihi')));
        $this->post(route('sort-sales.post', ['sale' => $oversell->id]), $this->key(['version' => $oversell->version, 'preview_hash' => str_repeat('a', 64)]))->assertSessionHasErrors('operation');
        $this->assertDatabaseCount('sort_sale_items', 1);
        $this->assertDatabaseCount('sort_stock_movements', 2);
    }

    public function test_no_ticket_manual_sale_and_return_keep_unknown_costs_null(): void
    {
        $this->dispatch();
        $sale = $this->postSale($this->draft('factory'));
        $this->assertSame('unavailable', $sale->cost_status);
        $this->assertNull($sale->purchase_cost_released_idr);
        $this->assertNull(SortLot::firstOrFail()->initial_purchase_cost_idr);
        $sort = $this->postSale($this->draft('sort'));
        $this->assertSame(60000, $sort->revenue_idr);
        $this->assertNull($sort->purchase_cost_released_idr);
        $this->assertDatabaseCount('profit_entries', 2);
        $this->assertSame(2, DB::table('sort_stock_movements')->whereNull('purchase_cost_delta_idr')->count());
        $this->get(route('sort-sales.show', ['sale' => $sort->id]))->assertInertia(fn (Assert $p) => $p->where('record.purchase_cost_released_idr', null)->where('record.cost_status', 'unavailable'));
    }

    public function test_stock_loss_must_be_reversed_before_factory_source_and_records_single_loss_cost(): void
    {
        $sale = $this->factoryPosted();
        $lot = SortLot::firstOrFail();
        $this->post(route('inventory.adjust', $lot), $this->key(['loss_kg' => '10', 'reason' => 'Rusak']))->assertSessionHasNoErrors();
        $loss = $lot->movements()->where('kind', 'adjustment')->firstOrFail();
        $this->assertSame(31000, (int) DB::table('profit_entries')->where('category', 'stock_loss')->sum('amount_delta_idr'));
        $this->post(route('factory-sales.reverse', ['sale' => $sale->id]), $this->key(['version' => $sale->version, 'reason' => 'Koreksi']))->assertSessionHasErrors('operation');
        $this->post(route('inventory.reverse', ['lot' => $lot->id, 'movement' => $loss->id]), $this->key(['reason' => 'Kesalahan berat rusak']))->assertSessionHasNoErrors();
        $this->assertSame(0, (int) DB::table('profit_entries')->where('category', 'stock_loss')->sum('amount_delta_idr'));
        $this->post(route('factory-sales.reverse', ['sale' => $sale->id]), $this->key(['version' => $sale->version, 'reason' => 'Koreksi']))->assertSessionHasNoErrors();
    }

    public function test_close_requires_invoice_and_empty_stock_but_does_not_wait_for_payment(): void
    {
        $sale = $this->factoryPosted();
        $this->post(route('trips.close', $this->trip), $this->key(['version' => $this->trip->fresh()->version]))->assertSessionHasErrors('operation');
        $this->postSale($this->draft('sort', ['sold_kg' => '80']));
        $this->post(route('trips.close', $this->trip), $this->key(['version' => $this->trip->fresh()->version]))->assertSessionHasNoErrors();
        $this->assertSame('closed', $this->trip->fresh()->status);
        $this->assertSame('posted', $sale->fresh()->status);
    }

    public function test_sale_audit_failure_rolls_back_header_return_stock_profit_and_idempotency(): void
    {
        $this->weigh();
        $this->dispatch();
        $sale = $this->draft('factory');
        $before = DB::table('idempotency_requests')->count();
        $this->mock(AuditService::class, function ($mock) {
            $mock->shouldReceive('snapshot')->andReturn([]);
            $mock->shouldReceive('record')->withArgs(fn ($actor, $action) => $action !== 'post')->andReturnNull();
            $mock->shouldReceive('record')->withArgs(fn ($actor, $action) => $action === 'post')->andThrow(new \RuntimeException('late audit failure'));
        });
        try {
            app(PostSale::class)->handle($this->manager, $sale, $this->key(['version' => $sale->version]));
            $this->fail('Expected rollback');
        } catch (\RuntimeException $e) {
            $this->assertSame('late audit failure', $e->getMessage());
        }
        $this->assertSame('draft', $sale->fresh()->status);
        $this->assertSame('dispatched', $this->trip->fresh()->status);
        $this->assertDatabaseCount('factory_returns', 0);
        $this->assertDatabaseCount('sort_lots', 0);
        $this->assertDatabaseCount('profit_entries', 0);
        $this->assertDatabaseCount('idempotency_requests', $before);
    }

    public function test_backdated_sale_before_departure_or_latest_stock_movement_is_rejected(): void
    {
        $this->weigh();
        $this->dispatch();
        $this->post(route('legacy-test.factory-sales.store'), $this->payload('factory', ['sold_at' => '2026-10-09T09:59:00']))->assertSessionHasErrors('sold_at');
        $this->postSale($this->draft('factory'));
        $draft = $this->draft('sort', ['sold_at' => '2026-10-09T09:59:00']);
        $this->get(route('sort-sales.show', ['sale' => $draft->id]))->assertInertia(fn (Assert $p) => $p->where('preview', null)->where('previewError', fn ($v) => str_contains($v, 'mendahului')));
    }

    public function test_source_weighing_can_void_after_sort_sale_reversal_while_loading(): void
    {
        $ticket = $this->weigh('0', '30');
        $sort = $this->postSale($this->draft('sort'));
        $this->post(route('weighings.void', $ticket), $this->key(['version' => $ticket->version, 'reason' => 'Salah timbang']))->assertSessionHasErrors('operation');
        $this->post(route('sort-sales.reverse', ['sale' => $sort->id]), $this->key(['version' => $sort->version, 'reason' => 'Koreksi turunan']))->assertSessionHasNoErrors();
        $this->post(route('weighings.void', $ticket), $this->key(['version' => $ticket->version, 'reason' => 'Salah timbang']))->assertSessionHasNoErrors();
        $this->assertSame('voided', $ticket->fresh()->status);
    }

    public function test_two_processes_competing_for_sort_stock_post_only_one_sale(): void
    {
        $this->factoryPosted();
        $first = $this->draft('sort', ['sold_kg' => '60']);
        $second = $this->draft('sort', ['sold_kg' => '60']);
        $hashes = [app(Stock::class)->preview($first)['preview_hash'], app(Stock::class)->preview($second)['preview_hash']];
        $path = tempnam(sys_get_temp_dir(), 'clbk-sort-');
        unlink($path);
        DB::statement('VACUUM INTO ?', [$path]);
        $pdo = new \PDO('sqlite:'.$path);
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo = null;
        $env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $path];
        $processes = [];
        foreach ([$first, $second] as $index => $sale) {
            $processes[] = new Process([PHP_BINARY, base_path('tests/Support/post-sort-sale-process.php'), (string) $this->manager->id, (string) $sale->id, (string) $sale->version, $hashes[$index], (string) Str::uuid()], base_path(), $env);
        }
        try {
            foreach ($processes as $process) {
                $process->start();
            }foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            }
            $outputs = array_map(fn ($p) => json_decode($p->getOutput(), true, flags: JSON_THROW_ON_ERROR), $processes);
            $this->assertCount(1, array_filter($outputs, fn ($o) => isset($o['id'])));
            $this->assertCount(1, array_filter($outputs, fn ($o) => isset($o['errors'])));
            $pdo = new \PDO('sqlite:'.$path);
            $this->assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM sort_sales WHERE status='posted'")->fetchColumn());
            $this->assertSame(20000, (int) $pdo->query('SELECT SUM(quantity_delta_grams) FROM sort_stock_movements')->fetchColumn());
            $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM sort_sale_items')->fetchColumn());
            $pdo = null;
        } finally {
            foreach (['', '-wal', '-shm'] as $suffix) {
                if (is_file($path.$suffix)) {
                    unlink($path.$suffix);
                }
            }
        }
    }

    public function test_sort_post_failure_after_profit_write_rolls_back_allocation_and_stock(): void
    {
        $this->factoryPosted();
        $sale = $this->draft('sort');
        $lot = SortLot::firstOrFail();
        $before = app(Stock::class)->balance($lot);
        $keys = DB::table('idempotency_requests')->count();
        $this->mock(AuditService::class, function ($mock) {
            $mock->shouldReceive('snapshot')->andReturn([]);
            $mock->shouldReceive('record')->andThrow(new \RuntimeException('sort audit failure'));
        });
        try {
            app(PostSale::class)->handle($this->manager, $sale, $this->key(['version' => $sale->version, 'preview_hash' => app(Stock::class)->preview($sale)['preview_hash']]));
            $this->fail('Expected rollback');
        } catch (\RuntimeException $e) {
            $this->assertSame('sort audit failure', $e->getMessage());
        }
        $this->assertSame('draft', $sale->fresh()->status);
        $this->assertSame($before, app(Stock::class)->balance($lot));
        $this->assertDatabaseCount('sort_sale_items', 0);
        $this->assertDatabaseCount('profit_entries', 3);
        $this->assertDatabaseCount('idempotency_requests', $keys);
    }

    public function test_two_same_day_trips_on_same_vehicle_keep_manual_weights_and_return_stock_separate(): void
    {
        $first = $this->factoryPosted();
        $oldTrip = $this->trip;
        $this->post(route('trips.store'), ['vehicle_id' => $oldTrip->vehicle_id, 'driver_id' => $this->driver->id])->assertSessionHasNoErrors();
        $this->trip = Trip::latest('id')->firstOrFail();
        $this->dispatch();
        $second = $this->postSale($this->draft('factory', ['initial_kg' => '500', 'sort_kg' => '20', 'accepted_kg' => '480']));
        $this->assertSame(1000000, $oldTrip->fresh()->manual_load_grams);
        $this->assertSame(500000, $this->trip->fresh()->manual_load_grams);
        $this->assertSame(80000, SortLot::where('trip_id', $oldTrip->id)->firstOrFail()->initial_grams);
        $this->assertSame(20000, SortLot::where('trip_id', $this->trip->id)->firstOrFail()->initial_grams);
        $this->assertSame('unavailable', $second->cost_status);
        $this->assertSame(3150000, $first->fresh()->revenue_idr);
    }
}

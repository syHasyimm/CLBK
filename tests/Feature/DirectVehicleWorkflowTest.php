<?php

namespace Tests\Feature;

use App\Enums\TransactionWorkflow;
use App\Models\Customer;
use App\Models\Factory;
use App\Models\FactoryPriceSchedule;
use App\Models\FactorySale;
use App\Models\SortSale;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Weighing;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class DirectVehicleWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $driver;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = User::factory()->create();
        $this->vehicle = Vehicle::create(['code' => 'D1', 'plate_number' => 'BK DIRECT', 'is_active' => true]);
    }

    private function sort(array $extra = []): SortSale
    {
        return SortSale::create(array_replace(['number' => 'DIRECT-SORT', 'vehicle_id' => $this->vehicle->id,
            'workflow' => TransactionWorkflow::Direct, 'trip_id' => null, 'sold_at' => now(), 'business_date' => now()->toDateString(),
            'manual_sold_grams' => 10000, 'sell_price_snapshot_idr' => 2000, 'deduction_idr' => 0, 'revenue_idr' => 20000,
            'status' => 'draft', 'created_by' => $this->driver->id], $extra));
    }

    private function factory(): FactorySale
    {
        $factory = Factory::create(['code' => 'DP', 'name' => 'Pabrik Direct', 'is_active' => true]);
        $price = FactoryPriceSchedule::create(['factory_id' => $factory->id, 'effective_from' => now()->subHour(),
            'tbs_sell_price_idr' => 3100, 'published_by' => User::factory()->manager()->create()->id]);

        return FactorySale::create(['number' => 'DIRECT-FACTORY', 'vehicle_id' => $this->vehicle->id,
            'workflow' => TransactionWorkflow::Direct, 'trip_id' => null, 'factory_id' => $factory->id,
            'factory_price_schedule_id' => $price->id, 'sold_at' => now(), 'business_date' => now()->toDateString(),
            'accepted_grams' => 1000000, 'sell_price_snapshot_idr' => 3100, 'deduction_idr' => 0, 'revenue_idr' => 3100000,
            'status' => 'draft', 'created_by' => $this->driver->id]);
    }

    private function weighing(): Weighing
    {
        return Weighing::create(['number' => 'DIRECT-WEIGHING', 'vehicle_id' => $this->vehicle->id,
            'workflow' => TransactionWorkflow::Direct, 'trip_id' => null,
            'customer_id' => Customer::create(['code' => 'DK', 'name' => 'Konsumen Direct', 'is_active' => true])->id,
            'weighed_at' => now(), 'business_date' => now()->toDateString(), 'status' => 'draft', 'created_by' => $this->driver->id]);
    }

    public function test_direct_records_reference_vehicle_without_creating_trip_or_stock(): void
    {
        foreach ([$this->weighing(), $this->factory(), $this->sort()] as $record) {
            $this->assertNull($record->trip);
            $this->assertTrue($record->vehicle->is($this->vehicle));
            $this->assertSame(TransactionWorkflow::Direct, $record->fresh()->workflow);
        }
        foreach (['trips', 'sort_lots', 'sort_stock_movements', 'sort_sale_items', 'factory_returns'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertNull(FactorySale::firstOrFail()->initial_load_grams);
        $this->assertNull(FactorySale::firstOrFail()->sorted_out_grams);
        $this->assertForeignKeysAreValid();
    }

    public function test_direct_access_follows_creator_instead_of_vehicle_or_trip(): void
    {
        $other = User::factory()->create();
        $manager = User::factory()->manager()->create();
        $cashier = User::factory()->cashier()->create();
        foreach ([$this->weighing(), $this->factory(), $this->sort()] as $record) {
            $this->assertTrue(Gate::forUser($this->driver)->allows('view', $record));
            $this->assertFalse(Gate::forUser($other)->allows('view', $record));
            $this->assertTrue(Gate::forUser($manager)->allows('view', $record));
            $this->assertFalse(Gate::forUser($cashier)->allows('view', $record));
            $this->assertFalse(Gate::forUser($other)->allows('update', $record));
        }
        $sale = SortSale::firstOrFail();
        $sale->update(['status' => 'posted', 'posted_at' => now(), 'posted_by' => $this->driver->id, 'version' => 2]);
        $this->assertTrue(Gate::forUser($cashier)->allows('view', $sale));
        $this->assertFalse(Gate::forUser($cashier)->allows('update', $sale));
    }

    public function test_direct_sort_post_reconciles_manual_revenue_without_fifo(): void
    {
        $sale = $this->sort();
        try {
            $sale->update(['status' => 'posted', 'revenue_idr' => 1, 'posted_at' => now(), 'posted_by' => $this->driver->id, 'version' => 2]);
            $this->fail('Incorrect revenue accepted');
        } catch (QueryException) {
            $this->assertSame('draft', $sale->fresh()->status);
        }
        $sale->fresh()->update(['status' => 'posted', 'posted_at' => now(), 'posted_by' => $this->driver->id, 'version' => 2]);
        $this->assertDatabaseCount('sort_sale_items', 0);
        $other = Vehicle::create(['code' => 'D2', 'plate_number' => 'BK OTHER', 'is_active' => true]);
        $this->expectException(QueryException::class);
        DB::table('sort_sales')->where('id', $sale->id)->update(['vehicle_id' => $other->id]);
    }

    public function test_direct_factory_post_requires_matching_price_and_does_not_require_return(): void
    {
        $sale = $this->factory();
        try {
            DB::table('factory_sales')->where('id', $sale->id)->update(['sell_price_snapshot_idr' => 1]);
            $this->fail('Mismatched factory price accepted');
        } catch (QueryException) {
            $this->assertSame(3100, $sale->fresh()->sell_price_snapshot_idr);
        }
        $sale->update(['status' => 'posted', 'posted_at' => now(), 'posted_by' => $this->driver->id, 'version' => 2]);
        $this->assertSame('posted', $sale->fresh()->status);
        $this->assertDatabaseCount('factory_returns', 0);
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Direct factory sales do not create return stock');
        DB::table('factory_returns')->insert(['factory_sale_id' => $sale->id, 'weight_grams' => 100,
            'cost_status' => 'unavailable', 'received_at' => now()]);
    }

    public function test_direct_weighing_cannot_create_sort_stock(): void
    {
        $record = $this->weighing();
        $item = $record->items()->create(['fruit_type' => 'SORT', 'weight_grams' => 1000]);
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Direct weighings do not create stock');
        DB::table('sort_lots')->insert(['source_type' => 'INITIAL_SORT', 'source_weighing_item_id' => $item->id,
            'trip_id' => 999, 'received_at' => now(), 'initial_grams' => 1000, 'cost_status' => 'unavailable', 'status' => 'active']);
    }

    public function test_direct_sort_cannot_create_fifo_allocations(): void
    {
        $sale = $this->sort();
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Direct sort sales do not allocate FIFO');
        DB::table('sort_sale_items')->insert(['sort_sale_id' => $sale->id, 'lot_id' => 999, 'weight_grams' => 100,
            'sell_price_snapshot_idr' => 2000, 'gross_revenue_idr' => 200]);
    }

    public function test_direct_workflow_rejects_missing_vehicle_and_rollback_does_not_discard_records(): void
    {
        try {
            $this->sort(['vehicle_id' => null]);
            $this->fail('Missing vehicle accepted');
        } catch (QueryException) {
            $this->assertDatabaseCount('sort_sales', 0);
        }
        $this->sort();
        $migration = require database_path('migrations/2026_10_17_000001_create_clbk_schema.php');
        try {
            $migration->down();
            $this->fail('Rollback discarded a direct record');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('tidak dapat dihapus melalui rollback', $e->getMessage());
        }
        $this->assertDatabaseCount('sort_sales', 1);
        $this->assertForeignKeysAreValid();
    }
}

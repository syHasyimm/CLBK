<?php

namespace Tests\Feature;

use App\Actions\Sales\SaveSale;
use App\Actions\Weighings\CreateDraftWeighing;
use App\Domain\Inventory\Stock;
use App\Enums\UserRole;
use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\Factory;
use App\Models\FactoryPriceSchedule;
use App\Models\FactorySale;
use App\Models\LoadingWorker;
use App\Models\PriceSchedule;
use App\Models\SortSale;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Weighing;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Tests\Concerns\DatabaseMigrations;
use Tests\Concerns\LegacyWorkflowRoutes;
use Tests\Concerns\SalesAndInventoryTest\FactorySaleEntryCases;
use Tests\Concerns\SalesAndInventoryTest\InventoryMigrationsCases;
use Tests\Concerns\SalesAndInventoryTest\SaleAccessCases;
use Tests\Concerns\SalesAndInventoryTest\SaleIntegrityCases;
use Tests\Concerns\SalesAndInventoryTest\StockAllocationCases;
use Tests\Concerns\SalesAndInventoryTest\TripLifecycleCases;
use Tests\TestCase;

class SalesAndInventoryTest extends TestCase
{
    use DatabaseMigrations;
    use FactorySaleEntryCases;
    use InventoryMigrationsCases;
    use LegacyWorkflowRoutes;
    use SaleAccessCases;
    use SaleIntegrityCases;
    use StockAllocationCases;
    use TripLifecycleCases;

    private User $manager;

    private User $driver;

    private User $cashier;

    private Trip $trip;

    private Factory $factory;

    private Weighing $ticket;

    private PriceSchedule $price;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registerLegacyWorkflowTestRoutes();
        $this->travelTo(Carbon::parse('2026-10-09 03:00:00', 'UTC'));
        $this->manager = User::factory()->manager()->create();
        $this->driver = User::factory()->create();
        $this->cashier = User::factory()->cashier()->create();
        CashAccount::create(['code' => 'KAS', 'name' => 'Kas', 'type' => 'cash', 'is_active' => true]);
        $this->factory = Factory::create(['code' => 'P1', 'name' => 'Pabrik Uji', 'is_active' => true]);
        FactoryPriceSchedule::create(['factory_id' => $this->factory->id, 'effective_from' => '2026-10-08 17:00:00', 'tbs_sell_price_idr' => 3500, 'published_by' => $this->manager->id]);
        $vehicle = Vehicle::create(['code' => 'M1', 'plate_number' => 'BK 1234', 'is_active' => true]);
        $this->actingAs($this->manager)->post(route('trips.store'), ['vehicle_id' => $vehicle->id, 'driver_id' => $this->driver->id, 'planned_factory_id' => $this->factory->id])->assertSessionHasNoErrors();
        $this->trip = Trip::firstOrFail();
        $this->price = PriceSchedule::create(['business_date' => '2026-10-09', 'effective_from' => '2026-10-08 17:00:00',
            'tbs_buy_price_idr' => 3000, 'sort_buy_price_idr' => 2000, 'loading_rate_idr' => 100, 'published_by' => $this->manager->id]);
    }

    private function key(array $extra = []): array
    {
        return $extra + ['idempotency_key' => (string) Str::uuid()];
    }

    private function weigh(string $tbs = '1000', string $sort = '0'): Weighing
    {
        $customer = Customer::create(['code' => 'K'.Str::random(8), 'name' => 'Konsumen Uji', 'is_active' => true]);
        $worker = LoadingWorker::create(['code' => 'A'.Str::random(8), 'name' => 'Anggota Uji', 'is_active' => true]);
        $this->actingAs($this->manager);
        $ticket = app(CreateDraftWeighing::class)->handle($this->manager, ['customer_id' => $customer->id, 'trip_id' => $this->trip->id,
            'tbs_kg' => $tbs, 'sort_kg' => $sort, 'worker_ids' => [$worker->id], 'weighed_at' => '2026-10-09T10:00:00'])->fresh();
        $this->post(route('weighings.post', $ticket), $this->key(['version' => $ticket->version, 'price_schedule_id' => $this->price->id]))->assertSessionHasNoErrors();

        return $this->ticket = $ticket->fresh();
    }

    private function dispatch(): void
    {
        $this->actingAs($this->manager)->post(route('trips.dispatch', $this->trip), $this->key(['version' => $this->trip->fresh()->version]))->assertSessionHasNoErrors();
        $this->trip = $this->trip->fresh();
    }

    private function payload(string $kind, array $overrides = []): array
    {
        return array_replace(['trip_id' => $this->trip->id, 'sold_at' => '2026-10-09T10:00:00', 'sell_price_snapshot_idr' => $kind === 'factory' ? 3500 : 2000,
            'deduction_idr' => 0] + ($kind === 'factory' ? ['factory_id' => $this->factory->id, 'invoice_number' => 'INV-'.Str::random(8),
                'initial_kg' => '1000', 'sort_kg' => '80', 'accepted_kg' => '900'] : ['buyer_name' => 'Pembeli Uji', 'sold_kg' => '30']), $overrides);
    }

    private function draft(string $kind, array $overrides = [], ?User $actor = null)
    {
        $data = $this->payload($kind, $overrides);
        if ($actor?->role === UserRole::Driver) {
            unset($data['sold_at']);
        }
        $this->actingAs($actor ?? $this->manager)->post(route('legacy-test.'.$kind.'-sales.store'), $data)->assertSessionHasNoErrors();

        return SaveSale::model($kind)::latest('id')->firstOrFail();
    }

    private function postSale($sale, ?User $actor = null)
    {
        $data = $this->key(['version' => $sale->version]);
        if ($sale instanceof SortSale) {
            $data['preview_hash'] = app(Stock::class)->preview($sale)['preview_hash'];
        }
        $this->actingAs($actor ?? $this->manager)->post(route($sale->kind().'-sales.post', ['sale' => $sale->id]), $data)->assertSessionHasNoErrors();

        return $sale->fresh();
    }

    private function factoryPosted(): FactorySale
    {
        $this->weigh();
        $this->dispatch();

        return $this->postSale($this->draft('factory'));
    }
}

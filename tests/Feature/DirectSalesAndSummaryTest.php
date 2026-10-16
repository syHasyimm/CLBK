<?php

namespace Tests\Feature;

use App\Actions\Finance\PostFinance;
use App\Enums\FinanceKind;
use App\Models\CashAccount;
use App\Models\Factory;
use App\Models\FactoryPriceSchedule;
use App\Models\FactorySale;
use App\Models\SaleReceipt;
use App\Models\SortSale;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\DatabaseMigrations;
use Tests\Concerns\DirectSalesAndSummaryTest\DirectSaleCorrectionsCases;
use Tests\Concerns\DirectSalesAndSummaryTest\DirectSaleEntryCases;
use Tests\Concerns\DirectSalesAndSummaryTest\DirectSaleReceiptsCases;
use Tests\Concerns\DirectSalesAndSummaryTest\PeriodSummariesCases;
use Tests\Concerns\LegacyCustomerPayments;
use Tests\TestCase;

class DirectSalesAndSummaryTest extends TestCase
{
    use DatabaseMigrations;
    use DirectSaleCorrectionsCases;
    use DirectSaleEntryCases;
    use DirectSaleReceiptsCases;
    use LegacyCustomerPayments;
    use PeriodSummariesCases;

    public function runDatabaseMigrations(): void
    {
        $this->refreshTestDatabase();
        $this->beforeApplicationDestroyed(function () {
            RefreshDatabaseState::$migrated = false;
            DB::disconnect();
        });
    }

    private User $manager;

    private User $driver;

    private User $cashier;

    private Vehicle $vehicle;

    private Factory $factory;

    private FactoryPriceSchedule $price;

    private CashAccount $cash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-09 03:00:00', 'UTC'));
        $this->manager = User::factory()->manager()->create();
        $this->driver = User::factory()->create();
        $this->cashier = User::factory()->cashier()->create();
        $this->cash = CashAccount::create(['code' => 'KAS-D', 'name' => 'Kas', 'type' => 'cash', 'is_active' => true]);
        $this->vehicle = Vehicle::create(['code' => 'MD', 'plate_number' => 'BK LANGSUNG', 'is_active' => true]);
        $this->driver->update(['assigned_vehicle_id' => $this->vehicle->id]);
        $this->factory = Factory::create(['code' => 'PD', 'name' => 'Pabrik Langsung', 'is_active' => true]);
        $this->price = FactoryPriceSchedule::create(['factory_id' => $this->factory->id, 'effective_from' => '2026-10-08 17:00:00', 'tbs_sell_price_idr' => 3500, 'published_by' => $this->manager->id]);
    }

    private function payload(string $kind = 'factory', array $changes = []): array
    {
        return array_replace(['vehicle_id' => $this->vehicle->id, 'vehicle_assignment_version' => 1, 'idempotency_key' => (string) Str::uuid(), 'replaces_sale_id' => null, 'correction_reason' => '', 'notes' => '']
            + ($kind === 'factory' ? ['factory_id' => $this->factory->id, 'factory_price_schedule_id' => $this->price->id, 'accepted_kg' => '900', 'deduction_idr' => 1000]
                : ['sold_kg' => '30.005', 'sell_price_snapshot_idr' => 2000]), $changes);
    }

    private function save(string $kind = 'factory', array $changes = [], ?User $actor = null)
    {
        $this->actingAs($actor ?? $this->driver)->post(route($kind.'-sales.store'), $this->payload($kind, $changes))
            ->assertSessionHasNoErrors()->assertRedirect(route($kind.'-sales.index'))->assertSessionHas('saved_sale');

        return ($kind === 'factory' ? FactorySale::class : SortSale::class)::latest('id')->firstOrFail();
    }

    private function receive($sale): SaleReceipt
    {
        return app(PostFinance::class)->handle($this->cashier, FinanceKind::Receipt, ['sale_kind' => $sale->kind(), 'sale_id' => $sale->id,
            'cash_account_id' => $this->cash->id, 'amount_idr' => $sale->revenue_idr, 'expected_remaining_idr' => $sale->revenue_idr,
            'method' => 'cash', 'occurred_at' => '2026-10-09T10:00:00', 'idempotency_key' => (string) Str::uuid()]);
    }
}

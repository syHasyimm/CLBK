<?php

namespace Tests\Feature;

use App\Actions\Weighings\CreateDraftWeighing;
use App\Enums\FinanceKind;
use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\Factory;
use App\Models\FactoryPriceSchedule;
use App\Models\FactorySale;
use App\Models\LoadingWorker;
use App\Models\PriceSchedule;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Weighing;
use App\Services\CustomerSettlement;
use App\Support\BusinessClock;
use Carbon\Carbon;
use Database\Seeders\BusinessDefaultsSeeder;
use Illuminate\Support\Str;
use Tests\Concerns\DatabaseMigrations;
use Tests\Concerns\FinanceTest\CustomerPaymentsCases;
use Tests\Concerns\FinanceTest\ExpensesAndCapitalCases;
use Tests\Concerns\FinanceTest\FinanceIntegrityCases;
use Tests\Concerns\FinanceTest\PayrollPaymentsCases;
use Tests\Concerns\FinanceTest\SaleReceiptsCases;
use Tests\Concerns\LegacyCustomerPayments;
use Tests\Concerns\LegacyWorkflowRoutes;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use CustomerPaymentsCases;
    use DatabaseMigrations;
    use ExpensesAndCapitalCases;
    use FinanceIntegrityCases;
    use LegacyCustomerPayments;
    use LegacyWorkflowRoutes;
    use PayrollPaymentsCases;
    use SaleReceiptsCases;

    private User $manager;

    private User $cashier;

    private User $driver;

    private CashAccount $cash;

    private Customer $customer;

    private LoadingWorker $worker;

    private Trip $trip;

    private PriceSchedule $price;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registerLegacyWorkflowTestRoutes();
        $this->travelTo(Carbon::parse('2026-10-09 03:00:00', 'UTC'));
        $this->seed(BusinessDefaultsSeeder::class);
        $this->manager = User::factory()->manager()->create();
        $this->cashier = User::factory()->cashier()->create();
        $this->driver = User::factory()->create();
        $this->cash = CashAccount::firstOrFail();
        $this->customer = Customer::create(['code' => 'K1', 'name' => 'Konsumen Lama', 'is_active' => true]);
        $this->worker = LoadingWorker::create(['code' => 'A1', 'name' => 'Anggota Muat', 'is_active' => true]);
        $vehicle = Vehicle::create(['code' => 'M1', 'plate_number' => 'BK FINANCE', 'is_active' => true]);
        $this->actingAs($this->manager)->post(route('trips.store'), ['vehicle_id' => $vehicle->id, 'driver_id' => $this->driver->id])->assertSessionHasNoErrors();
        $this->trip = Trip::firstOrFail();
        $this->price = PriceSchedule::create(['business_date' => '2026-10-09', 'effective_from' => '2026-10-08 17:00:00', 'tbs_buy_price_idr' => 3000, 'sort_buy_price_idr' => 2000, 'loading_rate_idr' => 100, 'published_by' => $this->manager->id]);
    }

    private function key(array $data = []): array
    {
        return $data + ['idempotency_key' => (string) Str::uuid()];
    }

    private function weigh(string $kg = '1000', ?Customer $customer = null, string $sort = '0'): Weighing
    {
        $this->actingAs($this->manager);
        $ticket = app(CreateDraftWeighing::class)->handle($this->manager, ['trip_id' => $this->trip->id, 'customer_id' => ($customer ?? $this->customer)->id,
            'tbs_kg' => $kg, 'sort_kg' => $sort, 'worker_ids' => [$this->worker->id], 'weighed_at' => app(BusinessClock::class)->local(app(BusinessClock::class)->now())])->fresh();
        $this->post(route('weighings.post', $ticket), $this->key(['version' => $ticket->version, 'price_schedule_id' => $this->price->id]))->assertSessionHasNoErrors();

        return $ticket->fresh();
    }

    private function capital(int $amount = 10000000): void
    {
        $this->actingAs($this->manager)->post(route('cash.store'), $this->key(['cash_account_id' => $this->cash->id, 'kind' => 'opening', 'amount_idr' => $amount,
            'occurred_at' => '2026-10-09T09:00:00', 'reason' => 'Saldo awal usaha']))->assertSessionHasNoErrors();
    }

    private function payment(Weighing $ticket, array $extra = []): array
    {
        $remaining = app(CustomerSettlement::class)->total($ticket->customer_id);

        return array_replace($this->key(['recipient_id' => $ticket->customer_id, 'selected_ids' => [$ticket->id], 'cash_account_id' => $this->cash->id,
            'amount_idr' => $remaining, 'expected_remaining_idr' => $remaining, 'method' => 'cash', 'occurred_at' => '2026-10-09T10:00:00']), $extra);
    }

    private function automaticPayroll(array $extra = []): array
    {
        return array_replace($this->key(['recipient_id' => $this->worker->id, 'all_in_period' => true,
            'cash_account_id' => $this->cash->id, 'amount_idr' => 100000, 'expected_remaining_idr' => 100000,
            'method' => 'cash', 'period_start' => '2026-10-09', 'period_end' => '2026-10-09',
            'occurred_at' => '2026-10-09T10:00:00']), $extra);
    }

    private function factorySale(): FactorySale
    {
        $factory = Factory::create(['code' => 'P1', 'name' => 'Pabrik Uji', 'is_active' => true]);
        FactoryPriceSchedule::create(['factory_id' => $factory->id, 'effective_from' => '2026-10-08 17:00:00', 'tbs_sell_price_idr' => 3500, 'published_by' => $this->manager->id]);
        $this->actingAs($this->manager)->post(route('trips.dispatch', $this->trip), $this->key(['version' => $this->trip->fresh()->version]))->assertSessionHasNoErrors();
        $this->post(route('legacy-test.factory-sales.store'), ['trip_id' => $this->trip->id, 'factory_id' => $factory->id, 'invoice_number' => 'NOTA-KEUANGAN',
            'sold_at' => '2026-10-09T10:00:00', 'initial_kg' => '1000', 'sort_kg' => '0', 'accepted_kg' => '1000', 'sell_price_snapshot_idr' => 3500, 'deduction_idr' => 0])->assertSessionHasNoErrors();
        $sale = FactorySale::firstOrFail();
        $this->post(route('factory-sales.post', ['sale' => $sale->id]), $this->key(['version' => $sale->version]))->assertSessionHasNoErrors();

        return $sale->fresh();
    }

    private function reverse(FinanceKind $kind, $record): void
    {
        $this->actingAs($this->manager)->post(route('finance.reverse', ['kind' => $kind->value, 'document' => $record->id]),
            $this->key(['version' => $record->version, 'reason' => 'Koreksi transaksi', 'return_reference' => 'Uang telah dikembalikan tunai']))->assertSessionHasNoErrors();
    }
}

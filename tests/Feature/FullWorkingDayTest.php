<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Factory;
use App\Models\FactoryPriceSchedule;
use App\Models\FactorySale;
use App\Models\LoadingWorker;
use App\Models\PriceSchedule;
use App\Models\SortSale;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Weighing;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\DatabaseMigrations;
use Tests\TestCase;

class FullWorkingDayTest extends TestCase
{
    use DatabaseMigrations;

    public function test_complete_day_next_day_settlement_reports_and_private_downloads(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 16)->setTime(3, 0));
        Storage::fake('local');
        $this->seed();
        $manager = User::factory()->manager()->create();
        $cashier = User::factory()->cashier()->create();
        $vehicle = Vehicle::create(['code' => 'UAT-M', 'plate_number' => 'BK UAT 6', 'is_active' => true]);
        $driver = User::factory()->create(['assigned_vehicle_id' => $vehicle->id]);
        $customer = Customer::create(['code' => 'UAT-K', 'name' => 'Konsumen UAT', 'is_active' => true]);
        $worker = LoadingWorker::create(['code' => 'UAT-A', 'name' => 'Anggota UAT', 'is_active' => true]);
        $factory = Factory::create(['code' => 'UAT-P', 'name' => 'Pabrik UAT', 'is_active' => true]);
        $key = fn ($data) => $data + ['idempotency_key' => (string) Str::uuid()];
        $this->actingAs($manager)->post(route('prices.store'), ['effective_from' => '2026-10-16T09:00:00',
            'tbs_buy_price_idr' => 3000, 'sort_buy_price_idr' => 2000, 'loading_rate_idr' => 100])->assertSessionHasNoErrors();
        $this->post(route('prices.factory-store'), ['effective_from' => '2026-10-16T09:00:00',
            'factory_id' => $factory->id, 'tbs_sell_price_idr' => 3500])->assertSessionHasNoErrors();
        $this->post(route('cash.store'), $key(['kind' => 'opening', 'amount_idr' => 5000000,
            'occurred_at' => '2026-10-16T10:00:00', 'reason' => 'Modal awal']))->assertSessionHasNoErrors();
        $this->actingAs($driver)->post(route('weighings.store'), $key(['customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id, 'vehicle_assignment_version' => 1, 'tbs_kg' => '1000', 'sort_kg' => '100',
            'worker_ids' => [$worker->id], 'price_schedule_id' => PriceSchedule::firstOrFail()->id]))->assertSessionHasNoErrors();
        $ticket = Weighing::firstOrFail();
        $this->assertSame(3200000, $ticket->total_purchase_idr);
        $this->assertSame(110000, $ticket->total_loading_wage_idr);
        $this->post(route('factory-sales.store'), $key(['vehicle_id' => $vehicle->id, 'vehicle_assignment_version' => 1,
            'factory_id' => $factory->id, 'factory_price_schedule_id' => FactoryPriceSchedule::firstOrFail()->id,
            'accepted_kg' => '900', 'deduction_idr' => 0]))->assertSessionHasNoErrors();
        $this->post(route('sort-sales.store'), $key(['vehicle_id' => $vehicle->id, 'vehicle_assignment_version' => 1,
            'sold_kg' => '100', 'sell_price_snapshot_idr' => 2000, 'buyer_name' => 'Pembeli UAT']))->assertSessionHasNoErrors();
        $factorySale = FactorySale::firstOrFail();
        $sortSale = SortSale::firstOrFail();
        $this->post(route('customer-payments.store'), [])->assertForbidden();
        $this->get(route('reports.export'))->assertForbidden();
        $this->actingAs($cashier);
        foreach ([$factorySale, $sortSale] as $sale) {
            $this->post(route('sale-receipts.store'), $key(['sale_kind' => $sale->kind(), 'sale_id' => $sale->id,
                'amount_idr' => $sale->revenue_idr, 'expected_remaining_idr' => $sale->revenue_idr,
                'method' => 'cash', 'occurred_at' => '2026-10-16T10:00:00']))->assertSessionHasNoErrors();
        }
        $this->post(route('daily-expenses.store'), $key(['expense_category_id' => DB::table('expense_categories')->value('id'),
            'amount_idr' => 50000, 'description' => 'BBM UAT', 'method' => 'cash', 'occurred_at' => '2026-10-16T10:00:00']))->assertSessionHasNoErrors();
        $expenseId = DB::table('daily_expenses')->value('id');
        $this->post(route('finance.proofs', ['kind' => 'expense', 'document' => $expenseId]), [
            'proof' => UploadedFile::fake()->createWithContent('bukti.pdf', "%PDF-1.4\nUAT\n%%EOF"),
        ])->assertSessionHasNoErrors();
        $proofId = DB::table('attachments')->value('id');
        $this->get(route('finance-proofs.download', $proofId))->assertDownload('bukti.pdf');
        $this->actingAs($manager)->get(route('dashboard', ['from' => '2026-10-16', 'to' => '2026-10-16']))
            ->assertInertia(fn (Assert $page) => $page->where('summary.totals.revenue_idr', 3350000)
                ->where('summary.totals.period_difference_idr', -10000)->where('summary.balances.cash_idr', 8300000));
        // Next-day rates cannot rewrite yesterday's entitlement or recognized expense.
        $this->travel(1)->days();
        $this->post(route('prices.store'), ['effective_from' => '2026-10-17T09:00:00',
            'tbs_buy_price_idr' => 2900, 'sort_buy_price_idr' => 1900, 'loading_rate_idr' => 90])->assertSessionHasNoErrors();
        $this->actingAs($cashier)->post(route('customer-payments.store'), $key(['recipient_id' => $customer->id,
            'amount_idr' => 3200000, 'expected_remaining_idr' => 3200000, 'method' => 'cash', 'occurred_at' => '2026-10-17T10:00:00']))->assertSessionHasNoErrors();
        $this->post(route('payroll-payments.store'), $key(['recipient_id' => $worker->id, 'all_in_period' => true,
            'period_start' => '2026-10-16', 'period_end' => '2026-10-16', 'amount_idr' => 110000,
            'expected_remaining_idr' => 110000, 'method' => 'cash', 'occurred_at' => '2026-10-17T10:00:00']))->assertSessionHasNoErrors();
        $this->actingAs($manager)->get(route('dashboard', ['from' => '2026-10-16', 'to' => '2026-10-17']))
            ->assertInertia(fn (Assert $page) => $page->where('summary.totals.period_difference_idr', -10000)
                ->where('summary.balances.cash_idr', 4990000)->where('summary.balances.customer_debt_idr', 0)
                ->where('summary.balances.payroll_debt_idr', 0)->where('summary.balances.sale_receivable_idr', 0));
        $csv = $this->get(route('reports.export', ['view' => 'summary', 'from' => '2026-10-16', 'to' => '2026-10-17']))->assertOk()->streamedContent();
        $this->assertStringContainsString('3350000', $csv);
        $this->assertStringContainsString('-10000', $csv);
        $this->get(route('reports.print', ['view' => 'summary', 'from' => '2026-10-16', 'to' => '2026-10-17']))->assertOk()->assertSee('Rp 4.990.000');
        $this->actingAs($driver)->get(route('finance-proofs.download', $proofId))->assertForbidden();
        $this->post(route('logout'))->assertRedirect('/');
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }
}

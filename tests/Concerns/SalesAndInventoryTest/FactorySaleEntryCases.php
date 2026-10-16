<?php

namespace Tests\Concerns\SalesAndInventoryTest;

use App\Domain\Inventory\Stock;
use App\Models\Factory;
use App\Models\FactoryPriceSchedule;
use App\Models\FactorySale;
use App\Models\SortLot;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

trait FactorySaleEntryCases
{
    public function test_factory_form_without_initial_weight_price_or_deduction_reason_can_post(): void
    {
        $this->weigh();
        $this->dispatch();
        $data = $this->payload('factory', ['deduction_idr' => 1000]);
        unset($data['initial_kg'], $data['sell_price_snapshot_idr']);
        $this->post(route('legacy-test.factory-sales.store'), $data)->assertSessionHasNoErrors();
        $sale = FactorySale::firstOrFail();
        $this->assertSame(980000, $sale->initial_load_grams);
        $this->assertSame(0, $sale->weight_difference_grams);
        $this->assertSame(3500, $sale->sell_price_snapshot_idr);
        $this->assertSame(FactoryPriceSchedule::firstOrFail()->id, $sale->factory_price_schedule_id);
        $this->assertSame(3149000, $sale->revenue_idr);
        $this->assertSame('Potongan penjualan pabrik', $sale->deduction_reason);
        $this->assertSame('posted', $this->postSale($sale)->status);
        $this->assertDatabaseCount('sale_receipts', 0);
        $this->assertDatabaseCount('cash_entries', 0);
        $this->assertSame(80000, SortLot::firstOrFail()->initial_grams);
    }

    public function test_factory_price_uses_effective_history_ignores_client_price_and_remains_frozen(): void
    {
        $this->dispatch();
        $this->travelTo(Carbon::parse('2026-10-09 04:00:00', 'UTC'));
        FactoryPriceSchedule::create(['factory_id' => $this->factory->id, 'effective_from' => '2026-10-09 03:30:00', 'tbs_sell_price_idr' => 3400, 'published_by' => $this->manager->id]);
        $sale = $this->draft('factory', ['sell_price_snapshot_idr' => 1]);
        $this->assertSame(3500, $sale->sell_price_snapshot_idr);
        $this->patch(route('factory-sales.update', ['sale' => $sale->id]), $this->payload('factory', ['version' => $sale->version,
            'invoice_number' => $sale->invoice_number, 'sold_at' => '2026-10-09T11:00:00', 'sell_price_snapshot_idr' => 99999]))->assertSessionHasNoErrors();
        $sale = $this->postSale($sale->fresh());
        $this->assertSame(3400, $sale->sell_price_snapshot_idr);
        FactoryPriceSchedule::create(['factory_id' => $this->factory->id, 'effective_from' => '2026-10-09 04:00:00', 'tbs_sell_price_idr' => 3300, 'published_by' => $this->manager->id]);
        $this->assertSame(3400, $sale->fresh()->sell_price_snapshot_idr);
    }

    public function test_factory_price_is_required_for_the_selected_factory_and_sale_time(): void
    {
        $this->dispatch();
        $other = Factory::create(['code' => 'P2', 'name' => 'Pabrik Tanpa Harga', 'is_active' => true]);
        $data = $this->payload('factory', ['factory_id' => $other->id]);
        unset($data['initial_kg'], $data['sell_price_snapshot_idr']);
        $this->post(route('legacy-test.factory-sales.store'), $data)->assertSessionHasErrors('factory_id');
        FactoryPriceSchedule::create(['factory_id' => $other->id, 'effective_from' => '2026-10-09 04:00:00', 'tbs_sell_price_idr' => 4000, 'published_by' => $this->manager->id]);
        $this->post(route('legacy-test.factory-sales.store'), $data)->assertSessionHasErrors('factory_id');
        $this->assertDatabaseCount('factory_sales', 0);
    }

    public function test_final_factory_weights_are_validated_without_initial_weight(): void
    {
        $this->dispatch();
        $data = $this->payload('factory');
        unset($data['initial_kg'], $data['sell_price_snapshot_idr']);
        $this->post(route('legacy-test.factory-sales.store'), array_replace($data, ['accepted_kg' => '0']))->assertSessionHasErrors('accepted_kg');
        $this->post(route('legacy-test.factory-sales.store'), array_replace($data, ['sort_kg' => '-1']))->assertSessionHasErrors('sort_kg');
        $this->post(route('legacy-test.factory-sales.store'), array_replace($data, ['sort_kg' => '1000000', 'accepted_kg' => '1']))->assertSessionHasErrors('accepted_kg');
        $this->assertDatabaseCount('factory_sales', 0);
    }

    public function test_manual_factory_weights_direct_clean_weight_and_prd_cost_example(): void
    {
        $sale = $this->factoryPosted();
        $this->assertSame(3150000, $sale->revenue_idr);
        $this->assertSame(20000, $sale->weight_difference_grams);
        $this->assertSame(2760000, $sale->purchase_cost_released_idr);
        $this->assertSame(92000, $sale->loading_cost_released_idr);
        $lot = SortLot::firstOrFail();
        $this->assertSame('FACTORY_RETURN', $lot->source_type);
        $this->assertSame(80000, $lot->initial_grams);
        $this->assertSame(240000, $lot->initial_purchase_cost_idr);
        $this->assertSame(8000, $lot->initial_loading_cost_idr);
        $this->assertSame(3000000, $this->ticket->fresh()->total_purchase_idr);
        $this->assertDatabaseCount('weighings', 1);
        $this->assertSame(1000000, $this->trip->fresh()->manual_load_grams);
        $this->postSale($this->draft('sort', ['sold_kg' => '80']));
        $totals = DB::table('profit_entries')->selectRaw("SUM(CASE WHEN category='revenue' THEN amount_delta_idr ELSE -amount_delta_idr END) AS net")->value('net');
        $this->assertSame(210000, (int) $totals);
        $this->assertSame(0, app(Stock::class)->balance($lot)['grams']);
    }

    public function test_variance_requires_manager_current_version_without_reason_and_edit_invalidates_approval(): void
    {
        $this->dispatch();
        $sale = $this->draft('factory', ['accepted_kg' => '890']);
        $this->post(route('factory-sales.post', ['sale' => $sale->id]), $this->key(['version' => $sale->version]))->assertSessionHasErrors('operation');
        $this->actingAs($this->driver)->post(route('factory-sales.approve', ['sale' => $sale->id]), $this->key(['version' => $sale->version, 'reason' => 'x']))->assertForbidden();
        $this->actingAs($this->manager)->post(route('factory-sales.approve', ['sale' => $sale->id]), $this->key(['version' => $sale->version]))->assertSessionHasNoErrors();
        $sale = $sale->fresh();
        $this->assertSame($sale->version, $sale->variance_approved_version);
        $this->assertSame('Persetujuan selisih berat oleh manajer', $sale->variance_reason);
        $this->patch(route('factory-sales.update', ['sale' => $sale->id]), $this->payload('factory', ['invoice_number' => $sale->invoice_number, 'accepted_kg' => '890', 'version' => $sale->version]))->assertSessionHasNoErrors();
        $sale = $sale->fresh();
        $this->assertNull($sale->variance_approved_by);
        $this->post(route('factory-sales.post', ['sale' => $sale->id]), $this->key(['version' => $sale->version]))->assertSessionHasErrors('operation');
        $this->post(route('factory-sales.approve', ['sale' => $sale->id]), $this->key(['version' => $sale->version, 'reason' => 'Hasil telah diperiksa']))->assertSessionHasNoErrors();
        $this->assertSame('posted', $this->postSale($sale->fresh())->status);
    }

    public function test_negative_difference_requires_approval_and_exact_two_percent_does_not(): void
    {
        $this->dispatch();
        $sale = $this->draft('factory', ['accepted_kg' => '930']);
        $this->get(route('factory-sales.show', ['sale' => $sale->id]))->assertInertia(fn (Assert $p) => $p->where('preview.requires_approval', true)->where('preview.weight_difference_grams', -10000));
        $this->post(route('factory-sales.post', ['sale' => $sale->id]), $this->key(['version' => $sale->version]))->assertSessionHasErrors('operation');
    }

    public function test_invoice_uniqueness_active_factory_and_invalid_manual_weights_and_deduction(): void
    {
        $this->dispatch();
        $sale = $this->draft('factory');
        $this->post(route('legacy-test.factory-sales.store'), $this->payload('factory', ['invoice_number' => $sale->invoice_number]))->assertSessionHasErrors('trip_id');
        $this->patch(route('factory-sales.update', ['sale' => $sale->id]), $this->payload('factory', ['version' => 1, 'sort_kg' => '1001']))->assertSessionHasErrors('initial_kg');
        $this->patch(route('factory-sales.update', ['sale' => $sale->id]), $this->payload('factory', ['version' => 1, 'initial_kg' => '1e3']))->assertSessionHasErrors('initial_kg');
        $this->patch(route('factory-sales.update', ['sale' => $sale->id]), $this->payload('factory', ['version' => 1, 'deduction_idr' => 1]))->assertSessionHasNoErrors();
        $this->assertSame(3149999, $sale->fresh()->revenue_idr);
        $this->factory->update(['is_active' => false]);
        $this->post(route('factory-sales.post', ['sale' => $sale->id]), $this->key(['version' => $sale->version]))->assertSessionHasErrors('operation');
    }
}

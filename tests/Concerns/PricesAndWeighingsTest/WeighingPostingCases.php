<?php

namespace Tests\Concerns\PricesAndWeighingsTest;

use App\Models\LoadingWorker;
use App\Models\PriceSchedule;
use App\Models\SortLot;
use App\Models\Trip;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

trait WeighingPostingCases
{
    public function test_posting_locks_prices_creates_entitlements_and_receipt_without_factory_weight(): void
    {
        $record = $this->postTicket($this->draft());
        $this->assertSame('posted', $record->status);
        $this->assertSame(3200000, $record->total_purchase_idr);
        $this->assertSame(110000, $record->total_loading_wage_idr);
        $this->assertSame([36667, 36667, 36666], $record->loaders->sortBy('allocation_order')->pluck('share_amount_idr')->values()->all());
        $this->assertSame(110000, $record->items->sum('loading_cost_allocated_idr'));
        $lot = SortLot::with('movements')->firstOrFail();
        $this->assertSame(100000, $lot->initial_grams);
        $this->assertSame(200000, $lot->initial_purchase_cost_idr);
        $this->assertSame(10000, $lot->initial_loading_cost_idr);
        $this->assertSame(100000, $lot->movements->sum('quantity_delta_grams'));
        $this->assertNull($this->trip->fresh()->manual_load_grams);
        $this->assertDatabaseCount('idempotency_requests', 1);
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'weighings', 'entity_id' => (string) $record->id, 'action' => 'post']);
    }

    public function test_draft_saves_without_prices_but_posting_never_falls_back_to_yesterday(): void
    {
        $this->travelTo(Carbon::parse('2026-10-10 03:00:00', 'UTC'));
        $record = $this->draft(['weighed_at' => '2026-10-10T10:00:00']);
        $this->get(route('weighings.show', $record))->assertInertia(fn (Assert $page) => $page->where('preview', null)->where('previewError', fn ($value) => str_contains($value, 'belum diterbitkan')));
        $this->post(route('weighings.post', $record), $this->posting($record))->assertSessionHasErrors('operation');
        $this->assertSame('draft', $record->fresh()->status);
        $this->assertDatabaseCount('sort_lots', 0);
    }

    public function test_changed_preview_requires_reconfirmation_and_driver_uses_current_server_time(): void
    {
        $record = $this->draft([], $this->driver);
        $this->travelTo(Carbon::parse('2026-10-09 03:02:00', 'UTC'));
        $this->actingAs($this->manager)->post(route('prices.store'), ['effective_from' => '2026-10-09T10:01:00', 'tbs_buy_price_idr' => 2900, 'sort_buy_price_idr' => 1800, 'loading_rate_idr' => 100])->assertSessionHasNoErrors();
        $this->actingAs($this->driver)->post(route('weighings.post', $record), $this->posting($record))->assertSessionHasErrors('operation');
        $this->assertSame('draft', $record->fresh()->status);
        $newPrice = PriceSchedule::latest('effective_from')->firstOrFail();
        $result = $this->postTicket($record, ['price_schedule_id' => $newPrice->id], $this->driver);
        $this->assertSame('2026-10-09 03:02:00', $result->weighed_at->format('Y-m-d H:i:s'));
        $this->assertSame(3080000, $result->total_purchase_idr);
    }

    public function test_idempotent_post_replays_once_and_rejects_changed_body(): void
    {
        $record = $this->draft();
        $data = $this->posting($record);
        $this->post(route('weighings.post', $record), $data)->assertSessionHasNoErrors();
        $auditCount = DB::table('audit_logs')->count();
        $this->post(route('weighings.post', $record), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('sort_lots', 1);
        $this->assertDatabaseCount('sort_stock_movements', 1);
        $this->assertDatabaseCount('idempotency_requests', 1);
        $this->assertDatabaseCount('audit_logs', $auditCount);
        $this->post(route('weighings.post', $record), array_replace($data, ['version' => 2]))->assertSessionHasErrors('operation');
        $this->post(route('weighings.post', $record), $this->posting($record))->assertSessionHasErrors('operation');
    }

    public function test_posting_ignores_client_totals_and_requires_workers(): void
    {
        $record = $this->draft(['worker_ids' => []]);
        $this->post(route('weighings.post', $record), $this->posting($record))->assertSessionHasErrors('operation');
        $record = $this->draft(['worker_ids' => [$this->workers[0]]]);
        $result = $this->postTicket($record, ['total_purchase_idr' => 1, 'total_loading_wage_idr' => 0]);
        $this->assertSame(3200000, $result->total_purchase_idr);
        $this->assertSame(110000, $result->loaders->first()->share_amount_idr);
    }

    public function test_manager_backdate_requires_reason_and_exact_daily_price(): void
    {
        $this->assertLegacyDraftErrors($this->payload(['weighed_at' => '2026-10-08T10:00:00']), 'backdate_reason');
        $record = $this->draft(['weighed_at' => '2026-10-08T10:00:00', 'backdate_reason' => 'Catatan nota kemarin']);
        $this->assertSame('2026-10-08', $record->business_date);
        $this->post(route('weighings.post', $record), $this->posting($record))->assertSessionHasErrors('operation');
        $this->assertLegacyDraftErrors($this->payload(['weighed_at' => '2026-02-30T10:00:00']), 'weighed_at');
    }

    public function test_active_references_and_trip_state_are_rechecked_at_posting(): void
    {
        $record = $this->draft();
        LoadingWorker::whereKey($this->workers[0])->update(['is_active' => false]);
        $this->post(route('weighings.post', $record), $this->posting($record))->assertSessionHasErrors('worker_ids');
        LoadingWorker::whereKey($this->workers[0])->update(['is_active' => true]);
        // Stage 3 rejects departure with a draft; a cancelled trip also stops intake.
        $this->trip->update(['status' => 'cancelled']);
        $this->post(route('weighings.post', $record), $this->posting($record))->assertSessionHasErrors('trip_id');
        $this->assertDatabaseCount('sort_lots', 0);
        $this->assertDatabaseCount('idempotency_requests', 0);
    }

    public function test_five_gram_categories_post_exactly_one_rupiah_of_loading_cost(): void
    {
        $record = $this->postTicket($this->draft(['tbs_kg' => '0.005', 'sort_kg' => '0.005', 'worker_ids' => array_reverse($this->workers)]));
        $this->assertSame(1, $record->total_loading_wage_idr);
        $this->assertSame(1, $record->items->sum('loading_cost_allocated_idr'));
        $this->assertSame(1, $record->loaders->sum('share_amount_idr'));
        $this->assertSame([1, 0, 0], $record->loaders->sortBy('allocation_order')->pluck('share_amount_idr')->values()->all());
    }

    public function test_reassigned_or_inactive_actor_cannot_replay_previous_post(): void
    {
        $record = $this->draft([], $this->driver);
        $data = $this->posting($record);
        $this->actingAs($this->driver)->post(route('weighings.post', $record), $data)->assertSessionHasNoErrors();
        $replacement = User::factory()->create();
        $this->trip->update(['driver_id' => $replacement->id]);
        $this->post(route('weighings.post', $record), $data)->assertForbidden();
    }
}

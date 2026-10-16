<?php

namespace Tests\Feature;

use App\Actions\Finance\PostFinance;
use App\Actions\Finance\ReverseFinance;
use App\Actions\Weighings\CreateDraftWeighing;
use App\Actions\Weighings\SaveDirectWeighing;
use App\Enums\FinanceKind;
use App\Enums\TransactionWorkflow;
use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\LoadingWorker;
use App\Models\PriceSchedule;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Weighing;
use App\Queries\PaymentBalances;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\DatabaseMigrations;
use Tests\Concerns\LegacyCustomerPayments;
use Tests\TestCase;

class DirectWeighingsTest extends TestCase
{
    use DatabaseMigrations;
    use LegacyCustomerPayments;

    public function runDatabaseMigrations(): void
    {
        // Real write transactions must remain outermost. Direct records deliberately prevent rollback migrations.
        $this->refreshTestDatabase();
        $this->beforeApplicationDestroyed(function () {
            RefreshDatabaseState::$migrated = false;
            DB::disconnect();
        });
    }

    private User $manager;

    private User $driver;

    private User $cashier;

    private Customer $customer;

    private Vehicle $vehicle;

    private array $workers;

    private PriceSchedule $price;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-09 03:00:00', 'UTC'));
        $this->manager = User::factory()->manager()->create();
        $this->driver = User::factory()->create();
        $this->cashier = User::factory()->cashier()->create();
        $this->customer = Customer::create(['code' => 'K1', 'name' => 'Konsumen Timbang', 'is_active' => true]);
        $this->vehicle = Vehicle::create(['code' => 'M1', 'plate_number' => 'BK DIRECT', 'is_active' => true]);
        $this->driver->update(['assigned_vehicle_id' => $this->vehicle->id]);
        $this->workers = collect(range(1, 3))->map(fn ($i) => LoadingWorker::create(['code' => 'A'.$i, 'name' => 'Anggota '.$i, 'is_active' => true])->id)->all();
        $this->price = PriceSchedule::create(['business_date' => '2026-10-09', 'effective_from' => '2026-10-08 17:00:00',
            'tbs_buy_price_idr' => 3000, 'sort_buy_price_idr' => 2000, 'loading_rate_idr' => 100, 'published_by' => $this->manager->id]);
    }

    private function payload(array $extra = []): array
    {
        return array_replace(['customer_id' => $this->customer->id, 'vehicle_id' => $this->vehicle->id,
            'vehicle_assignment_version' => 1,
            'tbs_kg' => '1000', 'sort_kg' => '100', 'worker_ids' => $this->workers,
            'price_schedule_id' => $this->price->id, 'idempotency_key' => (string) Str::uuid()], $extra);
    }

    private function save(array $extra = [], ?User $actor = null): Weighing
    {
        $this->actingAs($actor ?? $this->driver)->post(route('weighings.store'), $this->payload($extra))
            ->assertSessionHasNoErrors()->assertRedirect(route('weighings.index'))->assertSessionHas('saved_weighing');

        return Weighing::latest('id')->firstOrFail()->load('items', 'loaders');
    }

    private function price(array $extra = []): PriceSchedule
    {
        return PriceSchedule::create(array_replace(['business_date' => '2026-10-09', 'effective_from' => '2026-10-09 03:01:00',
            'tbs_buy_price_idr' => 2900, 'sort_buy_price_idr' => 1800, 'loading_rate_idr' => 120, 'published_by' => $this->manager->id], $extra));
    }

    public function test_driver_form_exposes_all_active_loading_workers_as_ordered_name_tags_only(): void
    {
        $expected = [];
        foreach ($this->workers as $index => $id) {
            $name = sprintf('Anggota %02d', $index + 1);
            LoadingWorker::whereKey($id)->update(['name' => $name, 'phone' => '08123456789', 'address' => 'Alamat pribadi']);
            $expected[$index + 1] = ['id' => $id, 'label' => $name];
        }
        foreach (range(30, 4) as $number) {
            $name = sprintf('Anggota %02d', $number);
            $worker = LoadingWorker::create(['code' => 'TAG-'.$number, 'name' => $name, 'is_active' => true,
                'phone' => '08123456789', 'address' => 'Alamat pribadi', 'joined_on' => '2026-01-01']);
            $expected[$number] = ['id' => $worker->id, 'label' => $name];
        }
        ksort($expected);
        $expected = array_values($expected);
        foreach (['Z-1', 'Z-2'] as $code) {
            $worker = LoadingWorker::create(['code' => $code, 'name' => 'Zul', 'is_active' => true]);
            $expected[] = ['id' => $worker->id, 'label' => 'Zul'];
        }
        LoadingWorker::create(['code' => 'NONAKTIF', 'name' => 'Anggota 00', 'is_active' => false]);

        $this->actingAs($this->driver)->get(route('weighings.create'))
            ->assertInertia(fn (Assert $page) => $page->component('Weighings/Form')
                ->has('availableWorkers', 32)
                ->where('availableWorkers', $expected));
        $this->actingAs($this->manager)->get(route('weighings.create'))
            ->assertInertia(fn (Assert $page) => $page->where('availableWorkers', []));
        $this->actingAs($this->cashier)->get(route('weighings.create'))->assertForbidden();
    }

    public function test_live_preview_computes_money_and_rounded_wages_without_writing_documents(): void
    {
        $this->actingAs($this->driver)->postJson(route('weighings.preview'), ['tbs_kg' => '1000', 'sort_kg' => '100', 'worker_ids' => array_reverse($this->workers)])
            ->assertOk()->assertJsonPath('preview.total_purchase_idr', 3200000)->assertJsonPath('preview.total_loading_wage_idr', 110000)
            ->assertJsonPath('preview.loaders.0.share_amount_idr', 36667)->assertJsonPath('preview.loaders.2.share_amount_idr', 36666)
            ->assertJsonPath('price.tbs_buy_price_idr', 3000);
        $this->postJson(route('weighings.preview'), ['tbs_kg' => '0', 'sort_kg' => '0', 'worker_ids' => []])->assertOk()->assertJsonPath('preview', null)->assertJsonPath('price.id', $this->price->id);
        foreach (['weighings', 'weighing_items', 'weighing_loaders', 'trips', 'sort_lots', 'sort_stock_movements', 'idempotency_requests', 'audit_logs'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_one_save_posts_direct_weighing_without_trip_or_stock_and_ignores_client_totals(): void
    {
        $ticket = $this->save(['total_purchase_idr' => 1, 'total_loading_wage_idr' => 0, 'created_by' => $this->manager->id]);
        $this->assertSame('posted', $ticket->status);
        $this->assertSame(TransactionWorkflow::Direct, $ticket->workflow);
        $this->assertSame($this->vehicle->id, $ticket->vehicle_id);
        $this->assertNull($ticket->trip_id);
        $this->assertSame($this->driver->id, $ticket->created_by);
        $this->assertSame(3200000, $ticket->total_purchase_idr);
        $this->assertSame(110000, $ticket->total_loading_wage_idr);
        $this->assertSame([36667, 36667, 36666], $ticket->loaders->sortBy('allocation_order')->pluck('share_amount_idr')->values()->all());
        $this->assertSame(110000, $ticket->items->sum('loading_cost_allocated_idr'));
        $this->assertSame('2026-10-09 03:00:00', $ticket->weighed_at->format('Y-m-d H:i:s'));
        foreach (['trips', 'sort_lots', 'sort_stock_movements', 'factory_returns', 'cash_entries', 'customer_payments', 'payroll_payments'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        $this->get(route('weighings.show', $ticket))->assertInertia(fn (Assert $p) => $p
            ->where('record.trip', null)->where('record.vehicle.plate_number', 'BK DIRECT')->where('record.payment_status', 'unpaid')->where('preview', null));
        $this->get(route('weighings.index'))->assertInertia(fn (Assert $p) => $p->has('weighings.data', 1)
            ->where('weighings.data.0.payment_status', 'unpaid')->has('weighings.data.0.items', 2)->has('weighings.data.0.loaders', 3));
        $this->post(route('weighings.post', $ticket), ['version' => 2, 'price_schedule_id' => $this->price->id, 'idempotency_key' => (string) Str::uuid()])->assertSessionHasErrors('operation');
        $this->get(route('weighings.edit', $ticket))->assertForbidden();
    }

    public function test_single_member_sort_zero_and_sort_only_support_exact_decimal_and_zero_rate(): void
    {
        $ticket = $this->save(['tbs_kg' => '1,005', 'sort_kg' => '0', 'worker_ids' => [$this->workers[0]]]);
        $this->assertSame(3015, $ticket->total_purchase_idr);
        $this->assertSame(101, $ticket->total_loading_wage_idr);
        $this->assertSame(101, $ticket->loaders->first()->share_amount_idr);
        $this->assertCount(1, $ticket->items);
        $two = $this->save(['tbs_kg' => '1.005', 'sort_kg' => '0', 'worker_ids' => array_slice($this->workers, 0, 2)]);
        $this->assertSame([51, 50], $two->loaders->pluck('share_amount_idr')->all());
        $this->price(['loading_rate_idr' => 0]);
        $this->travelTo(Carbon::parse('2026-10-09 03:02:00', 'UTC'));
        $price = PriceSchedule::latest('effective_from')->firstOrFail();
        $ticket = $this->save(['tbs_kg' => '0', 'sort_kg' => '2.5', 'price_schedule_id' => $price->id]);
        $this->assertSame(4500, $ticket->total_purchase_idr);
        $this->assertSame([0, 0, 0], $ticket->loaders->pluck('share_amount_idr')->all());
    }

    public function test_price_change_requires_new_preview_confirmation_and_failed_save_leaves_no_draft(): void
    {
        $this->actingAs($this->driver)->postJson(route('weighings.preview'), ['tbs_kg' => '1000', 'sort_kg' => '100', 'worker_ids' => $this->workers])->assertJsonPath('price.id', $this->price->id);
        $next = $this->price();
        $this->travelTo(Carbon::parse('2026-10-09 03:02:00', 'UTC'));
        $payload = $this->payload();
        $this->post(route('weighings.store'), $payload)->assertSessionHasErrors('price_schedule_id');
        foreach (['weighings', 'document_sequences', 'idempotency_requests', 'audit_logs'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->postJson(route('weighings.preview'), ['tbs_kg' => '1000', 'sort_kg' => '100', 'worker_ids' => $this->workers])
            ->assertJsonPath('price.id', $next->id)->assertJsonPath('preview.total_purchase_idr', 3080000);
        $this->post(route('weighings.store'), array_replace($payload, ['price_schedule_id' => $next->id]))->assertSessionHasNoErrors();
        $this->assertSame(3080000, Weighing::firstOrFail()->total_purchase_idr);
    }

    public function test_next_day_price_keeps_previous_entitlements_locked(): void
    {
        $first = $this->save();
        $this->travelTo(Carbon::parse('2026-10-10 03:00:00', 'UTC'));
        $next = $this->price(['business_date' => '2026-10-10', 'effective_from' => '2026-10-09 17:00:00']);
        $second = $this->save(['price_schedule_id' => $next->id]);
        $this->assertSame(3200000, $first->fresh()->total_purchase_idr);
        $this->assertSame(3080000, $second->total_purchase_idr);
        $this->assertSame(110000, $first->fresh()->total_loading_wage_idr);
        $this->assertSame(132000, $second->total_loading_wage_idr);
        $this->actingAs($this->cashier)->get(route('customer-payments.payee', $this->customer))->assertInertia(fn (Assert $p) => $p->where('totals.remaining_idr', 6280000));
    }

    public function test_missing_daily_price_blocks_preview_and_save_without_creating_draft(): void
    {
        $this->travelTo(Carbon::parse('2026-10-10 03:00:00', 'UTC'));
        $this->actingAs($this->driver)->postJson(route('weighings.preview'), ['tbs_kg' => '1', 'sort_kg' => '0', 'worker_ids' => $this->workers])->assertUnprocessable()->assertJsonValidationErrors('price_schedule_id');
        $this->post(route('weighings.store'), $this->payload())->assertSessionHasErrors('price_schedule_id');
        $this->assertDatabaseCount('weighings', 0);
        $this->assertDatabaseCount('idempotency_requests', 0);
    }

    public function test_inactive_references_empty_workers_duplicates_and_invalid_weights_are_rejected(): void
    {
        $this->actingAs($this->driver);
        foreach ([$this->customer, $this->vehicle, LoadingWorker::findOrFail($this->workers[0])] as $i => $record) {
            $record->update(['is_active' => false]);
            $this->post(route('weighings.store'), $this->payload())->assertSessionHasErrors(['customer_id', 'vehicle_id', 'worker_ids'][$i]);
            $record->update(['is_active' => true]);
        }
        foreach ([['worker_ids' => []], ['worker_ids' => [$this->workers[0], $this->workers[0]]], ['tbs_kg' => '0', 'sort_kg' => '0'], ['tbs_kg' => '-1'], ['tbs_kg' => '1.0000'], ['sort_kg' => '1000001']] as $extra) {
            $this->post(route('weighings.store'), $this->payload($extra))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('weighings', 0);
        $this->assertDatabaseCount('idempotency_requests', 0);
    }

    public function test_new_form_rejects_trip_and_client_time_for_both_roles(): void
    {
        foreach ([$this->manager, $this->driver] as $actor) {
            $this->actingAs($actor)->get(route('weighings.create'))->assertInertia(fn (Assert $p) => $p
                ->component('Weighings/Form')->has('selectedVehicle')->missing('selectedTrip')->missing('localNow')->missing('record'));
            $this->post(route('weighings.store'), $this->payload(['trip_id' => 999, 'weighed_at' => '2026-10-08T10:00:00', 'backdate_reason' => 'Mundur']))
                ->assertSessionHasErrors(['trip_id', 'weighed_at', 'backdate_reason']);
        }
        $this->assertDatabaseCount('weighings', 0);
    }

    public function test_permissions_follow_creator_and_cashier_cannot_preview_or_save(): void
    {
        $ticket = $this->save();
        $other = User::factory()->create();
        $this->vehicle->update(['default_driver_id' => $other->id]);
        $this->actingAs($other)->get(route('weighings.show', $ticket))->assertForbidden();
        $this->get(route('weighings.index', ['q' => $this->customer->name]))->assertInertia(fn (Assert $p) => $p->has('weighings.data', 0));
        $this->actingAs($this->driver)->get(route('weighings.index'))->assertInertia(fn (Assert $p) => $p->has('weighings.data', 1));
        $this->actingAs($this->cashier)->get(route('weighings.show', $ticket))->assertOk();
        $this->get(route('weighings.create'))->assertForbidden();
        $this->post(route('weighings.store'), $this->payload())->assertForbidden();
        $this->postJson(route('weighings.preview'), ['tbs_kg' => '1', 'sort_kg' => '0', 'worker_ids' => []])->assertForbidden();
        $this->save([], $this->manager);
        $this->actingAs($this->manager)->get(route('weighings.index'))->assertInertia(fn (Assert $p) => $p->has('weighings.data', 2));
        $this->actingAs($this->driver)->get(route('weighings.index'))->assertInertia(fn (Assert $p) => $p->has('weighings.data', 1));
    }

    public function test_duplicate_submission_replays_without_new_number_entitlement_or_audit(): void
    {
        $payload = $this->payload();
        $this->actingAs($this->driver)->post(route('weighings.store'), $payload)->assertSessionHasNoErrors();
        $this->customer->update(['is_active' => false]);
        $this->price();
        $this->travelTo(Carbon::parse('2026-10-09 03:02:00', 'UTC'));
        $this->post(route('weighings.store'), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('weighings', 1);
        $this->assertDatabaseCount('weighing_items', 2);
        $this->assertDatabaseCount('weighing_loaders', 3);
        $this->assertDatabaseCount('idempotency_requests', 1);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->post(route('weighings.store'), array_replace($payload, ['tbs_kg' => '1']))->assertSessionHasErrors('operation');
        $this->assertSame(3200000, Weighing::firstOrFail()->total_purchase_idr);
    }

    public function test_audit_failure_rolls_back_whole_save_including_number_and_entitlements(): void
    {
        $this->mock(AuditService::class, function ($mock) {
            $mock->shouldReceive('snapshot')->andReturn([]);
            $mock->shouldReceive('record')->andThrow(new \RuntimeException('audit failure'));
        });
        try {
            app(SaveDirectWeighing::class)->handle($this->driver, $this->payload());
            $this->fail('Expected audit failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('audit failure', $e->getMessage());
        }
        foreach (['weighings', 'weighing_items', 'weighing_loaders', 'document_sequences', 'idempotency_requests', 'audit_logs'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_legacy_customer_partial_and_payroll_payments_keep_snapshots_and_block_void_until_reversed(): void
    {
        $ticket = $this->save(['worker_ids' => [$this->workers[0]]]);
        $account = CashAccount::create(['code' => 'C1', 'name' => 'Kas Uji', 'type' => 'cash', 'is_active' => true]);
        $this->actingAs($this->manager)->post(route('cash.store'), ['cash_account_id' => $account->id, 'kind' => 'opening', 'amount_idr' => 10000000,
            'occurred_at' => '2026-10-09T09:00:00', 'reason' => 'Saldo awal', 'idempotency_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $common = ['cash_account_id' => $account->id, 'method' => 'cash', 'occurred_at' => '2026-10-09T10:00:00'];
        $customerPayment = $this->legacyCustomerPayment($this->cashier, $account, [$ticket->id => 1000000]);
        $payroll = app(PostFinance::class)->handle($this->cashier, FinanceKind::Payroll, $common + ['recipient_id' => $this->workers[0],
            'selected_ids' => [$ticket->loaders->first()->id], 'period_start' => '2026-10-01', 'period_end' => '2026-10-31',
            'expected_remaining_idr' => 110000, 'amount_idr' => 50000, 'idempotency_key' => (string) Str::uuid()]);
        $this->actingAs($this->cashier)->get(route('weighings.show', $ticket))->assertInertia(fn (Assert $p) => $p->where('record.payment_status', 'partial')->where('record.remaining_idr', 2200000));
        $this->get(route('customer-payments.payee', $this->customer))->assertInertia(fn (Assert $p) => $p->where('totals.remaining_idr', 2200000));
        $this->get(route('payroll.payee', $this->workers[0]))->assertInertia(fn (Assert $p) => $p->where('totals.remaining_idr', 60000));
        $customerFinal = app(PostFinance::class)->handle($this->cashier, FinanceKind::Customer, $common + ['recipient_id' => $this->customer->id,
            'selected_ids' => [$ticket->id], 'expected_remaining_idr' => 2200000, 'amount_idr' => 2200000, 'idempotency_key' => (string) Str::uuid()]);
        $payrollFinal = app(PostFinance::class)->handle($this->cashier, FinanceKind::Payroll, $common + ['recipient_id' => $this->workers[0],
            'selected_ids' => [$ticket->loaders->first()->id], 'period_start' => '2026-10-01', 'period_end' => '2026-10-31',
            'expected_remaining_idr' => 60000, 'amount_idr' => 60000, 'idempotency_key' => (string) Str::uuid()]);
        $this->get(route('weighings.show', $ticket))->assertInertia(fn (Assert $p) => $p->where('record.payment_status', 'paid')->where('record.remaining_idr', 0));
        $this->get(route('customer-payments.index', ['status' => 'paid']))->assertInertia(fn (Assert $p) => $p->has('cards.data', 0)->where('filters.status', 'unpaid'));
        $this->get(route('customer-payments.payee', $this->customer))->assertInertia(fn (Assert $p) => $p->has('rows.data', 0)->has('history.data', 2)->where('totals.remaining_idr', 0));
        $this->get(route('payroll.payee', $this->workers[0]))->assertInertia(fn (Assert $p) => $p->where('totals.remaining_idr', 0));
        $void = ['version' => 2, 'reason' => 'Berat salah', 'idempotency_key' => (string) Str::uuid()];
        $this->actingAs($this->manager)->post(route('weighings.void', $ticket), $void)->assertSessionHasErrors('operation');
        foreach ([[FinanceKind::Customer, $customerPayment], [FinanceKind::Payroll, $payroll], [FinanceKind::Customer, $customerFinal], [FinanceKind::Payroll, $payrollFinal]] as [$kind, $document]) {
            app(ReverseFinance::class)->handle($this->manager, $kind, $document, ['version' => $document->version,
                'reason' => 'Koreksi timbang', 'return_reference' => 'Uang dikembalikan', 'idempotency_key' => (string) Str::uuid()]);
        }
        $this->post(route('weighings.void', $ticket), $void)->assertSessionHasNoErrors();
        $this->post(route('weighings.void', $ticket), $void)->assertSessionHasNoErrors();
        $this->assertSame('voided', $ticket->fresh()->status);
        $this->assertSame(3200000, $ticket->fresh()->total_purchase_idr);
        $this->assertSame(0, app(PaymentBalances::class)->customers()->count());
        $this->assertSame(0, app(PaymentBalances::class)->payroll()->count());
        $this->assertDatabaseCount('sort_lots', 0);
        $this->save(['replaces_weighing_id' => $ticket->id, 'tbs_kg' => '999'], $this->manager);
        $this->post(route('weighings.store'), $this->payload(['replaces_weighing_id' => $ticket->id]))->assertSessionHasErrors('replaces_weighing_id');
        $this->assertDatabaseCount('weighings', 2);
    }

    public function test_list_summary_uses_all_filtered_tickets_and_respects_driver_scope(): void
    {
        foreach (range(1, 21) as $i) {
            $this->save(['tbs_kg' => '1', 'sort_kg' => '0']);
        }
        $this->save(['tbs_kg' => '2', 'sort_kg' => '0'], $this->manager);

        $this->actingAs($this->driver)->get(route('weighings.index', [
            'q' => $this->customer->name, 'status' => 'posted', 'from' => '2026-10-09', 'to' => '2026-10-09', 'page' => 2,
        ]))->assertInertia(fn (Assert $p) => $p->has('weighings.data', 1)
            ->where('weighings.total', 21)
            ->where('summary.ticket_count', 21)
            ->where('summary.weight_grams', 21000)
            ->where('summary.purchase_idr', 63000)
            ->where('summary.loading_wage_idr', 2100));

        $this->get(route('weighings.index', ['q' => 'Tidak ditemukan']))->assertInertia(fn (Assert $p) => $p
            ->where('summary.ticket_count', 0)->where('summary.weight_grams', 0)
            ->where('summary.purchase_idr', 0)->where('summary.loading_wage_idr', 0));
        $this->get(route('weighings.index', ['from' => '2026-10-10']))->assertInertia(fn (Assert $p) => $p
            ->where('summary.ticket_count', 0)->where('weighings.total', 0));
    }

    public function test_list_summary_excludes_voided_tickets_and_legacy_drafts(): void
    {
        $voided = $this->save();
        $this->actingAs($this->manager)->post(route('weighings.void', $voided), [
            'version' => $voided->version, 'reason' => 'Berat salah', 'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();
        $this->save(['tbs_kg' => '2.5', 'sort_kg' => '0']);
        $trip = Trip::create(['number' => 'SUMMARY-TRIP', 'vehicle_id' => $this->vehicle->id, 'driver_id' => $this->driver->id,
            'business_date' => '2026-10-09', 'status' => 'loading', 'created_by' => $this->manager->id]);
        app(CreateDraftWeighing::class)->handle($this->manager, ['customer_id' => $this->customer->id, 'trip_id' => $trip->id,
            'tbs_kg' => '1', 'sort_kg' => '0', 'worker_ids' => $this->workers, 'weighed_at' => '2026-10-09T10:00:00']);

        $this->actingAs($this->driver)->get(route('weighings.index'))->assertInertia(fn (Assert $p) => $p
            ->where('weighings.total', 3)->where('summary.ticket_count', 1)->where('summary.weight_grams', 2500)
            ->where('summary.purchase_idr', 7500)->where('summary.loading_wage_idr', 250));
        $this->actingAs($this->cashier)->get(route('weighings.index'))->assertInertia(fn (Assert $p) => $p
            ->where('weighings.total', 2)->where('summary.ticket_count', 1)->where('summary.purchase_idr', 7500));
        $this->actingAs($this->manager)->get(route('weighings.index', ['status' => 'draft']))->assertInertia(fn (Assert $p) => $p
            ->where('weighings.total', 1)->where('summary.ticket_count', 0)->where('summary.purchase_idr', 0));
        $this->get(route('weighings.index', ['status' => 'voided']))->assertInertia(fn (Assert $p) => $p
            ->where('weighings.total', 1)->where('summary.ticket_count', 0)->where('summary.loading_wage_idr', 0));
    }

    public function test_existing_legacy_draft_and_direct_records_share_readable_history(): void
    {
        $trip = Trip::create(['number' => 'LEGACY-TRIP', 'vehicle_id' => $this->vehicle->id, 'driver_id' => $this->driver->id,
            'business_date' => '2026-10-09', 'status' => 'loading', 'created_by' => $this->manager->id]);
        $legacy = app(CreateDraftWeighing::class)->handle($this->manager, ['customer_id' => $this->customer->id, 'trip_id' => $trip->id,
            'tbs_kg' => '1', 'sort_kg' => '0', 'worker_ids' => $this->workers, 'weighed_at' => '2026-10-09T10:00:00']);
        $this->save();
        $this->actingAs($this->manager)->get(route('weighings.edit', $legacy))->assertInertia(fn (Assert $p) => $p->component('Weighings/LegacyForm')->where('record.workflow', 'legacy'));
        $this->get(route('weighings.show', $legacy))->assertOk();
        $this->get(route('weighings.index'))->assertInertia(fn (Assert $p) => $p->has('weighings.data', 2)->where('weighings.data.1.vehicle.plate_number', 'BK DIRECT'));
        $this->actingAs($this->driver)->get(route('weighings.index'))->assertInertia(fn (Assert $p) => $p->has('weighings.data', 2));
        $this->actingAs($this->cashier)->get(route('weighings.index'))->assertInertia(fn (Assert $p) => $p->has('weighings.data', 1));
    }
}

<?php

namespace Tests\Concerns\PricesAndWeighingsTest;

use App\Actions\Prices\PublishPrice;
use App\Enums\UserRole;
use App\Models\Factory;
use App\Models\FactoryPriceSchedule;
use App\Models\PriceSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait PricePublicationCases
{
    public function test_next_day_price_does_not_change_previous_ticket_and_is_used_for_new_ticket(): void
    {
        $first = $this->postTicket($this->draft());
        $this->travelTo(Carbon::parse('2026-10-10 03:00:00', 'UTC'));
        $data = ['effective_from' => '2026-10-10T00:00:00', 'tbs_buy_price_idr' => 2900,
            'sort_buy_price_idr' => 1900, 'loading_rate_idr' => 120, 'notes' => 'Tarif baru oleh kasir'];
        $this->actingAs($this->cashier)->post(route('prices.store'), $data)
            ->assertRedirect(route('prices.index'))->assertSessionHasNoErrors();
        $nextPrice = PriceSchedule::latest('effective_from')->firstOrFail();
        $this->assertSame($this->cashier->id, $nextPrice->published_by);
        $this->assertSame('Tarif baru oleh kasir', $nextPrice->notes);
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'price_schedules', 'entity_id' => (string) $nextPrice->id,
            'actor_id' => $this->cashier->id, 'action' => 'publish', 'before_json' => null]);
        $audit = DB::table('audit_logs')->where('entity_type', 'price_schedules')->where('entity_id', (string) $nextPrice->id)->first();
        $snapshot = json_decode($audit->after_json, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(2900, $snapshot['tbs_buy_price_idr']);
        $this->assertSame(1900, $snapshot['sort_buy_price_idr']);
        $this->assertSame(120, $snapshot['loading_rate_idr']);
        $this->assertSame($this->cashier->id, $snapshot['published_by']);
        $this->post(route('prices.store'), $data)->assertSessionHasErrors('effective_from');
        $this->assertDatabaseCount('price_schedules', 2);
        $this->assertSame(1, DB::table('audit_logs')->where('entity_type', 'price_schedules')->count());
        $second = $this->postTicket($this->draft(['weighed_at' => '2026-10-10T10:00:00']), ['price_schedule_id' => $nextPrice->id]);
        $this->assertSame(3200000, $first->fresh()->total_purchase_idr);
        $this->assertSame(3000, $first->items->firstWhere('fruit_type', 'TBS')->buy_price_snapshot_idr);
        $this->assertSame(100, $first->fresh()->loading_rate_snapshot_idr);
        $this->assertSame(110000, $first->fresh()->total_loading_wage_idr);
        $this->assertSame(3090000, $second->total_purchase_idr);
        $this->assertSame(2900, $second->items->firstWhere('fruit_type', 'TBS')->buy_price_snapshot_idr);
        $this->assertSame(1900, $second->items->firstWhere('fruit_type', 'SORT')->buy_price_snapshot_idr);
        $this->assertSame(120, $second->loading_rate_snapshot_idr);
        $this->assertSame(132000, $second->total_loading_wage_idr);
    }

    public function test_price_publication_cannot_cross_posted_or_voided_history(): void
    {
        $record = $this->postTicket($this->draft());
        $data = ['effective_from' => '2026-10-09T09:59:59', 'tbs_buy_price_idr' => 2900, 'sort_buy_price_idr' => 2000, 'loading_rate_idr' => 100];
        $this->actingAs($this->cashier)->post(route('prices.store'), $data)->assertSessionHasErrors('effective_from');
        $this->actingAs($this->manager)->post(route('weighings.void', $record), ['version' => 2, 'reason' => 'Salah catat', 'idempotency_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->actingAs($this->cashier)->post(route('prices.store'), $data)->assertSessionHasErrors('effective_from');
        $this->post(route('prices.store'), array_replace($data, ['effective_from' => '2026-10-09T10:01:00']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('price_schedules', 2);
    }

    public function test_price_publication_rechecks_cashier_role_and_active_status_inside_the_action(): void
    {
        $factory = Factory::create(['code' => 'P-1', 'name' => 'Pabrik Satu', 'is_active' => true]);
        $data = ['effective_from' => '2026-10-09T10:01:00', 'tbs_buy_price_idr' => 2900,
            'sort_buy_price_idr' => 1900, 'loading_rate_idr' => 120];
        $factoryData = ['factory_id' => $factory->id, 'effective_from' => '2026-10-09T10:01:00', 'tbs_sell_price_idr' => 3500];
        foreach ([['is_active' => false], ['is_active' => true, 'role' => UserRole::Driver]] as $changes) {
            User::query()->whereKey($this->cashier->id)->update($changes);
            foreach ([false, true] as $factoryPrice) {
                try {
                    app(PublishPrice::class)->handle($this->cashier, $factoryPrice ? $factoryData : $data, $factoryPrice);
                    $this->fail('Expected publication authorization to use the current cashier account');
                } catch (AuthorizationException $exception) {
                    $this->assertDatabaseCount('price_schedules', 1);
                    $this->assertDatabaseCount('factory_price_schedules', 0);
                    $this->assertSame(0, DB::table('audit_logs')->whereIn('entity_type', ['price_schedules', 'factory_price_schedules'])->count());
                }
            }
        }
    }

    public function test_factory_price_versions_are_separate_and_used_masters_cannot_be_deleted(): void
    {
        $factory = Factory::create(['code' => 'P-1', 'name' => 'Pabrik Satu', 'is_active' => true]);
        $data = ['factory_id' => $factory->id, 'effective_from' => '2026-10-09T09:00:00', 'tbs_sell_price_idr' => 3500];
        $this->post(route('prices.factory-store'), $data)->assertSessionHasNoErrors();
        $this->post(route('prices.factory-store'), $data)->assertSessionHasErrors('effective_from');
        $this->assertDatabaseCount('factory_price_schedules', 1);
        $this->assertDatabaseCount('price_schedules', 1);
        $this->draft();
        $this->delete(route('master-data.destroy', ['customers', $this->customer->id]))->assertSessionHasErrors('operation');
        $this->patch(route('master-data.status', ['customers', $this->customer->id]), ['is_active' => false])->assertSessionHasNoErrors();
    }

    public function test_cashier_can_publish_factory_price_versions_with_audit_and_retained_history(): void
    {
        $factory = Factory::create(['code' => 'P-1', 'name' => 'Pabrik Satu', 'is_active' => true]);
        $data = ['factory_id' => $factory->id, 'effective_from' => '2026-10-09T09:00:00', 'tbs_sell_price_idr' => 3500];
        $this->post(route('prices.factory-store'), $data)->assertSessionHasNoErrors();
        $previous = FactoryPriceSchedule::firstOrFail();

        $newData = array_replace($data, ['effective_from' => '2026-10-09T10:00:00', 'tbs_sell_price_idr' => 3700]);
        $this->actingAs($this->cashier)->post(route('prices.factory-store'), $newData)
            ->assertRedirect(route('prices.index'))->assertSessionHasNoErrors();
        $current = FactoryPriceSchedule::latest('effective_from')->firstOrFail();
        $this->assertNotSame($previous->id, $current->id);
        $this->assertSame(3700, $current->tbs_sell_price_idr);
        $this->assertSame($this->cashier->id, $current->published_by);
        $this->assertSame($factory->id, $current->factory_id);
        $this->assertSame('2026-10-09 03:00:00', $current->effective_from->format('Y-m-d H:i:s'));
        $this->assertSame(3500, $previous->fresh()->tbs_sell_price_idr);
        $this->assertSame($this->manager->id, $previous->fresh()->published_by);
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'factory_price_schedules', 'entity_id' => (string) $current->id,
            'actor_id' => $this->cashier->id, 'action' => 'publish', 'before_json' => null]);
        $audit = DB::table('audit_logs')->where('entity_type', 'factory_price_schedules')->where('entity_id', (string) $current->id)->first();
        $snapshot = json_decode($audit->after_json, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(3700, $snapshot['tbs_sell_price_idr']);
        $this->assertSame($factory->id, $snapshot['factory_id']);
        $this->assertSame($this->cashier->id, $snapshot['published_by']);

        $this->post(route('prices.factory-store'), $newData)->assertSessionHasErrors('effective_from');
        $this->assertDatabaseCount('factory_price_schedules', 2);
        $this->assertDatabaseCount('price_schedules', 1);
        $this->assertSame(2, DB::table('audit_logs')->where('entity_type', 'factory_price_schedules')->count());
    }

    public function test_cashier_factory_price_publication_requires_an_active_existing_factory(): void
    {
        $factory = Factory::create(['code' => 'P-1', 'name' => 'Pabrik Satu', 'is_active' => false]);
        $data = ['effective_from' => '2026-10-09T10:00:00', 'tbs_sell_price_idr' => 3700];
        $this->actingAs($this->cashier);
        foreach ([$factory->id, $factory->id + 1000] as $factoryId) {
            $this->post(route('prices.factory-store'), array_replace($data, ['factory_id' => $factoryId]))
                ->assertSessionHasErrors('factory_id');
        }
        $this->assertDatabaseCount('factory_price_schedules', 0);
        $this->assertSame(0, DB::table('audit_logs')->where('entity_type', 'factory_price_schedules')->count());
    }
}

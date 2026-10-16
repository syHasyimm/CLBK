<?php

namespace Tests\Feature;

use App\Models\PriceSchedule;
use App\Models\User;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\DatabaseMigrations;
use Tests\TestCase;

class PricePageTest extends TestCase
{
    use DatabaseMigrations;

    public function test_current_price_uses_latest_effective_daily_version_independently_of_history_page(): void
    {
        $this->travelTo(Carbon::parse('2026-10-10 03:30:00', 'UTC'));
        $manager = User::factory()->manager()->create();
        $this->publish($manager, '2026-10-10', '2026-10-10 01:00:00');
        $current = $this->publish($manager, '2026-10-10', '2026-10-10 03:00:00', 3200);
        $future = $this->publish($manager, '2026-10-10', '2026-10-10 05:00:00', 3500);
        foreach (range(1, 16) as $day) {
            $date = Carbon::parse('2026-10-10', 'UTC')->subDays($day)->toDateString();
            $this->publish($manager, $date, $date.' 01:00:00');
        }

        $this->actingAs($manager)->get(route('prices.index', ['page' => 2]))
            ->assertInertia(fn (Assert $page) => $page->component('Prices/Index')
                ->where('currentPrice.id', $current->id)
                ->where('currentPrice.tbs_buy_price_idr', 3200)
                ->where('currentPrice.loading_rate_idr', 0)
                ->where('can.publishPrice', true)
                ->where('can.publishFactoryPrice', true)
                ->where('prices.current_page', 2)
                ->where('prices.total', 19)
                ->where('serverNow', '2026-10-10T03:30:00+00:00')
                ->where('localNow', '2026-10-10T10:30:00'));

        $this->travelTo(Carbon::parse('2026-10-10 05:00:00', 'UTC'));
        $this->get(route('prices.index'))->assertInertia(fn (Assert $page) => $page
            ->where('currentPrice.id', $future->id));
    }

    public function test_current_price_does_not_fall_back_to_yesterday_or_use_future_price(): void
    {
        $this->travelTo(Carbon::parse('2026-10-09 17:30:00', 'UTC'));
        $manager = User::factory()->manager()->create();
        $this->publish($manager, '2026-10-09', '2026-10-09 16:00:00');
        $this->publish($manager, '2026-10-10', '2026-10-09 18:00:00');

        $cashier = User::factory()->cashier()->create();
        $this->actingAs($cashier)->get(route('prices.index'))
            ->assertInertia(fn (Assert $page) => $page->component('Prices/Index')
                ->where('currentPrice', null)
                ->where('prices.total', 2)
                ->where('localNow', '2026-10-10T00:30:00'));
    }

    public function test_driver_cannot_open_prices_but_keeps_operational_pages_and_profile(): void
    {
        $driver = User::factory()->create();
        $this->actingAs($driver)->get(route('prices.index'))->assertForbidden();
        foreach (['dashboard', 'weighings.index', 'factory-sales.index', 'sort-sales.index', 'profile.edit'] as $name) {
            $this->get(route($name))->assertOk();
        }
        $this->post(route('prices.store'), [])->assertForbidden();
        $this->post(route('prices.factory-store'), [])->assertForbidden();
    }

    public function test_cashier_can_publish_purchase_and_factory_prices_without_managing_settings_or_masters(): void
    {
        $cashier = User::factory()->cashier()->create();
        $this->actingAs($cashier);
        foreach (['dashboard', 'prices.index', 'reports.index', 'profile.edit', 'customer-payments.index', 'payroll.index', 'daily-expenses.index', 'cash.index'] as $name) {
            $this->get(route($name))->assertOk();
        }
        $this->get(route('prices.index'))
            ->assertInertia(fn (Assert $page) => $page->component('Prices/Index')
                ->where('can.publishPrice', true)
                ->where('can.publishFactoryPrice', true));
        $this->post(route('prices.store'), [])->assertSessionHasErrors([
            'effective_from', 'tbs_buy_price_idr', 'sort_buy_price_idr', 'loading_rate_idr',
        ]);
        $this->post(route('prices.factory-store'), [])->assertSessionHasErrors([
            'factory_id', 'effective_from', 'tbs_sell_price_idr',
        ]);
        $this->get(route('settings.business.edit'))->assertForbidden();
        $this->patch(route('settings.business.update'), [])->assertForbidden();
        $this->get(route('master-data.index', 'factories'))->assertForbidden();
        $this->post(route('master-data.store', 'factories'), [])->assertForbidden();
    }

    private function publish(User $manager, string $date, string $time, int $tbs = 3000): PriceSchedule
    {
        return PriceSchedule::create([
            'business_date' => $date,
            'effective_from' => $time,
            'tbs_buy_price_idr' => $tbs,
            'sort_buy_price_idr' => 2000,
            'loading_rate_idr' => 0,
            'published_by' => $manager->id,
        ]);
    }
}

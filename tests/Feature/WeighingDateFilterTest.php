<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Weighing;
use App\Support\BusinessClock;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleUserSeeder;
use Database\Seeders\WeighingDateFilterSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\Concerns\DatabaseMigrations;
use Tests\TestCase;

class WeighingDateFilterTest extends TestCase
{
    use DatabaseMigrations;

    public function test_fixture_has_a_vehicle_and_known_weighings_and_date_filter_includes_wib_boundaries_for_each_role(): void
    {
        $this->seed();
        $vehicle = Vehicle::where('code', 'UAT-DATE-M01')->sole();
        $this->assertTrue($vehicle->is_active);
        $this->assertSame('BK 2026 UAT', $vehicle->plate_number);
        $this->assertDatabaseCount('weighings', 5);
        $this->assertSame(['2026-10-09', '2026-10-10', '2026-10-11', '2026-10-12', '2026-10-13'],
            Weighing::orderBy('business_date')->pluck('business_date')->all());
        $this->assertTrue(Weighing::where('business_date', '2026-10-10')->sole()->weighed_at->equalTo(CarbonImmutable::parse('2026-10-09T17:00:00Z')));

        foreach (['manager@clbk.test', 'supir@clbk.test', 'kasir@clbk.test'] as $email) {
            $this->flushSession();
            $this->actingAs(User::where('email', $email)->sole());
            $this->get(route('weighings.index'))->assertInertia(fn (Assert $page) => $page->where('weighings.total', 5));
            $this->get(route('weighings.index', ['from' => WeighingDateFilterSeeder::FROM, 'to' => WeighingDateFilterSeeder::TO]))
                ->assertInertia(fn (Assert $page) => $page->component('Weighings/Index')
                    ->where('filters.from', '2026-10-10')->where('filters.to', '2026-10-12')
                    ->where('weighings.total', 3)->has('weighings.data', 3)
                    ->where('weighings.data.0.business_date', '2026-10-12')
                    ->where('weighings.data.1.business_date', '2026-10-11')
                    ->where('weighings.data.2.business_date', '2026-10-10')
                    ->where('summary.ticket_count', 3)->where('summary.weight_grams', 330000));
        }

        $this->get(route('weighings.index', ['from' => '2026-10-10']))->assertInertia(fn (Assert $page) => $page->where('weighings.total', 4));
        $this->get(route('weighings.index', ['to' => '2026-10-12']))->assertInertia(fn (Assert $page) => $page->where('weighings.total', 4));
        $this->get(route('weighings.index', ['from' => '2026-10-14', 'to' => '2026-10-15']))
            ->assertInertia(fn (Assert $page) => $page->where('weighings.total', 0)->where('summary.ticket_count', 0));
        $this->getJson(route('weighings.index', ['from' => '2026-10-12', 'to' => '2026-10-10']))
            ->assertUnprocessable()->assertJsonValidationErrors('to');
    }

    public function test_rerunning_fixture_preserves_records_and_restores_the_clock(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-16T03:00:00Z'));
        $this->seed();
        $tables = ['users', 'vehicles', 'customers', 'weighings', 'weighing_items', 'weighing_loaders', 'price_schedules', 'audit_logs'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        $this->seed();
        foreach ($tables as $table) {
            $this->assertSame($before[$table], DB::table($table)->orderBy('id')->get()->toJson(), $table);
        }
        $this->assertSame('2026-10-16T03:00:00+00:00', app(BusinessClock::class)->now()->toIso8601String());
        $this->assertDatabaseHas('price_schedules', ['business_date' => '2026-10-16']);
        $this->assertForeignKeysAreValid();
    }

    public function test_fixture_refuses_production_without_writing_test_data(): void
    {
        $environment = app()->environment();
        app()->instance('env', 'production');
        try {
            app(WeighingDateFilterSeeder::class)->run();
            $this->fail('Production must not receive test transactions.');
        } catch (RuntimeException) {
            $this->assertDatabaseCount('users', 0);
            $this->assertDatabaseCount('vehicles', 0);
            $this->assertDatabaseCount('weighings', 0);
        } finally {
            app()->instance('env', $environment);
        }
    }

    public function test_fixture_reports_inactive_references_without_reactivating_or_replacing_tickets(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-16T03:00:00Z'));
        $this->seed();
        $customer = Customer::where('code', WeighingDateFilterSeeder::CUSTOMER_CODE)->sole();
        $customer->update(['is_active' => false]);
        $before = Weighing::orderBy('id')->get()->toJson();
        try {
            app(WeighingDateFilterSeeder::class)->run();
            $this->fail('Inactive test references must be reported.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('dinonaktifkan', $error->getMessage());
            $this->assertFalse($customer->fresh()->is_active);
            $this->assertSame($before, Weighing::orderBy('id')->get()->toJson());
            $this->assertSame('2026-10-16T03:00:00+00:00', app(BusinessClock::class)->now()->toIso8601String());
        }
    }

    public function test_fixture_does_not_overwrite_existing_driver_assignment_and_rolls_back(): void
    {
        $this->seed(RoleUserSeeder::class);
        $vehicle = Vehicle::create(['code' => 'EXISTING', 'plate_number' => 'BK EXISTING', 'is_active' => true]);
        $driver = User::where('email', 'supir@clbk.test')->sole();
        $driver->update(['assigned_vehicle_id' => $vehicle->id]);
        try {
            app(WeighingDateFilterSeeder::class)->run();
            $this->fail('Existing assignment must not be replaced.');
        } catch (RuntimeException) {
            $this->assertSame($vehicle->id, $driver->fresh()->assigned_vehicle_id);
            $this->assertDatabaseCount('vehicles', 1);
            $this->assertDatabaseCount('weighings', 0);
            $this->assertFalse(Customer::where('code', WeighingDateFilterSeeder::CUSTOMER_CODE)->exists());
        }
    }
}

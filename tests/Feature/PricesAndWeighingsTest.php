<?php

namespace Tests\Feature;

use App\Actions\Weighings\CreateDraftWeighing;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\LoadingWorker;
use App\Models\PriceSchedule;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Weighing;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\DatabaseMigrations;
use Tests\Concerns\LegacyWorkflowRoutes;
use Tests\Concerns\PricesAndWeighingsTest\LegacyWeighingLifecycleCases;
use Tests\Concerns\PricesAndWeighingsTest\PricePublicationCases;
use Tests\Concerns\PricesAndWeighingsTest\WeighingAccessCases;
use Tests\Concerns\PricesAndWeighingsTest\WeighingIntegrityCases;
use Tests\Concerns\PricesAndWeighingsTest\WeighingPostingCases;
use Tests\TestCase;

class PricesAndWeighingsTest extends TestCase
{
    use DatabaseMigrations;
    use LegacyWeighingLifecycleCases;
    use LegacyWorkflowRoutes;
    use PricePublicationCases;
    use WeighingAccessCases;
    use WeighingIntegrityCases;
    use WeighingPostingCases;

    private User $manager;

    private User $driver;

    private User $cashier;

    private Customer $customer;

    private array $workers;

    private Trip $trip;

    private PriceSchedule $price;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registerLegacyWorkflowTestRoutes();
        $this->travelTo(Carbon::parse('2026-10-09 03:00:00', 'UTC'));
        $this->manager = User::factory()->manager()->create();
        $this->driver = User::factory()->create();
        $this->cashier = User::factory()->cashier()->create();
        $this->customer = Customer::create(['code' => 'K-1', 'name' => 'Konsumen Satu', 'is_active' => true]);
        $this->workers = [];
        foreach (range(1, 3) as $number) {
            $this->workers[] = LoadingWorker::create(['code' => 'A-'.$number, 'name' => 'Anggota '.$number, 'is_active' => true])->id;
        }
        $vehicle = Vehicle::create(['code' => 'M-1', 'plate_number' => 'BK 1001', 'default_driver_id' => $this->driver->id, 'is_active' => true]);
        $this->actingAs($this->manager)->post(route('trips.store'), ['vehicle_id' => $vehicle->id, 'driver_id' => $this->driver->id])->assertSessionHasNoErrors();
        $this->trip = Trip::firstOrFail();
        $this->price = PriceSchedule::create(['business_date' => '2026-10-09', 'effective_from' => '2026-10-08 17:00:00',
            'tbs_buy_price_idr' => 3000, 'sort_buy_price_idr' => 2000, 'loading_rate_idr' => 100, 'published_by' => $this->manager->id]);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace(['customer_id' => $this->customer->id, 'trip_id' => $this->trip->id, 'tbs_kg' => '1000', 'sort_kg' => '100',
            'worker_ids' => $this->workers, 'weighed_at' => '2026-10-09T10:00:00'], $overrides);
    }

    private function draft(array $overrides = [], ?User $actor = null): Weighing
    {
        $payload = $this->payload($overrides);
        if ($actor?->role === UserRole::Driver) {
            unset($payload['weighed_at']);
        }
        $this->actingAs($actor ?? $this->manager);

        // Historical draft workflows remain covered without creating new legacy documents through the public form.
        return app(CreateDraftWeighing::class)->handle($actor ?? $this->manager, $payload)->fresh();
    }

    private function posting(Weighing $record, array $overrides = []): array
    {
        return array_replace(['version' => $record->version, 'price_schedule_id' => $this->price->id, 'idempotency_key' => (string) Str::uuid()], $overrides);
    }

    private function postTicket(Weighing $record, array $overrides = [], ?User $actor = null): Weighing
    {
        $this->actingAs($actor ?? $this->manager)->post(route('weighings.post', $record), $this->posting($record, $overrides))->assertSessionHasNoErrors();

        return $record->fresh(['items', 'loaders']);
    }

    private function assertLegacyDraftErrors(array $payload, string $field): void
    {
        try {
            app(CreateDraftWeighing::class)->handle($this->manager, $payload);
            $this->fail('Expected legacy validation error');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey($field, $error->errors());
        }
    }
}

<?php

namespace App\Support\Testing;

use App\Actions\Weighings\SaveDirectWeighing;
use App\Domain\Pricing\PriceSnapshotResolver;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\LoadingWorker;
use App\Models\PriceSchedule;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Weighing;
use App\Support\BusinessClock;
use App\Support\Database\WriteTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use RuntimeException;

final class WeighingDateFilterData
{
    public const FROM = '2026-10-10';

    public const TO = '2026-10-12';

    public const CUSTOMER_CODE = 'UAT-DATE-C01';

    public function seed(User $manager, User $driver): void
    {
        [$manager, $driver, $vehicle, $customer, $worker] = app(WriteTransaction::class)->run(function () use ($manager, $driver) {
            $manager = $manager->fresh();
            $driver = $driver->fresh();
            if (! $manager || ! $driver || ! $manager->is_active || $manager->role !== UserRole::Manager
                || ! $driver->is_active || $driver->role !== UserRole::Driver) {
                throw new RuntimeException('Akun fixture telah diubah. Gunakan database pengujian terpisah; akun yang ada tidak ditimpa.');
            }
            $vehicle = Vehicle::firstOrCreate(['code' => 'UAT-DATE-M01'], ['plate_number' => 'BK 2026 UAT', 'is_active' => true]);
            if ($driver->assigned_vehicle_id !== null && $driver->assigned_vehicle_id !== $vehicle->id) {
                throw new RuntimeException('Supir fixture sudah ditugaskan ke mobil lain; penugasan yang ada tidak ditimpa.');
            }
            if ($driver->assigned_vehicle_id === null) {
                $driver->update(['assigned_vehicle_id' => $vehicle->id]);
            }
            $driver->refresh();
            $customer = Customer::firstOrCreate(['code' => self::CUSTOMER_CODE], ['name' => 'Konsumen Uji Filter Tanggal', 'is_active' => true]);
            $worker = LoadingWorker::firstOrCreate(['code' => 'UAT-DATE-W01'], ['name' => 'Anggota Uji Filter Tanggal', 'is_active' => true]);
            if (! $vehicle->is_active || ! $customer->is_active || ! $worker->is_active) {
                throw new RuntimeException('Referensi fixture telah dinonaktifkan; status yang ada tidak ditimpa.');
            }

            return [$manager, $driver, $vehicle, $customer, $worker];
        });
        $previousNow = CarbonImmutable::getTestNow();
        try {
            foreach (['2026-10-09T23:59:59', '2026-10-10T00:00:00', '2026-10-11T12:00:00', '2026-10-12T23:59:59', '2026-10-13T00:00:00'] as $localTime) {
                $time = app(BusinessClock::class)->parse($localTime);
                CarbonImmutable::setTestNow($time);
                $this->price($manager, $time);
                $existing = Weighing::where('customer_id', $customer->id)->where('weighed_at', $time)->first();
                if ($existing) {
                    if ((int) $existing->created_by !== $driver->id || $existing->vehicle_id !== $vehicle->id || $existing->status !== 'posted') {
                        throw new RuntimeException('Tiket fixture telah diubah atau dibuat oleh supir lain; gunakan fixture terpisah tanpa menimpa histori.');
                    }

                    continue;
                }
                $price = app(PriceSnapshotResolver::class)->resolve($time);
                app(SaveDirectWeighing::class)->handle($driver, [
                    'customer_id' => $customer->id, 'vehicle_id' => $vehicle->id,
                    'vehicle_assignment_version' => $driver->vehicle_assignment_version,
                    'tbs_kg' => '100', 'sort_kg' => '10', 'worker_ids' => [$worker->id],
                    'price_schedule_id' => $price->id, 'idempotency_key' => (string) Str::uuid(),
                ]);
            }
        } finally {
            CarbonImmutable::setTestNow($previousNow);
        }
        // The create form can also preview and save a new weighing on the day of testing.
        $this->price($manager, app(BusinessClock::class)->now());
    }

    private function price(User $manager, CarbonImmutable $time): void
    {
        $clock = app(BusinessClock::class);
        app(WriteTransaction::class)->run(fn () => PriceSchedule::firstOrCreate([
            'business_date' => $clock->date($time),
            'effective_from' => $time->setTimezone($clock->timezone())->startOfDay()->utc(),
        ], [
            'tbs_buy_price_idr' => 3000, 'sort_buy_price_idr' => 2000,
            'loading_rate_idr' => 100, 'published_by' => $manager->id,
            'notes' => 'Fixture pengujian filter tanggal; bukan data operasional.',
        ]));
    }
}

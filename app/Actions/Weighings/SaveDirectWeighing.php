<?php

namespace App\Actions\Weighings;

use App\Domain\Pricing\PriceSnapshotResolver;
use App\Domain\Weighings\DirectWeighingInput;
use App\Domain\Weighings\WeighingCalculator;
use App\Enums\TransactionWorkflow;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\Weighing;
use App\Services\AuditService;
use App\Services\DriverVehicleAssignment;
use App\Services\IdempotencyService;
use App\Services\WhatsApp\NotificationOutbox;
use App\Support\BusinessClock;
use App\Support\Database\WriteTransaction;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveDirectWeighing
{
    public function handle(User $actor, array $data): Weighing
    {
        return app(WriteTransaction::class)->run(function () use ($actor, $data) {
            $actor = $actor->fresh();
            Gate::forUser($actor)->authorize('create', Weighing::class);
            $input = app(DirectWeighingInput::class);
            $weights = $input->weights($data);
            $workers = $input->workers($data);
            $payload = ['customer_id' => (int) $data['customer_id'], 'vehicle_id' => (int) $data['vehicle_id'],
                'weights' => $weights, 'worker_ids' => $workers, 'price_schedule_id' => (int) $data['price_schedule_id'],
                'replaces_weighing_id' => empty($data['replaces_weighing_id']) ? null : (int) $data['replaces_weighing_id']];
            if ($actor->role === UserRole::Driver) {
                $payload['vehicle_assignment_version'] = isset($data['vehicle_assignment_version']) ? (int) $data['vehicle_assignment_version'] : null;
            }
            $id = app(IdempotencyService::class)->run($actor, 'save-direct-weighing', $data['idempotency_key'], $payload,
                function () use ($actor, $input, $payload, $weights, $workers) {
                    if (! $weights) {
                        throw ValidationException::withMessages(['tbs_kg' => 'Isi setidaknya satu berat buah yang lebih dari nol.']);
                    }
                    $vehicleId = app(DriverVehicleAssignment::class)->forWrite($actor, $payload['vehicle_id'], $payload['vehicle_assignment_version'] ?? null);
                    $input->checkReferences($payload['customer_id'], $vehicleId, $workers);
                    if ($payload['replaces_weighing_id']) {
                        Gate::forUser($actor)->authorize('manage-settings');
                        $original = Weighing::findOrFail($payload['replaces_weighing_id']);
                        if ($original->status !== 'voided' || $original->customer_id !== $payload['customer_id']) {
                            throw ValidationException::withMessages(['replaces_weighing_id' => 'Pengganti harus merujuk timbangan batal milik konsumen yang sama.']);
                        }
                        if (Weighing::where('replaces_weighing_id', $original->id)->where('status', '!=', 'voided')->exists()) {
                            throw ValidationException::withMessages(['replaces_weighing_id' => 'Timbangan pengganti aktif sudah ada.']);
                        }
                    }
                    $clock = app(BusinessClock::class);
                    $time = $clock->now();
                    try {
                        $price = app(PriceSnapshotResolver::class)->resolve($time);
                    } catch (ValidationException) {
                        throw ValidationException::withMessages(['price_schedule_id' => 'Harga hari ini belum tersedia. Terbitkan harga terlebih dahulu, lalu perbarui ringkasan.']);
                    }
                    if ($price->id !== $payload['price_schedule_id']) {
                        throw ValidationException::withMessages(['price_schedule_id' => 'Harga berubah. Periksa ringkasan terbaru lalu klik Simpan Timbangan kembali.']);
                    }
                    $result = app(WeighingCalculator::class)->calculate($weights, $workers, $price);
                    // The internal draft and its posting commit together; users save only once.
                    $record = Weighing::create(['number' => app(DocumentNumber::class)->next('TBG', $clock->date($time)),
                        'customer_id' => $payload['customer_id'], 'vehicle_id' => $vehicleId,
                        'workflow' => TransactionWorkflow::Direct, 'trip_id' => null, 'weighed_at' => $time,
                        'business_date' => $clock->date($time), 'status' => 'draft', 'created_by' => $actor->id,
                        'replaces_weighing_id' => $payload['replaces_weighing_id']]);
                    $record->items()->createMany($result['items']);
                    $record->loaders()->createMany($result['loaders']);
                    $record->update(array_intersect_key($result, array_flip(['price_schedule_id', 'loading_rate_snapshot_idr',
                        'loading_basis_grams', 'total_purchase_idr', 'total_loading_wage_idr']))
                        + ['status' => 'posted', 'posted_at' => $time, 'posted_by' => $actor->id, 'version' => 2]);
                    $record->load('items', 'loaders');
                    $audit = app(AuditService::class);
                    $audit->record($actor, 'save-direct', $record, null, $audit->snapshot($record)
                        + ['items' => $record->items->toArray(), 'loaders' => $record->loaders->toArray()]);

                    app(NotificationOutbox::class)->weighing($record);

                    return $record->id;
                });
            $record = Weighing::findOrFail($id);
            Gate::forUser($actor)->authorize('view', $record);

            return $record;
        });
    }
}

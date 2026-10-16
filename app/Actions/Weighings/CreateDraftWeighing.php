<?php

namespace App\Actions\Weighings;

use App\Domain\Shared\Weight;
use App\Enums\TransactionWorkflow;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Factory;
use App\Models\LoadingWorker;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Weighing;
use App\Services\AuditService;
use App\Support\BusinessClock;
use App\Support\Database\WriteTransaction;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateDraftWeighing
{
    public function handle(User $actor, array $data, ?Weighing $record = null): Weighing
    {
        return app(WriteTransaction::class)->run(function () use ($actor, $data, $record) {
            $actor = $actor->fresh();
            $record = $record?->fresh();
            Gate::forUser($actor)->authorize($record ? 'update' : 'create', $record ?? Weighing::class);
            if ($record?->workflow === TransactionWorkflow::Direct) {
                throw ValidationException::withMessages(['operation' => 'Timbangan baru dikoreksi melalui pembatalan dan timbangan pengganti.']);
            }
            if ($record && ($record->status !== 'draft' || $record->version !== (int) $data['version'])) {
                throw ValidationException::withMessages(['operation' => 'Tiket sudah berubah. Muat ulang sebelum mengedit.']);
            }
            $trip = Trip::findOrFail($data['trip_id']);
            Gate::forUser($actor)->authorize('view', $trip);
            self::checkReferences($actor, $trip, (int) $data['customer_id'], array_map('intval', $data['worker_ids'] ?? []));
            if ($record && $record->trip->status !== 'loading') {
                throw ValidationException::withMessages(['operation' => 'Perjalanan asal sudah tidak menerima perubahan tiket.']);
            }
            $weights = ['TBS' => Weight::grams((string) $data['tbs_kg'], 'tbs_kg'), 'SORT' => Weight::grams((string) $data['sort_kg'], 'sort_kg')];
            $weights = array_filter($weights, fn ($weight) => $weight > 0);
            if (! $weights) {
                throw ValidationException::withMessages(['tbs_kg' => 'Isi setidaknya satu berat buah yang lebih dari nol.']);
            }
            $clock = app(BusinessClock::class);
            $time = $actor->role === UserRole::Driver ? $clock->now() : $clock->parse($data['weighed_at'], 'weighed_at');
            if ($time->greaterThan($clock->now())) {
                throw ValidationException::withMessages(['weighed_at' => 'Waktu timbang tidak boleh di masa depan.']);
            }
            $reason = trim($data['backdate_reason'] ?? '');
            if ($clock->date($time) < $clock->date($clock->now()) && $reason === '') {
                throw ValidationException::withMessages(['backdate_reason' => 'Alasan wajib untuk tanggal timbang mundur.']);
            }
            $replacement = null;
            if (! empty($data['replaces_weighing_id'])) {
                Gate::forUser($actor)->authorize('manage-settings');
                $replacement = Weighing::findOrFail($data['replaces_weighing_id']);
                if ($replacement->status !== 'voided' || $replacement->customer_id !== (int) $data['customer_id']) {
                    throw ValidationException::withMessages(['replaces_weighing_id' => 'Pengganti harus merujuk tiket batal milik konsumen yang sama.']);
                }
                if (Weighing::query()->where('replaces_weighing_id', $replacement->id)->where('status', '!=', 'voided')->when($record, fn ($q) => $q->whereKeyNot($record->id))->exists()) {
                    throw ValidationException::withMessages(['replaces_weighing_id' => 'Tiket pengganti aktif sudah ada.']);
                }
            }
            $audit = app(AuditService::class);
            $before = $record ? $audit->snapshot($record->load('items', 'loaders')) + ['items' => $record->items->toArray(), 'loaders' => $record->loaders->toArray()] : null;
            $attributes = ['customer_id' => (int) $data['customer_id'], 'vehicle_id' => $trip->vehicle_id, 'workflow' => TransactionWorkflow::Legacy, 'trip_id' => $trip->id, 'weighed_at' => $time,
                'business_date' => $clock->date($time), 'backdate_reason' => $actor->role === UserRole::Manager ? ($reason ?: null) : null,
                'replaces_weighing_id' => $replacement?->id];
            if ($record) {
                $record->update($attributes + ['version' => $record->version + 1]);
                $record->items()->delete();
                $record->loaders()->delete();
            } else {
                $record = Weighing::create($attributes + ['number' => app(DocumentNumber::class)->next('TBG', $clock->date($time)),
                    'status' => 'draft', 'created_by' => $actor->id]);
            }
            foreach ($weights as $type => $grams) {
                $record->items()->create(['fruit_type' => $type, 'weight_grams' => $grams]);
            }
            foreach (array_unique(array_map('intval', $data['worker_ids'] ?? [])) as $worker) {
                $record->loaders()->create(['worker_id' => $worker]);
            }
            $record->load('items', 'loaders');
            $audit->record($actor, $before ? 'update-draft' : 'create-draft', $record, $before,
                $audit->snapshot($record) + ['items' => $record->items->toArray(), 'loaders' => $record->loaders->toArray()]);

            return $record;
        });
    }

    public static function checkReferences(User $actor, Trip $trip, int $customer, array $workers): void
    {
        if ($trip->status !== 'loading') {
            throw ValidationException::withMessages(['trip_id' => 'Perjalanan sudah tidak menerima tiket.']);
        }
        if (! Vehicle::query()->whereKey($trip->vehicle_id)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['trip_id' => 'Mobil perjalanan sudah nonaktif.']);
        }
        if (! User::query()->whereKey($trip->driver_id)->where('is_active', true)->where('role', UserRole::Driver)->exists()) {
            throw ValidationException::withMessages(['trip_id' => 'Supir perjalanan sudah nonaktif atau bukan supir.']);
        }
        if ($trip->planned_factory_id && ! Factory::query()->whereKey($trip->planned_factory_id)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['trip_id' => 'Pabrik tujuan sudah nonaktif.']);
        }
        if (! Customer::query()->whereKey($customer)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['customer_id' => 'Pilih konsumen aktif.']);
        }
        if (LoadingWorker::query()->whereKey(array_unique($workers))->where('is_active', true)->count() !== count(array_unique($workers))) {
            throw ValidationException::withMessages(['worker_ids' => 'Anggota muat harus aktif.']);
        }
    }
}

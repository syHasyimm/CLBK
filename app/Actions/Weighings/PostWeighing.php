<?php

namespace App\Actions\Weighings;

use App\Domain\Weighings\WeighingPreview;
use App\Enums\TransactionWorkflow;
use App\Models\SortLot;
use App\Models\User;
use App\Models\Weighing;
use App\Services\AuditService;
use App\Services\IdempotencyService;
use App\Services\WhatsApp\NotificationOutbox;
use App\Support\BusinessClock;
use App\Support\Database\WriteTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PostWeighing
{
    public function handle(User $actor, Weighing $record, array $data): Weighing
    {
        return app(WriteTransaction::class)->run(function () use ($actor, $record, $data) {
            $actor = $actor->fresh();
            $record = $record->fresh(['trip', 'items', 'loaders']);
            Gate::forUser($actor)->authorize('post', $record);
            if ($record->workflow === TransactionWorkflow::Direct) {
                throw ValidationException::withMessages(['operation' => 'Timbangan baru sudah disimpan langsung. Tidak memerlukan pengesahan terpisah.']);
            }
            $id = app(IdempotencyService::class)->run($actor, 'post-weighing', $data['idempotency_key'],
                ['weighing_id' => $record->id, 'version' => (int) $data['version'], 'price_schedule_id' => (int) $data['price_schedule_id']],
                function () use ($actor, $record, $data) {
                    if ($record->status !== 'draft' || $record->version !== (int) $data['version']) {
                        throw ValidationException::withMessages(['operation' => 'Tiket sudah berubah atau disahkan. Muat ulang untuk melihat hasil terakhir.']);
                    }
                    CreateDraftWeighing::checkReferences($actor, $record->trip, $record->customer_id, $record->loaders->pluck('worker_id')->all());
                    if ($record->loaders->isEmpty()) {
                        throw ValidationException::withMessages(['operation' => 'Pilih minimal satu anggota muat sebelum pengesahan.']);
                    }
                    $preview = app(WeighingPreview::class);
                    $result = $preview->calculate($actor, $record);
                    if ($result['price_schedule_id'] !== (int) $data['price_schedule_id']) {
                        throw ValidationException::withMessages(['operation' => 'Harga berubah sejak pratinjau. Periksa ringkasan terbaru, lalu sahkan kembali.']);
                    }
                    $clock = app(BusinessClock::class);
                    $time = CarbonImmutable::parse($result['weighed_at'])->utc();
                    $audit = app(AuditService::class);
                    $before = $audit->snapshot($record);
                    foreach ($result['items'] as $item) {
                        $record->items()->where('fruit_type', $item['fruit_type'])->firstOrFail()->update($item);
                    }
                    foreach ($result['loaders'] as $loader) {
                        $record->loaders()->where('worker_id', $loader['worker_id'])->firstOrFail()->update($loader);
                    }
                    $record->update(array_intersect_key($result, array_flip(['price_schedule_id', 'loading_rate_snapshot_idr', 'loading_basis_grams', 'total_purchase_idr', 'total_loading_wage_idr']))
                        + ['weighed_at' => $time, 'business_date' => $clock->date($time), 'status' => 'posted',
                            'posted_at' => $clock->now(), 'posted_by' => $actor->id, 'version' => $record->version + 1]);
                    if ($sort = $record->items()->where('fruit_type', 'SORT')->first()) {
                        $lot = SortLot::create(['source_type' => 'INITIAL_SORT', 'source_weighing_item_id' => $sort->id,
                            'trip_id' => $record->trip_id, 'received_at' => $time, 'initial_grams' => $sort->weight_grams,
                            'initial_purchase_cost_idr' => $sort->purchase_amount_idr, 'initial_loading_cost_idr' => $sort->loading_cost_allocated_idr,
                            'cost_status' => 'available', 'status' => 'active']);
                        $lot->movements()->create(['kind' => 'receipt', 'quantity_delta_grams' => $sort->weight_grams,
                            'purchase_cost_delta_idr' => $sort->purchase_amount_idr, 'loading_cost_delta_idr' => $sort->loading_cost_allocated_idr,
                            'occurred_at' => $time, 'created_by' => $actor->id]);
                        $audit->record($actor, 'receive-initial-sort', $lot, null, $audit->snapshot($lot));
                    }
                    $record->load('items', 'loaders');
                    $audit->record($actor, 'post', $record, $before, $audit->snapshot($record)
                        + ['items' => $record->items->toArray(), 'loaders' => $record->loaders->toArray()]);

                    app(NotificationOutbox::class)->weighing($record);

                    return $record->id;
                });

            return Weighing::findOrFail($id);
        });
    }
}

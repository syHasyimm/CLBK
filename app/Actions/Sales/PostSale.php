<?php

namespace App\Actions\Sales;

use App\Domain\Inventory\Stock;
use App\Domain\Sales\FactoryCalculation;
use App\Enums\TransactionWorkflow;
use App\Enums\UserRole;
use App\Models\FactoryReturn;
use App\Models\FactorySale;
use App\Models\Sale;
use App\Models\SortLot;
use App\Models\User;
use App\Services\AuditService;
use App\Services\IdempotencyService;
use App\Services\ProfitLedger;
use App\Support\BusinessClock;
use App\Support\Database\WriteTransaction;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PostSale
{
    public function handle(User $actor, Sale $sale, array $data): Sale
    {
        return app(WriteTransaction::class)->run(function () use ($actor, $sale, $data) {
            $actor = $actor->fresh();
            $sale = $sale->fresh(['trip.vehicle', 'trip.driver']);
            Gate::forUser($actor)->authorize('update', $sale);
            if ($sale->workflow === TransactionWorkflow::Direct) {
                throw ValidationException::withMessages(['operation' => 'Penjualan langsung sudah sah saat disimpan.']);
            }
            $model = get_class($sale);
            $reference = trim($data['payment_reference'] ?? '');
            $id = app(IdempotencyService::class)->run($actor, 'post-'.$sale->kind().'-sale', $data['idempotency_key'], ['sale_id' => $sale->id, 'version' => (int) $data['version'], 'preview_hash' => $data['preview_hash'] ?? null,
                'cash_account_id' => empty($data['cash_account_id']) ? null : (int) $data['cash_account_id'], 'payment_reference' => $reference === '' ? null : $reference], function () use ($actor, $sale, $data) {
                    $trip = $sale->trip;
                    if ($sale->status !== 'draft' || $sale->version !== (int) $data['version'] || ! SaveSale::eligible($trip, $sale->kind()) || ! $trip->vehicle->is_active || ! $trip->driver->is_active || $trip->driver->role !== UserRole::Driver) {
                        throw ValidationException::withMessages(['operation' => 'Draft atau perjalanan berubah. Muat ulang ringkasan.']);
                    }
                    $clock = app(BusinessClock::class);
                    if ($actor->role === UserRole::Driver) {
                        $sale->sold_at = $clock->now();
                        $sale->business_date = $clock->date($sale->sold_at);
                    }
                    $audit = app(AuditService::class);
                    $before = $audit->snapshot($sale);
                    if ($sale instanceof FactorySale) {
                        if (! $sale->factory->is_active) {
                            throw ValidationException::withMessages(['operation' => 'Pabrik sudah tidak aktif.']);
                        }
                        $calc = app(FactoryCalculation::class)->calculate($sale, $trip);
                        $approver = $sale->variance_approved_by ? User::find($sale->variance_approved_by) : null;
                        if ($calc['requires_approval'] && ($sale->variance_approved_version !== $sale->version || ! $approver?->is_active || $approver->role !== UserRole::Manager || ! $sale->variance_reason)) {
                            throw ValidationException::withMessages(['operation' => 'Selisih berat membutuhkan persetujuan manajer atas draft terbaru.']);
                        }
                        $sale->fill(array_intersect_key($calc, array_flip(['weight_difference_grams', 'revenue_idr', 'cost_status', 'purchase_cost_released_idr', 'loading_cost_released_idr'])));
                        if ($sale->sorted_out_grams > 0) {
                            $return = FactoryReturn::create(['factory_sale_id' => $sale->id, 'weight_grams' => $sale->sorted_out_grams,
                                'purchase_cost_carried_idr' => $calc['purchase_cost_carried_idr'], 'loading_cost_carried_idr' => $calc['loading_cost_carried_idr'], 'cost_status' => $calc['cost_status'], 'received_at' => $sale->sold_at]);
                            $lot = SortLot::create(['source_type' => 'FACTORY_RETURN', 'source_factory_return_id' => $return->id, 'trip_id' => $trip->id,
                                'received_at' => $sale->sold_at, 'initial_grams' => $return->weight_grams, 'initial_purchase_cost_idr' => $return->purchase_cost_carried_idr,
                                'initial_loading_cost_idr' => $return->loading_cost_carried_idr, 'cost_status' => $return->cost_status, 'status' => 'active']);
                            $lot->movements()->create(['kind' => 'receipt', 'quantity_delta_grams' => $lot->initial_grams, 'purchase_cost_delta_idr' => $lot->initial_purchase_cost_idr,
                                'loading_cost_delta_idr' => $lot->initial_loading_cost_idr, 'occurred_at' => $sale->sold_at, 'created_by' => $actor->id]);
                            $audit->record($actor, 'receive-factory-return', $lot, null, $audit->snapshot($lot));
                        }
                    } else {
                        $calc = app(Stock::class)->preview($sale);
                        if (! hash_equals($calc['preview_hash'], $data['preview_hash'] ?? '')) {
                            throw ValidationException::withMessages(['operation' => 'Stok atau pembagian FIFO berubah. Muat ulang dan periksa ringkasan terbaru.']);
                        }
                        foreach ($calc['items'] as $values) {
                            unset($values['revision']);
                            $item = $sale->items()->create($values);
                            SortLot::findOrFail($item->lot_id)->movements()->create(['kind' => 'sale', 'sort_sale_item_id' => $item->id, 'quantity_delta_grams' => -$item->weight_grams,
                                'purchase_cost_delta_idr' => $item->purchase_cost_released_idr === null ? null : -$item->purchase_cost_released_idr,
                                'loading_cost_delta_idr' => $item->loading_cost_released_idr === null ? null : -$item->loading_cost_released_idr, 'occurred_at' => $sale->sold_at, 'created_by' => $actor->id]);
                        }
                        $sale->fill(array_intersect_key($calc, array_flip(['revenue_idr', 'cost_status', 'purchase_cost_released_idr', 'loading_cost_released_idr'])));
                    }
                    $sale->fill(['status' => 'posted', 'posted_at' => $clock->now(), 'posted_by' => $actor->id, 'version' => $sale->version + 1])->save();
                    if ($sale instanceof FactorySale) {
                        $tripBefore = $audit->snapshot($trip);
                        $trip->update(['status' => 'factory_recorded', 'manual_load_grams' => $sale->initial_load_grams, 'closed_at' => null, 'version' => $trip->version + 1]);
                        $audit->record($actor, 'record-factory', $trip, $tripBefore, $audit->snapshot($trip));
                    }
                    app(ProfitLedger::class)->sale($actor, $sale);
                    $audit->record($actor, 'post', $sale, $before, $audit->snapshot($sale));

                    return $sale->id;
                });

            return $model::findOrFail($id);
        });
    }
}

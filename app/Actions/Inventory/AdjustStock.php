<?php

namespace App\Actions\Inventory;

use App\Actions\Sales\ReverseSale;
use App\Domain\Inventory\Stock;
use App\Domain\Shared\Weight;
use App\Models\SortLot;
use App\Models\SortStockMovement;
use App\Models\User;
use App\Services\AuditService;
use App\Services\IdempotencyService;
use App\Services\ProfitLedger;
use App\Support\BusinessClock;
use App\Support\Database\WriteTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AdjustStock
{
    public function handle(User $actor, SortLot $lot, array $data, ?SortStockMovement $reversal = null): void
    {
        app(WriteTransaction::class)->run(function () use ($actor, $lot, $data, $reversal) {
            $actor = $actor->fresh();
            Gate::forUser($actor)->authorize('manage-settings');
            $lot = $lot->fresh();
            $reason = trim($data['reason']);
            if ($reason === '') {
                throw ValidationException::withMessages(['reason' => 'Alasan wajib diisi.']);
            }
            $grams = $reversal ? null : Weight::grams($data['loss_kg'], 'loss_kg');
            app(IdempotencyService::class)->run($actor, $reversal ? 'reverse-stock-loss' : 'stock-loss', $data['idempotency_key'], ['lot_id' => $lot->id, 'movement_id' => $reversal?->id, 'grams' => $grams, 'reason' => $reason], function () use ($actor, $lot, $reversal, $grams, $reason) {
                $clock = app(BusinessClock::class);
                if ($reversal) {
                    $reversal = $reversal->fresh();
                    if ($reversal->lot_id !== $lot->id || $reversal->kind !== 'adjustment' || $lot->status !== 'active' || SortStockMovement::where('reversal_of_id', $reversal->id)->exists()) {
                        throw ValidationException::withMessages(['operation' => 'Kehilangan ini sudah dibalik atau lot tidak aktif.']);
                    }
                    $movement = app(ReverseSale::class)->reverseMovement($actor, $reversal, $reason);
                    app(ProfitLedger::class)->reverse($actor, 'sort_stock_movement_id', $reversal->id);
                } else {
                    $allocation = app(Stock::class)->allocate($lot, $grams, $clock->now());
                    $movement = $lot->movements()->create(['kind' => 'adjustment', 'quantity_delta_grams' => -$grams,
                        'purchase_cost_delta_idr' => $allocation['purchase_cost_released_idr'] === null ? null : -$allocation['purchase_cost_released_idr'],
                        'loading_cost_delta_idr' => $allocation['loading_cost_released_idr'] === null ? null : -$allocation['loading_cost_released_idr'],
                        'reason' => $reason, 'occurred_at' => $clock->now(), 'created_by' => $actor->id]);
                    if ($lot->cost_status === 'available') {
                        DB::table('profit_entries')->insert(['category' => 'stock_loss', 'amount_delta_idr' => $allocation['purchase_cost_released_idr'] + $allocation['loading_cost_released_idr'],
                            'occurred_at' => $clock->now(), 'business_date' => $clock->date($clock->now()), 'trip_id' => $lot->trip_id, 'sort_stock_movement_id' => $movement->id, 'created_by' => $actor->id]);
                    }
                }
                if ($reversal && $lot->trip->status === 'closed') {
                    $before = app(AuditService::class)->snapshot($lot->trip);
                    $lot->trip->update(['status' => 'correction_pending', 'closed_at' => null, 'version' => $lot->trip->version + 1]);
                    app(AuditService::class)->record($actor, 'await-correction', $lot->trip, $before, app(AuditService::class)->snapshot($lot->trip));
                }
                app(AuditService::class)->record($actor, $reversal ? 'reverse-loss' : 'record-loss', $movement, null, app(AuditService::class)->snapshot($movement));

                return $movement->id;
            });
        });
    }
}

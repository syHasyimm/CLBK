<?php

namespace App\Actions\Sales;

use App\Domain\Inventory\Stock;
use App\Enums\TransactionWorkflow;
use App\Models\FactorySale;
use App\Models\Sale;
use App\Models\SortStockMovement;
use App\Models\User;
use App\Services\AuditService;
use App\Services\IdempotencyService;
use App\Services\ProfitLedger;
use App\Services\SaleCash;
use App\Support\BusinessClock;
use App\Support\Database\WriteTransaction;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ReverseSale
{
    public function handle(User $actor, Sale $sale, array $data): void
    {
        app(WriteTransaction::class)->run(function () use ($actor, $sale, $data) {
            $actor = $actor->fresh();
            $sale = $sale->fresh(['trip']);
            Gate::forUser($actor)->authorize('reverse', $sale);
            app(IdempotencyService::class)->run($actor, 'reverse-'.$sale->kind().'-sale', $data['idempotency_key'], ['sale_id' => $sale->id, 'version' => (int) $data['version'], 'reason' => trim($data['reason'])], function () use ($actor, $sale, $data) {
                if ($sale->status !== 'posted' || $sale->version !== (int) $data['version'] || trim($data['reason']) === '') {
                    throw ValidationException::withMessages(['operation' => 'Nota berubah atau sudah dibalik. Isi alasan dan muat ulang halaman.']);
                }
                $audit = app(AuditService::class);
                $before = $audit->snapshot($sale);
                app(SaleCash::class)->reverse($actor, $sale, trim($data['reason']));
                if ($sale instanceof FactorySale) {
                    if ($lot = $sale->factoryReturn?->lot) {
                        if (! app(Stock::class)->untouched($lot)) {
                            throw ValidationException::withMessages(['operation' => 'Balikkan penjualan dan kehilangan retur pabrik terlebih dahulu hingga berat serta biaya kembali penuh.']);
                        }
                        $this->reverseMovement($actor, $lot->movements()->where('kind', 'receipt')->firstOrFail(), trim($data['reason']));
                        $lotBefore = $audit->snapshot($lot);
                        $lot->update(['status' => 'voided']);
                        $audit->record($actor, 'reverse-factory-return', $lot, $lotBefore, $audit->snapshot($lot));
                    }
                } else {
                    foreach (SortStockMovement::whereIn('sort_sale_item_id', $sale->items()->pluck('id'))->get() as $movement) {
                        $this->reverseMovement($actor, $movement, trim($data['reason']));
                    }
                }
                $clock = app(BusinessClock::class);
                app(ProfitLedger::class)->reverse($actor, $sale->kind().'_sale_id', $sale->id);
                $sale->update(['status' => 'reversed', 'reversed_at' => $clock->now(), 'reversal_business_date' => $clock->date($clock->now()),
                    'reversed_by' => $actor->id, 'reversal_reason' => trim($data['reason']), 'version' => $sale->version + 1]);
                $trip = $sale->trip;
                if ($sale->workflow === TransactionWorkflow::Legacy && ($sale instanceof FactorySale || $trip->status === 'closed')) {
                    $tripBefore = $audit->snapshot($trip);
                    $trip->update(['status' => 'correction_pending', 'closed_at' => null, 'manual_load_grams' => $sale instanceof FactorySale ? null : $trip->manual_load_grams, 'version' => $trip->version + 1]);
                    $audit->record($actor, 'await-correction', $trip, $tripBefore, $audit->snapshot($trip));
                }
                $audit->record($actor, 'reverse', $sale, $before, $audit->snapshot($sale));

                return $sale->id;
            });
        });
    }

    public function reverseMovement(User $actor, SortStockMovement $movement, string $reason): SortStockMovement
    {
        return SortStockMovement::create(['lot_id' => $movement->lot_id, 'kind' => 'reversal', 'quantity_delta_grams' => -$movement->quantity_delta_grams,
            'purchase_cost_delta_idr' => $movement->purchase_cost_delta_idr === null ? null : -$movement->purchase_cost_delta_idr,
            'loading_cost_delta_idr' => $movement->loading_cost_delta_idr === null ? null : -$movement->loading_cost_delta_idr,
            'reversal_of_id' => $movement->id, 'reason' => $reason, 'occurred_at' => app(BusinessClock::class)->now(), 'created_by' => $actor->id]);
    }
}

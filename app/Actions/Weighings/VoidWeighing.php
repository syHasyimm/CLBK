<?php

namespace App\Actions\Weighings;

use App\Domain\Inventory\Stock;
use App\Enums\TransactionWorkflow;
use App\Models\User;
use App\Models\Weighing;
use App\Services\AuditService;
use App\Services\IdempotencyService;
use App\Support\BusinessClock;
use App\Support\Database\WriteTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class VoidWeighing
{
    public function handle(User $actor, Weighing $record, array $data): Weighing
    {
        return app(WriteTransaction::class)->run(function () use ($actor, $record, $data) {
            $actor = $actor->fresh();
            $record = $record->fresh(['trip', 'items.lot.movements']);
            Gate::forUser($actor)->authorize('void', $record);
            $reason = trim($data['reason']);
            if ($reason === '') {
                throw ValidationException::withMessages(['reason' => 'Alasan pembatalan wajib diisi.']);
            }
            $id = app(IdempotencyService::class)->run($actor, 'void-weighing', $data['idempotency_key'],
                ['weighing_id' => $record->id, 'version' => (int) $data['version'], 'reason' => $reason], function () use ($actor, $record, $data, $reason) {
                    if ($record->status !== 'posted' || $record->version !== (int) $data['version']) {
                        throw ValidationException::withMessages(['operation' => 'Timbangan sudah berubah atau dibatalkan. Muat ulang untuk melihat status terakhir.']);
                    }
                    if ($record->workflow === TransactionWorkflow::Legacy && $record->trip?->status !== 'loading') {
                        throw ValidationException::withMessages(['operation' => 'Pembatalan hanya untuk tiket sah pada perjalanan yang masih menerima muatan.']);
                    }
                    $audit = app(AuditService::class);
                    $hasPayment = DB::table('customer_payment_allocations as a')->join('customer_payments as p', 'p.id', '=', 'a.customer_payment_id')->where('a.weighing_id', $record->id)->where('p.status', 'posted')->exists();
                    $hasPayroll = DB::table('payroll_payment_allocations as a')->join('payroll_payments as p', 'p.id', '=', 'a.payroll_payment_id')->join('weighing_loaders as l', 'l.id', '=', 'a.weighing_loader_id')->where('l.weighing_id', $record->id)->where('p.status', 'posted')->exists();
                    if ($hasPayment || $hasPayroll) {
                        throw ValidationException::withMessages(['operation' => 'Balikkan pembayaran konsumen dan gaji yang masih sah sebelum membatalkan tiket.']);
                    }
                    $before = $audit->snapshot($record);
                    foreach ($record->items as $item) {
                        if (! $lot = $item->lot) {
                            continue;
                        }
                        if (! app(Stock::class)->untouched($lot)) {
                            throw ValidationException::withMessages(['operation' => 'Stok sortir sudah bergerak. Balikkan transaksi turunannya dahulu.']);
                        }
                        $receipt = $lot->movements->firstWhere('kind', 'receipt');
                        $lot->movements()->create(['kind' => 'reversal', 'quantity_delta_grams' => -$receipt->quantity_delta_grams,
                            'purchase_cost_delta_idr' => -$receipt->purchase_cost_delta_idr, 'loading_cost_delta_idr' => -$receipt->loading_cost_delta_idr,
                            'reversal_of_id' => $receipt->id, 'reason' => $reason, 'occurred_at' => app(BusinessClock::class)->now(), 'created_by' => $actor->id]);
                        $lotBefore = $audit->snapshot($lot);
                        $lot->update(['status' => 'voided']);
                        $audit->record($actor, 'void-initial-sort', $lot, $lotBefore, $audit->snapshot($lot));
                    }
                    $record->update(['status' => 'voided', 'voided_at' => app(BusinessClock::class)->now(), 'voided_by' => $actor->id,
                        'void_reason' => $reason, 'version' => $record->version + 1]);
                    $audit->record($actor, 'void', $record, $before, $audit->snapshot($record));

                    return $record->id;
                });

            return Weighing::findOrFail($id);
        });
    }
}

<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SaleReceipt;
use App\Models\User;
use App\Support\BusinessClock;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleCash
{
    // Call inside the sale's write transaction so its receipts and cash reverse together.
    public function reverse(User $actor, Sale $sale, string $reason): void
    {
        $clock = app(BusinessClock::class);
        $audit = app(AuditService::class);
        foreach (SaleReceipt::where($sale->kind().'_sale_id', $sale->id)->where('status', 'posted')->orderBy('id')->get() as $receipt) {
            $before = $audit->snapshot($receipt);
            $reference = 'Pembalikan penjualan '.$sale->number;
            $entry = DB::table('cash_entries')->where('sale_receipt_id', $receipt->id)->sole();
            $this->cashOperation(fn () => app(CashLedger::class)->reverse($actor, $entry, $reason, $reference));
            $receipt->update(['status' => 'reversed', 'version' => $receipt->version + 1, 'reversed_at' => $clock->now(),
                'reversal_business_date' => $clock->date($clock->now()), 'reversed_by' => $actor->id,
                'reversal_reason' => $reason, 'return_reference' => $reference]);
            $audit->record($actor, 'reverse-sale-cash', $receipt, $before, $audit->snapshot($receipt));
        }
    }

    private function cashOperation(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['operation' => array_values($exception->errors())[0][0]]);
        }
    }
}

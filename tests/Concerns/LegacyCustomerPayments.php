<?php

namespace Tests\Concerns;

use App\Models\CashAccount;
use App\Models\CustomerPayment;
use App\Models\User;
use App\Models\Weighing;
use App\Services\AuditService;
use App\Services\CashLedger;
use App\Support\BusinessClock;
use App\Support\Database\WriteTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait LegacyCustomerPayments
{
    // Historical fixtures only: new HTTP/action payments must never create instalments.
    protected function legacyCustomerPayment(User $actor, CashAccount $cash, array $amounts, string $when = '2026-10-09T10:00:00'): CustomerPayment
    {
        return app(WriteTransaction::class)->run(function () use ($actor, $cash, $amounts, $when) {
            $clock = app(BusinessClock::class);
            $time = $clock->parse($when);
            $amount = array_sum($amounts);
            app(CashLedger::class)->check($cash->id, 'out', $amount, $time);
            $record = CustomerPayment::create(['number' => 'LEGACY-'.Str::uuid(), 'customer_id' => Weighing::findOrFail(array_key_first($amounts))->customer_id,
                'cash_account_id' => $cash->id, 'amount_idr' => $amount, 'method' => 'cash', 'status' => 'posted',
                'occurred_at' => $time, 'business_date' => $clock->date($time), 'posted_at' => $time, 'created_by' => $actor->id, 'version' => 1]);
            foreach ($amounts as $id => $value) {
                DB::table('customer_payment_allocations')->insert(['customer_payment_id' => $record->id, 'weighing_id' => $id, 'amount_idr' => $value]);
            }
            DB::table('cash_entries')->insert(['number' => 'LEGACY-CASH-'.Str::uuid(), 'cash_account_id' => $cash->id, 'kind' => 'customer',
                'direction' => 'out', 'amount_idr' => $amount, 'customer_payment_id' => $record->id, 'occurred_at' => $time,
                'business_date' => $clock->date($time), 'created_by' => $actor->id]);
            app(AuditService::class)->record($actor, 'legacy-fixture', $record, null, app(AuditService::class)->snapshot($record));

            return $record;
        });
    }
}

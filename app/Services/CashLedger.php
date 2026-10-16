<?php

namespace App\Services;

use App\Models\User;
use App\Support\BusinessClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashLedger
{
    public function balance(int $account): int
    {
        return (int) DB::table('cash_entries')->where('cash_account_id', $account)
            ->selectRaw("COALESCE(SUM(CASE direction WHEN 'in' THEN amount_idr ELSE -amount_idr END),0) AS balance")->value('balance');
    }

    public function check(int $account, string $direction, int $amount, CarbonImmutable $time): void
    {
        $latest = DB::table('cash_entries')->where('cash_account_id', $account)->max('occurred_at');
        if ($latest && $time->lt(CarbonImmutable::parse($latest, 'UTC'))) {
            throw ValidationException::withMessages(['occurred_at' => 'Waktu tidak boleh mendahului mutasi terakhir akun kas. Pilih waktu terbaru.']);
        }
        $balance = $this->balance($account) + ($direction === 'in' ? $amount : -$amount);
        if ($balance < 0 || $balance > 1000000000000000) {
            throw ValidationException::withMessages(['amount_idr' => 'Saldo akun tidak mencukupi atau melebihi batas. Periksa akun kas dan setoran modal.']);
        }
    }

    public function reverse(User $actor, object $entry, string $reason, string $reference): int
    {
        if ($entry->kind === 'reversal' || DB::table('cash_entries')->where('reversal_of_id', $entry->id)->exists()) {
            throw ValidationException::withMessages(['operation' => 'Mutasi ini sudah dibalik atau merupakan pembalikan.']);
        }
        $clock = app(BusinessClock::class);
        $time = $clock->now();
        $direction = $entry->direction === 'in' ? 'out' : 'in';
        $this->check($entry->cash_account_id, $direction, $entry->amount_idr, $time);

        return DB::table('cash_entries')->insertGetId(['cash_account_id' => $entry->cash_account_id, 'kind' => 'reversal',
            'direction' => $direction, 'amount_idr' => $entry->amount_idr, 'occurred_at' => $time, 'business_date' => $clock->date($time),
            'reversal_of_id' => $entry->id, 'reason' => $reason, 'reference' => $reference, 'created_by' => $actor->id]);
    }
}

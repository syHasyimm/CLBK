<?php

namespace App\Actions\Finance;

use App\Enums\FinanceKind;
use App\Models\FinanceDocument;
use App\Models\User;
use App\Services\AuditService;
use App\Services\CashLedger;
use App\Services\IdempotencyService;
use App\Services\ProfitLedger;
use App\Support\BusinessClock;
use App\Support\Database\WriteTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ReverseFinance
{
    public function handle(User $actor, FinanceKind $kind, FinanceDocument $record, array $data): void
    {
        app(WriteTransaction::class)->run(function () use ($actor, $kind, $record, $data) {
            $actor = $actor->fresh();
            $record = $kind->model()::findOrFail($record->id);
            Gate::forUser($actor)->authorize('reverse', $record);
            $reason = trim($data['reason']);
            $reference = trim($data['return_reference']);
            app(IdempotencyService::class)->run($actor, 'reverse-finance-'.$kind->value, $data['idempotency_key'],
                ['id' => $record->id, 'version' => (int) $data['version'], 'reason' => $reason, 'return_reference' => $reference], function () use ($actor, $kind, $record, $data, $reason, $reference) {
                    if ($record->status !== 'posted' || $record->version !== (int) $data['version'] || $reason === '' || $reference === '') {
                        throw ValidationException::withMessages(['operation' => 'Voucher berubah/sudah dibalik. Isi alasan dan kejadian pengembalian uang.']);
                    }
                    $audit = app(AuditService::class);
                    $before = $audit->snapshot($record);
                    $entry = DB::table('cash_entries')->where($kind->sourceColumn(), $record->id)->sole();
                    app(CashLedger::class)->reverse($actor, $entry, $reason, $reference);
                    if ($kind === FinanceKind::Expense) {
                        app(ProfitLedger::class)->reverse($actor, 'daily_expense_id', $record->id);
                    }
                    $clock = app(BusinessClock::class);
                    $record->update(['status' => 'reversed', 'version' => $record->version + 1, 'reversed_at' => $clock->now(),
                        'reversal_business_date' => $clock->date($clock->now()), 'reversed_by' => $actor->id,
                        'reversal_reason' => $reason, 'return_reference' => $reference]);
                    $audit->record($actor, 'reverse-finance', $record, $before, $audit->snapshot($record));

                    return $record->id;
                });
        });
    }
}

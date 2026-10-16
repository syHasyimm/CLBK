<?php

namespace App\Actions\Finance;

use App\Models\User;
use App\Services\BusinessCash;
use App\Services\CashLedger;
use App\Services\IdempotencyService;
use App\Support\BusinessClock;
use App\Support\Database\WriteTransaction;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ManualCash
{
    public function post(User $actor, array $data): int
    {
        return app(WriteTransaction::class)->run(function () use ($actor, $data) {
            $actor = $actor->fresh();
            Gate::forUser($actor)->authorize('manage-finance-capital');
            $data['cash_account_id'] ??= app(BusinessCash::class)->account()?->id;
            $payload = $data;
            unset($payload['idempotency_key']);

            return app(IdempotencyService::class)->run($actor, 'manual-cash', $data['idempotency_key'], $payload, function () use ($actor, $data) {
                $clock = app(BusinessClock::class);
                $time = $clock->parse($data['occurred_at'], 'occurred_at');
                if ($time->gt($clock->now()) || $clock->date($time) < $clock->date($clock->now()) && trim($data['backdate_reason'] ?? '') === '') {
                    throw ValidationException::withMessages(['occurred_at' => 'Tanggal tidak boleh di masa depan. Transaksi tanggal sebelumnya memerlukan alasan.']);
                }
                $account = app(BusinessCash::class)->forPosting(isset($data['cash_account_id']) ? (int) $data['cash_account_id'] : null);
                if ($data['kind'] === 'opening' && DB::table('cash_entries')->where('cash_account_id', $account->id)->exists()) {
                    throw ValidationException::withMessages(['kind' => 'Saldo awal hanya sebelum mutasi pertama akun. Gunakan setoran modal.']);
                }
                $direction = $data['kind'] === 'withdrawal' ? 'out' : 'in';
                app(CashLedger::class)->check($account->id, $direction, (int) $data['amount_idr'], $time);
                $entry = ['number' => app(DocumentNumber::class)->next('KAS', $clock->date($time)), 'cash_account_id' => $account->id,
                    'direction' => $direction, 'amount_idr' => (int) $data['amount_idr'], 'occurred_at' => $time, 'business_date' => $clock->date($time),
                    'kind' => $data['kind'], 'reason' => trim($data['reason']), 'reference' => $data['reference'] ?? null,
                    'backdate_reason' => $data['backdate_reason'] ?? null, 'created_by' => $actor->id];
                $id = DB::table('cash_entries')->insertGetId($entry);
                $this->audit($actor, $id, 'manual-cash', null, $entry);

                return $id;
            });
        });
    }

    public function reverse(User $actor, int $id, array $data): int
    {
        return app(WriteTransaction::class)->run(function () use ($actor, $id, $data) {
            $actor = $actor->fresh();
            Gate::forUser($actor)->authorize('manage-finance-capital');

            return app(IdempotencyService::class)->run($actor, 'reverse-manual-cash', $data['idempotency_key'], ['id' => $id, 'reason' => trim($data['reason']), 'return_reference' => trim($data['return_reference'])], function () use ($actor, $id, $data) {
                $entry = DB::table('cash_entries')->find($id);
                abort_unless($entry && in_array($entry->kind, ['opening', 'capital', 'withdrawal'], true), 404);
                $reversal = app(CashLedger::class)->reverse($actor, $entry, trim($data['reason']), trim($data['return_reference']));
                $this->audit($actor, $reversal, 'reverse-manual-cash', (array) $entry, (array) DB::table('cash_entries')->find($reversal));

                return $reversal;
            });
        });
    }

    private function audit(User $actor, int $id, string $action, ?array $before, array $after): void
    {
        DB::table('audit_logs')->insert(['actor_id' => $actor->id, 'action' => $action, 'entity_type' => 'cash_entries', 'entity_id' => (string) $id,
            'before_json' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR), 'after_json' => json_encode($after, JSON_THROW_ON_ERROR),
            'occurred_at' => app(BusinessClock::class)->now()]);
    }
}

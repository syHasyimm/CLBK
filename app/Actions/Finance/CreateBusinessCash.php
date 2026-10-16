<?php

namespace App\Actions\Finance;

use App\Models\CashAccount;
use App\Models\User;
use App\Services\AuditService;
use App\Support\Database\WriteTransaction;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateBusinessCash
{
    public function __construct(private WriteTransaction $transactions, private AuditService $audit) {}

    public function handle(User $actor): CashAccount
    {
        return $this->transactions->run(function () use ($actor) {
            $actor = $actor->fresh();
            Gate::forUser($actor)->authorize('manage-finance-capital');
            if (CashAccount::query()->exists()) {
                throw ValidationException::withMessages(['operation' => 'Akun kas sudah tersedia. Hanya satu Kas Usaha yang diperbolehkan.']);
            }

            $account = CashAccount::query()->create([
                'code' => 'KAS-UTAMA', 'name' => 'Kas Usaha', 'type' => 'cash', 'is_active' => true,
            ]);
            $this->audit->record($actor, 'created', $account, null, $this->audit->snapshot($account));

            return $account;
        });
    }
}

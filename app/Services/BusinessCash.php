<?php

namespace App\Services;

use App\Models\CashAccount;
use Illuminate\Validation\ValidationException;

class BusinessCash
{
    public function account(): ?CashAccount
    {
        return CashAccount::query()->where('code', 'KAS-UTAMA')->first()
            ?? CashAccount::query()->where('type', 'cash')->orderBy('id')->first();
    }

    public function forPosting(?int $id = null): CashAccount
    {
        $account = $this->account();
        if (! $account || ($id !== null && $id !== $account->id)) {
            throw ValidationException::withMessages(['cash_account_id' => 'Transaksi hanya dapat memakai kas usaha.']);
        }
        if (! $account->is_active) {
            throw ValidationException::withMessages(['cash_account_id' => 'Kas usaha harus aktif sebelum mencatat transaksi.']);
        }

        return $account;
    }
}

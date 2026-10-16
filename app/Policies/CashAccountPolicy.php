<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\CashAccount;
use App\Models\User;
use App\Services\BusinessCash;

class CashAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->role === UserRole::Manager;
    }

    public function view(User $user, CashAccount $account): bool
    {
        return $this->viewAny($user) && $account->id === app(BusinessCash::class)->account()?->id;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, CashAccount $account): bool
    {
        return false;
    }

    public function delete(User $user, CashAccount $account): bool
    {
        return false;
    }
}

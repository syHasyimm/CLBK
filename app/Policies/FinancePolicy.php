<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\FinanceDocument;
use App\Models\User;

class FinancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && in_array($user->role, [UserRole::Manager, UserRole::Cashier], true);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, FinanceDocument $record): bool
    {
        return $this->viewAny($user);
    }

    public function reverse(User $user, FinanceDocument $record): bool
    {
        return $user->is_active && $user->role === UserRole::Manager;
    }
}

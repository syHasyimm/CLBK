<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class CustomerPolicy extends ManagerPolicy
{
    public function create(User $user): bool
    {
        return $user->is_active && in_array($user->role, [UserRole::Manager, UserRole::Driver], true);
    }
}

<?php

namespace App\Policies;

use App\Enums\TransactionWorkflow;
use App\Enums\UserRole;
use App\Models\Sale;
use App\Models\User;

class SalePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function create(User $user): bool
    {
        return $user->is_active && in_array($user->role, [UserRole::Manager, UserRole::Driver], true);
    }

    public function view(User $user, Sale $sale): bool
    {
        $canView = $sale->workflow === TransactionWorkflow::Direct
            ? $user->is_active && ($user->role !== UserRole::Driver || $sale->created_by === $user->id)
            : $sale->trip !== null && (new TripPolicy)->view($user, $sale->trip);

        return $canView && ($user->role !== UserRole::Cashier || $sale->status !== 'draft');
    }

    public function update(User $user, Sale $sale): bool
    {
        return $this->create($user) && $this->view($user, $sale) && $sale->created_by === $user->id;
    }

    public function reverse(User $user, Sale $sale): bool
    {
        return $user->is_active && $user->role === UserRole::Manager;
    }
}

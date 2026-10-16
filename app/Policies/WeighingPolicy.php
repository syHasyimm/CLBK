<?php

namespace App\Policies;

use App\Enums\TransactionWorkflow;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\Weighing;

class WeighingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function create(User $user): bool
    {
        return $user->is_active && in_array($user->role, [UserRole::Manager, UserRole::Driver], true);
    }

    public function view(User $user, Weighing $weighing): bool
    {
        $driverCanView = $weighing->workflow === TransactionWorkflow::Direct
            ? $weighing->created_by === $user->id
            : $weighing->trip?->driver_id === $user->id;

        return $user->is_active && ($user->role !== UserRole::Driver || $driverCanView)
            && ($user->role !== UserRole::Cashier || $weighing->status !== 'draft');
    }

    public function update(User $user, Weighing $weighing): bool
    {
        return $this->create($user) && $this->view($user, $weighing)
            && $weighing->created_by === $user->id;
    }

    public function post(User $user, Weighing $weighing): bool
    {
        return $this->update($user, $weighing);
    }

    public function delete(User $user, Weighing $weighing): bool
    {
        return $this->update($user, $weighing);
    }

    public function void(User $user, Weighing $weighing): bool
    {
        return $user->is_active && $user->role === UserRole::Manager;
    }
}

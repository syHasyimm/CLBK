<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Trip;
use App\Models\User;

class TripPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function create(User $user): bool
    {
        return $user->is_active && in_array($user->role, [UserRole::Manager, UserRole::Driver], true);
    }

    public function view(User $user, Trip $trip): bool
    {
        return $user->is_active && ($user->role !== UserRole::Driver || $trip->driver_id === $user->id);
    }
}

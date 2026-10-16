<?php

namespace App\Queries;

use App\Enums\TransactionWorkflow;
use App\Enums\UserRole;
use App\Models\Trip;
use App\Models\User;
use App\Models\Weighing;
use Illuminate\Database\Eloquent\Builder;

class OperationalScope
{
    public static function trips(User $actor): Builder
    {
        return Trip::query()->when($actor->role === UserRole::Driver, fn ($query) => $query->where('driver_id', $actor->id));
    }

    public static function weighings(User $actor): Builder
    {
        return Weighing::query()->when($actor->role === UserRole::Driver,
            fn ($query) => $query->where(fn ($q) => $q
                ->where(fn ($direct) => $direct->where('workflow', TransactionWorkflow::Direct)->where('created_by', $actor->id))
                ->orWhere(fn ($legacy) => $legacy->where('workflow', TransactionWorkflow::Legacy)
                    ->whereHas('trip', fn ($trip) => $trip->where('driver_id', $actor->id)))))
            ->when($actor->role === UserRole::Cashier, fn ($query) => $query->where('status', '!=', 'draft'));
    }
}

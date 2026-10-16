<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['number', 'vehicle_id', 'driver_id', 'planned_factory_id', 'business_date', 'status', 'departed_at', 'closed_at', 'manual_load_grams', 'tbs_purchase_cost_snapshot_idr', 'tbs_loading_cost_snapshot_idr', 'cost_status', 'version', 'created_by'])]
class Trip extends Model
{
    protected function casts(): array
    {
        return ['departed_at' => 'immutable_datetime', 'closed_at' => 'immutable_datetime', 'manual_load_grams' => 'integer', 'tbs_purchase_cost_snapshot_idr' => 'integer', 'tbs_loading_cost_snapshot_idr' => 'integer', 'version' => 'integer'];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function plannedFactory(): BelongsTo
    {
        return $this->belongsTo(Factory::class, 'planned_factory_id');
    }

    public function weighings(): HasMany
    {
        return $this->hasMany(Weighing::class);
    }
}

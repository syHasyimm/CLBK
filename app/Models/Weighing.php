<?php

namespace App\Models;

use App\Enums\TransactionWorkflow;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['number', 'customer_id', 'vehicle_id', 'workflow', 'trip_id', 'weighed_at', 'business_date', 'backdate_reason', 'price_schedule_id', 'loading_rate_snapshot_idr', 'loading_basis_grams', 'total_purchase_idr', 'total_loading_wage_idr', 'status', 'posted_at', 'posted_by', 'voided_at', 'voided_by', 'void_reason', 'replaces_weighing_id', 'version', 'created_by'])]
class Weighing extends Model
{
    protected function casts(): array
    {
        return ['workflow' => TransactionWorkflow::class, 'vehicle_id' => 'integer', 'weighed_at' => 'immutable_datetime', 'posted_at' => 'immutable_datetime', 'voided_at' => 'immutable_datetime', 'price_schedule_id' => 'integer', 'loading_rate_snapshot_idr' => 'integer', 'loading_basis_grams' => 'integer', 'total_purchase_idr' => 'integer', 'total_loading_wage_idr' => 'integer', 'version' => 'integer'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(WeighingItem::class);
    }

    public function loaders(): HasMany
    {
        return $this->hasMany(WeighingLoader::class);
    }

    public function priceSchedule(): BelongsTo
    {
        return $this->belongsTo(PriceSchedule::class);
    }
}

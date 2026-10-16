<?php

namespace App\Models;

use App\Enums\TransactionWorkflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

abstract class Sale extends Model
{
    protected $fillable = ['number', 'vehicle_id', 'workflow', 'trip_id', 'factory_id', 'factory_price_schedule_id', 'invoice_number', 'initial_load_grams', 'sorted_out_grams', 'accepted_grams', 'weight_difference_grams', 'variance_approved_by', 'variance_approved_version', 'variance_reason', 'buyer_name', 'buyer_contact', 'manual_sold_grams', 'sold_at', 'business_date', 'sell_price_snapshot_idr', 'deduction_idr', 'deduction_reason', 'notes', 'correction_reason', 'revenue_idr', 'purchase_cost_released_idr', 'loading_cost_released_idr', 'cost_status', 'status', 'posted_at', 'posted_by', 'reversed_at', 'reversal_business_date', 'reversed_by', 'reversal_reason', 'replaces_sale_id', 'version', 'created_by'];

    protected function casts(): array
    {
        return ['workflow' => TransactionWorkflow::class, 'vehicle_id' => 'integer', 'sold_at' => 'immutable_datetime', 'posted_at' => 'immutable_datetime', 'reversed_at' => 'immutable_datetime',
            'sell_price_snapshot_idr' => 'integer', 'deduction_idr' => 'integer', 'revenue_idr' => 'integer', 'version' => 'integer',
            'purchase_cost_released_idr' => 'integer', 'loading_cost_released_idr' => 'integer', 'variance_approved_version' => 'integer'];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    abstract public function kind(): string;
}

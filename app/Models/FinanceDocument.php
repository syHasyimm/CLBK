<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

abstract class FinanceDocument extends Model
{
    protected $fillable = ['number', 'customer_id', 'worker_id', 'factory_sale_id', 'sort_sale_id', 'expense_category_id', 'trip_id',
        'cash_account_id', 'occurred_at', 'business_date', 'amount_idr', 'method', 'reference', 'notes', 'description',
        'period_start', 'period_end', 'backdate_reason', 'status', 'posted_at', 'version', 'created_by', 'reversed_at',
        'reversal_business_date', 'reversed_by', 'reversal_reason', 'return_reference'];

    protected function casts(): array
    {
        return ['occurred_at' => 'immutable_datetime', 'posted_at' => 'immutable_datetime', 'reversed_at' => 'immutable_datetime',
            'amount_idr' => 'integer', 'version' => 'integer'];
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

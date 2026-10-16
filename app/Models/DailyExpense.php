<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyExpense extends FinanceDocument
{
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }
}

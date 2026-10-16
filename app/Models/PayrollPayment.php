<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollPayment extends FinanceDocument
{
    public function worker(): BelongsTo
    {
        return $this->belongsTo(LoadingWorker::class, 'worker_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['weighing_id', 'worker_id', 'member_count_snapshot', 'share_amount_idr', 'allocation_order'])]
class WeighingLoader extends Model
{
    protected function casts(): array
    {
        return ['member_count_snapshot' => 'integer', 'share_amount_idr' => 'integer', 'allocation_order' => 'integer'];
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(LoadingWorker::class);
    }
}

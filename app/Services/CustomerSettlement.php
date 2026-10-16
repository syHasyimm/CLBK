<?php

namespace App\Services;

use App\Queries\PaymentBalances;
use Illuminate\Support\Collection;

class CustomerSettlement
{
    public function rows(int $customer): Collection
    {
        return app(PaymentBalances::class)->customers(true)->where('customer_id', $customer)
            ->orderBy('weighings.weighed_at')->orderBy('weighings.id')->get();
    }

    public function total(int $customer): int
    {
        return (int) $this->rows($customer)->sum(fn ($row) => $row->total_purchase_idr - (int) $row->paid_idr);
    }
}

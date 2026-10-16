<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\Sale;
use App\Models\Trip;
use App\Models\User;
use App\Queries\PaymentBalances;

class OperationalPresenter
{
    public function trip(User $user, Trip $trip): array
    {
        $data = $trip->toArray();
        if ($user->role !== UserRole::Manager) {
            unset($data['tbs_purchase_cost_snapshot_idr'], $data['tbs_loading_cost_snapshot_idr']);
        }

        return $data;
    }

    public function costs(User $user, array $data): array
    {
        if ($user->role !== UserRole::Manager) {
            foreach (array_keys($data) as $key) {
                if (str_contains($key, 'cost_') || str_contains($key, 'margin') || in_array($key, ['purchase', 'loading'], true)) {
                    if ($key !== 'cost_status') {
                        unset($data[$key]);
                    }
                }
            }
        }
        if (isset($data['items'])) {
            $data['items'] = array_map(fn ($item) => $this->costs($user, $item), $data['items']);
        }

        return $data;
    }

    public function sale(User $user, Sale $sale): array
    {
        $data = $this->costs($user, $sale->toArray());
        $data['trip'] = $sale->trip ? $this->trip($user, $sale->trip) : null;
        $data['vehicle'] = $sale->vehicle->only(['id', 'plate_number']);
        // Retur is inventory, not a second customer entitlement.
        if (isset($data['factory_return'])) {
            $data['factory_return'] = $this->costs($user, $data['factory_return']);
            unset($data['factory_return']['lot']);
        }
        $paid = app(PaymentBalances::class)->salePaid($sale);
        $data['received_idr'] = $paid;
        $data['receivable_idr'] = $sale->status === 'posted' ? $sale->revenue_idr - $paid : 0;
        $data['payment_status'] = $sale->status === 'posted' ? app(PaymentBalances::class)->status($sale->revenue_idr, $paid) : null;

        return $data;
    }
}

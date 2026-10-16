<?php

namespace App\Queries;

use App\Enums\FinanceKind;

final class FinanceEntitlements
{
    public function query(FinanceKind $kind, array $filters, bool $onlyOutstanding = false)
    {
        $customer = $kind === FinanceKind::Customer;
        $query = $customer ? app(PaymentBalances::class)->customers($onlyOutstanding) : app(PaymentBalances::class)->payroll($onlyOutstanding);
        foreach (['from' => '>=', 'to' => '<='] as $key => $operator) {
            if (! empty($filters[$key])) {
                $query->where(($customer ? 'weighings' : 'w').'.business_date', $operator, $filters[$key]);
            }
        }

        return $query;
    }
}

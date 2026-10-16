<?php

namespace App\Queries;

use App\Models\Sale;
use App\Models\Weighing;
use App\Models\WeighingLoader;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PaymentBalances
{
    public function customers(bool $onlyOutstanding = false): Builder
    {
        $query = $this->withCustomerPaid(Weighing::query()->where('weighings.status', 'posted'));
        if ($onlyOutstanding) {
            $balances = DB::query()->fromSub((clone $query)->toBase(), 'customer_balances')
                ->select('id')->whereColumn('total_purchase_idr', '>', 'paid_idr');
            $query->whereIn('weighings.id', $balances);
        }

        return $query;
    }

    public function withCustomerPaid(Builder $query): Builder
    {
        return $query->select('weighings.*')
            ->selectSub(DB::table('customer_payment_allocations as a')->join('customer_payments as p', 'p.id', '=', 'a.customer_payment_id')
                ->whereColumn('a.weighing_id', 'weighings.id')->where('p.status', 'posted')->selectRaw('COALESCE(SUM(a.amount_idr),0)'), 'paid_idr');
    }

    public function payroll(bool $onlyOutstanding = false): Builder
    {
        $query = WeighingLoader::query()->join('weighings as w', 'w.id', '=', 'weighing_loaders.weighing_id')->where('w.status', 'posted')
            ->select('weighing_loaders.*', 'w.number', 'w.weighed_at', 'w.business_date', 'w.loading_basis_grams', 'w.loading_rate_snapshot_idr')
            ->selectSub(DB::table('payroll_payment_allocations as a')->join('payroll_payments as p', 'p.id', '=', 'a.payroll_payment_id')
                ->whereColumn('a.weighing_loader_id', 'weighing_loaders.id')->where('p.status', 'posted')->selectRaw('COALESCE(SUM(a.amount_idr),0)'), 'paid_idr');
        if ($onlyOutstanding) {
            $balances = DB::query()->fromSub((clone $query)->toBase(), 'payroll_balances')
                ->select('id')->whereColumn('share_amount_idr', '>', 'paid_idr');
            $query->whereIn('weighing_loaders.id', $balances);
        }

        return $query;
    }

    public function customerPaid(int $id): int
    {
        return (int) DB::table('customer_payment_allocations as a')->join('customer_payments as p', 'p.id', '=', 'a.customer_payment_id')
            ->where('a.weighing_id', $id)->where('p.status', 'posted')->sum('a.amount_idr');
    }

    public function salePaid(Sale $sale): int
    {
        return (int) DB::table('sale_receipts')->where($sale->kind().'_sale_id', $sale->id)->where('status', 'posted')->sum('amount_idr');
    }

    public function status(int $total, int $paid): string
    {
        return $paid >= $total ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');
    }
}

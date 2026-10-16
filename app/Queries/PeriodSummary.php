<?php

namespace App\Queries;

use App\Actions\Sales\SaveSale;
use App\Enums\UserRole;
use App\Models\DailyExpense;
use App\Models\User;
use App\Services\BusinessCash;
use App\Services\CashLedger;
use App\Support\BusinessClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PeriodSummary
{
    public const FIELDS = ['weighing_count', 'tbs_bought_grams', 'sort_bought_grams', 'factory_sold_grams', 'sort_sold_grams',
        'purchase_idr', 'revenue_idr', 'loading_idr', 'expense_idr'];

    public function get(User $actor, array $filters): array
    {
        $clock = app(BusinessClock::class);
        $from = $filters['from'];
        $to = $filters['to'];
        $start = CarbonImmutable::parse($from, $clock->timezone())->startOfDay()->utc();
        $end = CarbonImmutable::parse($to, $clock->timezone())->endOfDay()->utc();
        $vehicle = $filters['vehicle_id'] ?? null;
        $empty = array_fill_keys(self::FIELDS, 0);
        $total = $empty;
        $days = [];
        $vehicles = [];
        $add = function (string $date, ?int $vehicleId, array $values) use (&$total, &$days, &$vehicles, $empty) {
            $days[$date] ??= $empty;
            if ($vehicleId) {
                $vehicles[$vehicleId] ??= $empty;
            }
            foreach ($values as $field => $value) {
                $total[$field] += $value;
                $days[$date][$field] += $value;
                if ($vehicleId) {
                    $vehicles[$vehicleId][$field] += $value;
                }
            }
        };
        $weighings = OperationalScope::weighings($actor)->whereIn('status', ['posted', 'voided'])
            ->when($vehicle, fn ($q) => $q->where('vehicle_id', $vehicle))
            ->where(fn ($q) => $q->whereBetween('business_date', [$from, $to])->orWhereBetween('voided_at', [$start, $end]))
            ->select('weighings.*')
            ->selectSub(DB::table('weighing_items')->whereColumn('weighing_id', 'weighings.id')->where('fruit_type', 'TBS')->selectRaw('COALESCE(SUM(weight_grams),0)'), 'tbs_grams')
            ->selectSub(DB::table('weighing_items')->whereColumn('weighing_id', 'weighings.id')->where('fruit_type', 'SORT')->selectRaw('COALESCE(SUM(weight_grams),0)'), 'sort_grams');
        foreach ($weighings->cursor() as $row) {
            $values = ['weighing_count' => 1, 'tbs_bought_grams' => (int) $row->tbs_grams, 'sort_bought_grams' => (int) $row->sort_grams,
                'purchase_idr' => $row->total_purchase_idr, 'loading_idr' => $row->total_loading_wage_idr];
            if ($row->business_date >= $from && $row->business_date <= $to) {
                $add($row->business_date, $row->vehicle_id, $values);
            }
            if ($row->voided_at && $row->voided_at->betweenIncluded($start, $end)) {
                $add($clock->date($row->voided_at), $row->vehicle_id, array_map(fn ($v) => -$v, $values));
            }
        }
        foreach (['factory', 'sort'] as $kind) {
            $query = SaveSale::model($kind)::query()->whereIn('status', ['posted', 'reversed'])
                ->when($vehicle, fn ($q) => $q->where('vehicle_id', $vehicle))
                ->where(fn ($q) => $q->whereBetween('business_date', [$from, $to])->orWhereBetween('reversal_business_date', [$from, $to]));
            if ($actor->role === UserRole::Driver) {
                $query->where(fn ($q) => $q->where(fn ($d) => $d->where('workflow', 'direct')->where('created_by', $actor->id))
                    ->orWhere(fn ($l) => $l->where('workflow', 'legacy')->whereHas('trip', fn ($t) => $t->where('driver_id', $actor->id))));
            }
            foreach ($query->cursor() as $row) {
                $values = [$kind.'_sold_grams' => (int) ($kind === 'factory' ? $row->accepted_grams : $row->manual_sold_grams), 'revenue_idr' => $row->revenue_idr];
                if ($row->business_date >= $from && $row->business_date <= $to) {
                    $add($row->business_date, $row->vehicle_id, $values);
                }
                if ($row->reversal_business_date >= $from && $row->reversal_business_date <= $to) {
                    $add($row->reversal_business_date, $row->vehicle_id, array_map(fn ($v) => -$v, $values));
                }
            }
        }
        $financial = $actor->role !== UserRole::Driver;
        // Daily expenses no longer belong to vehicles. They are included only in the whole-business period.
        if ($financial && ! $vehicle) {
            foreach (DailyExpense::query()->where(fn ($q) => $q->whereBetween('business_date', [$from, $to])->orWhereBetween('reversal_business_date', [$from, $to]))->cursor() as $row) {
                if ($row->business_date >= $from && $row->business_date <= $to) {
                    $add($row->business_date, null, ['expense_idr' => $row->amount_idr]);
                }
                if ($row->reversal_business_date >= $from && $row->reversal_business_date <= $to) {
                    $add($row->reversal_business_date, null, ['expense_idr' => -$row->amount_idr]);
                }
            }
        }
        $manager = $actor->role === UserRole::Manager;
        $present = function (array $values) use ($manager, $financial, $vehicle) {
            if ($manager && ! $vehicle) {
                $values['period_difference_idr'] = $values['revenue_idr'] - $values['purchase_idr'] - $values['loading_idr'] - $values['expense_idr'];
            }
            if (! $financial) {
                unset($values['expense_idr']);
            }
            if (! $manager) {
                unset($values['period_difference_idr']);
            }

            return $values;
        };
        ksort($days);
        $plates = DB::table('vehicles')->whereIn('id', array_keys($vehicles))->pluck('plate_number', 'id');
        $result = ['totals' => $present($total),
            'days' => array_map(fn ($date, $values) => ['date' => $date] + $present($values), array_keys($days), array_values($days)),
            'vehicles' => array_map(fn ($id, $values) => ['id' => $id, 'plate_number' => $plates[$id]] + array_diff_key($present($values), ['period_difference_idr' => true, 'expense_idr' => true]), array_keys($vehicles), array_values($vehicles)),
            'balances' => null, 'cashflow' => null, 'as_of_date' => $clock->date($clock->now())];
        if ($financial) {
            $balances = app(PaymentBalances::class);
            $debts = $balances->customers()->when($vehicle, fn ($q) => $q->where('vehicle_id', $vehicle));
            $result['balances'] = ['customer_debt_idr' => (int) DB::query()->fromSub($debts->toBase(), 'd')
                ->selectRaw('COALESCE(SUM(total_purchase_idr-paid_idr),0) AS remaining')->value('remaining'), 'sale_receivable_idr' => 0];
            foreach (['factory', 'sort'] as $kind) {
                $sales = DB::table($kind.'_sales')->where('status', 'posted')->when($vehicle, fn ($q) => $q->where('vehicle_id', $vehicle));
                $result['balances']['sale_receivable_idr'] += (int) $sales->selectRaw("COALESCE(SUM(revenue_idr-(SELECT COALESCE(SUM(amount_idr),0) FROM sale_receipts WHERE {$kind}_sale_id={$kind}_sales.id AND status='posted')),0) AS remaining")->value('remaining');
            }
            $account = app(BusinessCash::class)->account();
            $result['balances']['cash_idr'] = $account ? app(CashLedger::class)->balance($account->id) : 0;
            $payroll = $balances->payroll()->when($vehicle, fn ($q) => $q->where('w.vehicle_id', $vehicle));
            $result['balances']['payroll_debt_idr'] = (int) DB::query()->fromSub($payroll->toBase(), 'p')
                ->selectRaw('COALESCE(SUM(share_amount_idr-paid_idr),0) AS remaining')->value('remaining');
            $result['cashflow'] = app(CashFlowSummary::class)->get($from, $to);
        }

        return $result;
    }
}

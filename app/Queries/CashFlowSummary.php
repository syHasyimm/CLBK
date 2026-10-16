<?php

namespace App\Queries;

use App\Services\BusinessCash;
use Illuminate\Support\Facades\DB;

class CashFlowSummary
{
    public function get(string $from, string $to, ?int $account = null): array
    {
        $account ??= app(BusinessCash::class)->account()?->id ?? 0;
        $rows = DB::table('cash_accounts as a')->leftJoin('cash_entries as e', 'e.cash_account_id', '=', 'a.id')
            ->where('a.id', $account)->groupBy('a.id', 'a.name', 'a.type')
            ->select('a.id', 'a.name', 'a.type')
            ->selectRaw("COALESCE(SUM(CASE WHEN e.business_date<? THEN CASE e.direction WHEN 'in' THEN e.amount_idr ELSE -e.amount_idr END ELSE 0 END),0) AS opening_idr", [$from])
            ->selectRaw("COALESCE(SUM(CASE WHEN e.business_date BETWEEN ? AND ? AND e.direction='in' THEN e.amount_idr ELSE 0 END),0) AS incoming_idr", [$from, $to])
            ->selectRaw("COALESCE(SUM(CASE WHEN e.business_date BETWEEN ? AND ? AND e.direction='out' THEN e.amount_idr ELSE 0 END),0) AS outgoing_idr", [$from, $to])
            ->orderBy('a.name')->get()->map(function ($row) {
                $values = (array) $row;
                foreach (['opening_idr', 'incoming_idr', 'outgoing_idr'] as $key) {
                    $values[$key] = (int) $values[$key];
                }
                $values['closing_idr'] = $values['opening_idr'] + $values['incoming_idr'] - $values['outgoing_idr'];

                return $values;
            })->all();

        $totals = [];
        foreach (['opening_idr', 'incoming_idr', 'outgoing_idr', 'closing_idr'] as $key) {
            $totals[$key] = array_sum(array_column($rows, $key));
        }

        return ['accounts' => $rows, 'totals' => $totals];
    }
}

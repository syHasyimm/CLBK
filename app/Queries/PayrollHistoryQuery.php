<?php

namespace App\Queries;

use App\Enums\FinanceKind;
use App\Models\LoadingWorker;
use App\Models\PayrollPayment;
use Illuminate\Support\Facades\DB;

final class PayrollHistoryQuery
{
    public function data(array $filters): array
    {
        $worker = ! empty($filters['worker_id']) ? LoadingWorker::findOrFail($filters['worker_id']) : null;
        $settled = $filters['view'] === 'settled';
        $query = $settled ? app(FinanceEntitlements::class)->query(FinanceKind::Payroll, $filters) : PayrollPayment::query();
        if ($worker) {
            $query->where($settled ? 'weighing_loaders.worker_id' : 'worker_id', $worker->id);
        }
        if (! empty($filters['q'])) {
            $search = '%'.$filters['q'].'%';
            $query->where(fn ($history) => $history->where($settled ? 'w.number' : 'number', 'like', $search)
                ->orWhereHas('worker', fn ($employee) => $employee->where('code', 'like', $search)->orWhere('name', 'like', $search)));
        }

        $rows = null;
        $documents = null;
        if ($settled) {
            $balances = DB::query()->fromSub((clone $query)->toBase(), 'settled_payroll_balances')
                ->select('id')->where('share_amount_idr', '>', 0)->whereColumn('paid_idr', 'share_amount_idr');
            $query->whereIn('weighing_loaders.id', $balances);
            $totals = DB::query()->fromSub((clone $query)->toBase(), 'e')
                ->selectRaw('COUNT(*) AS record_count, COUNT(DISTINCT worker_id) AS worker_count, COALESCE(SUM(share_amount_idr),0) AS total_idr, 0 AS reversed_idr')->first();
            $rows = $query->with('worker:id,code,name,is_active')->orderByDesc('w.weighed_at')->orderByDesc('weighing_loaders.id')
                ->paginate(50)->withQueryString()->through(fn ($row) => ['id' => $row->id, 'weighing_id' => $row->weighing_id,
                    'number' => $row->number, 'weighed_at' => $row->weighed_at, 'business_date' => $row->business_date,
                    'total_idr' => (int) $row->share_amount_idr, 'paid_idr' => (int) $row->paid_idr,
                    'remaining_idr' => (int) $row->share_amount_idr - (int) $row->paid_idr, 'items' => [],
                    'basis_grams' => (int) $row->loading_basis_grams, 'rate_idr' => (int) $row->loading_rate_snapshot_idr,
                    'member_count' => (int) $row->member_count_snapshot, 'worker' => $row->worker->only(['id', 'code', 'name', 'is_active'])]);
        } else {
            foreach (['from' => '>=', 'to' => '<=', 'status' => '='] as $key => $operator) {
                if (! empty($filters[$key])) {
                    $query->where($key === 'status' ? 'status' : 'business_date', $operator, $filters[$key]);
                }
            }
            $totals = DB::query()->fromSub((clone $query)->toBase(), 'p')
                ->selectRaw("COUNT(*) AS record_count, COUNT(DISTINCT worker_id) AS worker_count, COALESCE(SUM(CASE WHEN status='posted' THEN amount_idr ELSE 0 END),0) AS total_idr, COALESCE(SUM(CASE WHEN status='reversed' THEN amount_idr ELSE 0 END),0) AS reversed_idr")->first();
            $documents = $query->with('worker:id,code,name,is_active', 'cashAccount:id,name', 'creator:id,name')
                ->orderByDesc('occurred_at')->orderByDesc('id')->paginate(20)->withQueryString();
        }

        return ['view' => $filters['view'], 'filters' => $filters,
            'worker' => $worker?->only(['id', 'code', 'name', 'is_active']), 'rows' => $rows, 'documents' => $documents,
            'summary' => ['record_count' => (int) $totals->record_count, 'worker_count' => (int) $totals->worker_count,
                'total_idr' => (int) $totals->total_idr, 'reversed_idr' => (int) $totals->reversed_idr]];
    }
}

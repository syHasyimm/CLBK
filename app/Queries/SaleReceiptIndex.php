<?php

namespace App\Queries;

use App\Actions\Sales\SaveSale;
use App\Models\SaleReceipt;
use App\Support\OperationalPresenter;
use Illuminate\Http\Request;

final class SaleReceiptIndex
{
    private function receiptSales(string $kind, array $filters)
    {
        $model = SaveSale::model($kind);
        $sales = $model::where('status', 'posted')->with('vehicle:id,plate_number');
        if ($kind === 'factory') {
            $sales->with('factory:id,name');
        }
        $this->searchReceiptSales($sales, $kind, $filters['q'] ?? '');
        foreach (['from' => '>=', 'to' => '<='] as $key => $operator) {
            if (! empty($filters[$key])) {
                $sales->where('business_date', $operator, $filters[$key]);
            }
        }
        $paidSql = "(SELECT COALESCE(SUM(amount_idr),0) FROM sale_receipts WHERE {$kind}_sale_id={$kind}_sales.id AND status='posted')";
        if ($filters['status'] !== '') {
            $sales->whereRaw('revenue_idr '.($filters['status'] === 'paid' ? '=' : '>').' '.$paidSql);
        }
        if ($filters['status'] === 'partial') {
            $sales->whereRaw($paidSql.' > 0');
        }

        return $sales;
    }

    private function searchReceiptSales($query, string $kind, ?string $text): void
    {
        if ($text === null || $text === '') {
            return;
        }
        $search = '%'.$text.'%';
        $query->where(function ($sale) use ($kind, $search) {
            $sale->where('number', 'like', $search);
            if ($kind === 'factory') {
                $sale->orWhere('invoice_number', 'like', $search)
                    ->orWhereHas('factory', fn ($factory) => $factory->where('name', 'like', $search));
            } else {
                $sale->orWhere('buyer_name', 'like', $search)->orWhere('buyer_contact', 'like', $search);
            }
        });
    }

    public function data(Request $request): array
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:unpaid,partial,paid'],
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])]]);
        $filters['status'] = $request->has('status') ? ($filters['status'] ?? '') : 'unpaid';
        $documents = SaleReceipt::with('cashAccount:id,name', 'creator:id,name');
        if (! empty($filters['q'])) {
            $documents->where(function ($query) use ($filters) {
                $query->where('number', 'like', '%'.$filters['q'].'%');
                foreach (['factory', 'sort'] as $kind) {
                    $sales = SaveSale::model($kind)::select('id');
                    $this->searchReceiptSales($sales, $kind, $filters['q']);
                    $query->orWhereIn($kind.'_sale_id', $sales);
                }
            });
        }
        foreach (['from' => '>=', 'to' => '<='] as $key => $operator) {
            if (! empty($filters[$key])) {
                $documents->where('business_date', $operator, $filters[$key]);
            }
        }

        return ['filters' => $filters,
            'factorySales' => $this->receiptSales('factory', $filters)->orderByDesc('id')->paginate(20)->withQueryString()
                ->through(fn ($sale) => app(OperationalPresenter::class)->sale($request->user(), $sale)),
            'sortSales' => $this->receiptSales('sort', $filters)->orderByDesc('id')->paginate(20, ['*'], 'sort_page')->withQueryString()
                ->through(fn ($sale) => app(OperationalPresenter::class)->sale($request->user(), $sale)),
            'documents' => $documents->orderByDesc('id')->paginate(20, ['*'], 'receipts_page')->withQueryString()];
    }
}

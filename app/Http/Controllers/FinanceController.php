<?php

namespace App\Http\Controllers;

use App\Actions\Finance\PostFinance;
use App\Actions\Finance\ReverseFinance;
use App\Actions\Sales\SaveSale;
use App\Enums\FinanceKind;
use App\Http\Requests\Finance\StoreFinanceRequest;
use App\Models\Customer;
use App\Models\LoadingWorker;
use App\Models\PayrollPayment;
use App\Models\Trip;
use App\Queries\FinanceEntitlements;
use App\Queries\PaymentBalances;
use App\Queries\PayrollHistoryQuery;
use App\Queries\SaleReceiptIndex;
use App\Services\CustomerSettlement;
use App\Support\BusinessClock;
use App\Support\Database\ReadSnapshot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class FinanceController extends Controller
{
    private function kind(Request $request): FinanceKind
    {
        return FinanceKind::from($request->route('kind'));
    }

    private function filters(Request $request): array
    {
        return $request->validate(['q' => ['nullable', 'string', 'max:100'], 'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
            'status' => ['nullable', 'in:unpaid,paid,posted,reversed']]);
    }

    private function entitlements(FinanceKind $kind, array $filters, bool $onlyOutstanding = false)
    {
        return app(FinanceEntitlements::class)->query($kind, $filters, $onlyOutstanding);
    }

    public function worklist(Request $request)
    {
        $kind = $this->kind($request);
        Gate::authorize('viewAny', $kind->model());
        $filters = $this->filters($request);
        $filters['status'] = 'unpaid';
        $customer = $kind === FinanceKind::Customer;
        $owner = $customer ? 'customer_id' : 'worker_id';
        $value = $customer ? 'total_purchase_idr' : 'share_amount_idr';
        $master = $customer ? 'customers' : 'loading_workers';
        $query = DB::query()->fromSub($this->entitlements($kind, $filters, true)->toBase(), 'e')->join($master.' as m', 'm.id', '=', 'e.'.$owner)
            ->selectRaw("m.id, m.code, m.name, m.is_active, COUNT(*) AS ticket_count, MIN(e.business_date) AS first_work_date, MAX(e.business_date) AS last_work_date, SUM(e.{$value}) AS total_idr, SUM(e.paid_idr) AS paid_idr, SUM(e.{$value}-e.paid_idr) AS remaining_idr")
            ->groupBy('m.id', 'm.code', 'm.name', 'm.is_active')
            ->havingRaw("SUM(e.{$value}-e.paid_idr) >0");
        if (! empty($filters['q'])) {
            $query->where(fn ($q) => $q->where('m.name', 'like', '%'.$filters['q'].'%')->orWhere('m.code', 'like', '%'.$filters['q'].'%'));
        }
        $cards = $query->orderBy('m.name')->paginate(20)->withQueryString()->through(function ($row) use ($kind, $owner, $customer, $filters) {
            $row->last_payment = $kind->model()::where($owner, $row->id)->where('status', 'posted')->orderByDesc('occurred_at')->first(['id', 'number', 'amount_idr', 'occurred_at']);
            $preview = $this->entitlements($kind, $filters, true)->where($owner, $row->id);
            if ($customer) {
                $preview->with('items');
            }
            $row->tickets = $preview->orderByDesc($customer ? 'weighings.weighed_at' : 'w.weighed_at')->limit(3)->get()->map(fn ($ticket) => [
                'weighing_id' => $customer ? $ticket->id : $ticket->weighing_id, 'number' => $ticket->number,
                'business_date' => $ticket->business_date, 'basis_grams' => $ticket->loading_basis_grams,
                'rate_idr' => $ticket->loading_rate_snapshot_idr, 'member_count' => $customer ? null : $ticket->member_count_snapshot,
                'items' => $customer ? $ticket->items->map->only(['fruit_type', 'weight_grams', 'buy_price_snapshot_idr']) : [],
            ]);

            return $row;
        });

        return Inertia::render('Finance/Worklist', ['kind' => $kind->value, 'cards' => $cards, 'filters' => $filters]);
    }

    public function payee(Request $request)
    {
        $kind = $this->kind($request);
        Gate::authorize('create', $kind->model());
        $customer = $kind === FinanceKind::Customer;
        $recipient = ($customer ? Customer::class : LoadingWorker::class)::findOrFail($request->route('recipient'));
        $filters = $this->filters($request);
        if (! $customer) {
            $filters['from'] ??= app(BusinessClock::class)->now()->setTimezone(app(BusinessClock::class)->timezone())->startOfMonth()->toDateString();
            $filters['to'] ??= app(BusinessClock::class)->date(app(BusinessClock::class)->now());
        }

        return app(ReadSnapshot::class)->run(function () use ($kind, $customer, $recipient, $filters) {
            $owner = $customer ? 'customer_id' : 'worker_id';
            $query = $this->entitlements($kind, $filters)->where($owner, $recipient->id);
            $value = $customer ? 'total_purchase_idr' : 'share_amount_idr';
            $totals = DB::query()->fromSub((clone $query)->toBase(), 'e')->selectRaw("COUNT(*) AS ticket_count, COALESCE(SUM(CASE WHEN {$value}>paid_idr THEN 1 ELSE 0 END),0) AS outstanding_count, COALESCE(SUM({$value}),0) AS total_idr, COALESCE(SUM(paid_idr),0) AS paid_idr, COALESCE(SUM({$value}-paid_idr),0) AS remaining_idr")->first();
            $query = $this->entitlements($kind, $filters, true)->where($owner, $recipient->id);
            if ($customer) {
                $query->with('items');
            }
            $rows = $query->orderBy($customer ? 'weighings.weighed_at' : 'w.weighed_at')->orderBy($customer ? 'weighings.id' : 'weighing_loaders.id')
                ->paginate(50)->withQueryString()->through(fn ($row) => ['id' => $row->id, 'weighing_id' => $customer ? $row->id : $row->weighing_id,
                    'number' => $row->number, 'weighed_at' => $row->weighed_at, 'business_date' => $row->business_date,
                    'total_idr' => (int) $row->$value, 'paid_idr' => (int) $row->paid_idr, 'remaining_idr' => (int) $row->$value - (int) $row->paid_idr,
                    'items' => $customer ? $row->items->map->only(['fruit_type', 'weight_grams', 'buy_price_snapshot_idr', 'purchase_amount_idr']) : [],
                    'basis_grams' => $row->loading_basis_grams, 'rate_idr' => $row->loading_rate_snapshot_idr, 'member_count' => $customer ? null : $row->member_count_snapshot]);
            $history = $kind->model()::with('cashAccount:id,name')->where($owner, $recipient->id)->orderByDesc('id')->paginate(15, ['*'], 'history_page')->withQueryString();
            $settlement = null;
            if ($customer) {
                $all = app(CustomerSettlement::class)->rows($recipient->id);
                $settlement = ['remaining_idr' => (int) $all->sum(fn ($row) => $row->total_purchase_idr - (int) $row->paid_idr), 'ticket_count' => $all->count()];
            }

            return Inertia::render('Finance/Payee', ['kind' => $kind->value, 'recipient' => $recipient->only(['id', 'code', 'name', 'is_active']),
                'rows' => $rows, 'totals' => $totals, 'history' => $history, 'filters' => $filters,
                'localNow' => app(BusinessClock::class)->local(app(BusinessClock::class)->now()), 'customerSettlement' => $settlement]);
        });
    }

    public function payrollHistory(Request $request)
    {
        Gate::authorize('viewAny', PayrollPayment::class);
        $filters = $request->validate(['view' => ['nullable', 'in:settled,payments'], 'q' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
            'worker_id' => ['nullable', 'integer', 'min:1'], 'status' => ['nullable', 'in:posted,reversed']]);
        $filters['view'] ??= 'settled';

        return app(ReadSnapshot::class)->run(fn () => Inertia::render('Finance/PayrollHistory', app(PayrollHistoryQuery::class)->data($filters)));
    }

    public function index(Request $request)
    {
        $kind = $this->kind($request);
        Gate::authorize('viewAny', $kind->model());
        if ($kind === FinanceKind::Receipt) {
            return $this->receiptIndex($request);
        }
        $filters = $this->filters($request);
        $query = $kind->model()::with('cashAccount:id,name', 'creator:id,name');
        foreach (['from' => '>=', 'to' => '<=', 'status' => '='] as $key => $operator) {
            if (! empty($filters[$key]) && ! in_array($filters[$key], ['unpaid', 'paid'], true)) {
                $query->where($key === 'status' ? 'status' : 'business_date', $operator, $filters[$key]);
            }
        }
        if (! empty($filters['q'])) {
            $query->where('number', 'like', '%'.$filters['q'].'%');
        }

        return Inertia::render('Finance/Index', ['kind' => $kind->value, 'documents' => $query->orderByDesc('id')->paginate(20)->withQueryString(), 'filters' => $filters]);
    }

    public function create(Request $request)
    {
        $kind = $this->kind($request);
        Gate::authorize('create', $kind->model());
        $sale = null;
        if ($kind === FinanceKind::Receipt) {
            $data = $request->validate(['sale_kind' => ['nullable', 'in:factory,sort'], 'sale_id' => ['required', 'integer', 'min:1']]);
            $saleKind = $data['sale_kind'] ?? 'factory';
            $record = SaveSale::model($saleKind)::findOrFail($data['sale_id']);
            Gate::authorize('view', $record);
            $paid = app(PaymentBalances::class)->salePaid($record);
            abort_unless($record->status === 'posted' && $paid < $record->revenue_idr, 422, 'Nota sudah lunas atau tidak sah.');
            $sale = ['id' => $record->id, 'kind' => $saleKind, 'number' => $record->number, 'total_idr' => $record->revenue_idr,
                'paid_idr' => $paid, 'remaining_idr' => $record->revenue_idr - $paid,
                'party' => $saleKind === 'factory' ? $record->factory->name : ($record->buyer_name ?: 'Pembeli sortir')];
        } else {
            abort_unless($kind === FinanceKind::Expense, 404);
        }

        return Inertia::render('Finance/Form', ['kind' => $kind->value, 'sale' => $sale, 'localNow' => app(BusinessClock::class)->local(app(BusinessClock::class)->now())]);
    }

    private function receiptIndex(Request $request)
    {
        return Inertia::render('Finance/SaleReceipts', app(SaleReceiptIndex::class)->data($request));
    }

    public function store(StoreFinanceRequest $request, PostFinance $action)
    {
        $kind = $this->kind($request);
        $record = $action->handle($request->user(), $kind, $request->validated());

        if ($kind === FinanceKind::Payroll) {
            return to_route('payroll.index')->with('success', 'Pembayaran gaji berhasil dicatat.');
        }

        return to_route('finance.show', ['kind' => $kind->value, 'document' => $record->id])->with('success', 'Voucher sah. Alokasi dan mutasi kas telah dicatat.');
    }

    public function show(Request $request)
    {
        $kind = $this->kind($request);
        $record = $kind->model()::with('cashAccount:id,name', 'creator:id,name')->findOrFail($request->route('document'));
        Gate::authorize('view', $record);
        $allocations = [];
        $party = null;
        $source = null;
        if ($kind === FinanceKind::Customer) {
            $party = $record->customer->name;
            $allocations = DB::table('customer_payment_allocations as a')->join('weighings as w', 'w.id', '=', 'a.weighing_id')->where('a.customer_payment_id', $record->id)->get(['w.id as weighing_id', 'w.number', 'w.business_date', 'a.amount_idr']);
        } elseif ($kind === FinanceKind::Payroll) {
            $party = $record->worker->name;
            $allocations = DB::table('payroll_payment_allocations as a')->join('weighing_loaders as l', 'l.id', '=', 'a.weighing_loader_id')->join('weighings as w', 'w.id', '=', 'l.weighing_id')->where('a.payroll_payment_id', $record->id)->get(['w.id as weighing_id', 'w.number', 'w.business_date', 'a.amount_idr']);
        } elseif ($kind === FinanceKind::Receipt) {
            $saleKind = $record->factory_sale_id ? 'factory' : 'sort';
            $sale = SaveSale::model($saleKind)::findOrFail($record->{$saleKind.'_sale_id'});
            $source = ['kind' => $saleKind, 'id' => $sale->id, 'number' => $sale->number];
            $party = $saleKind === 'factory' ? $sale->factory->name : ($sale->buyer_name ?: 'Pembeli sortir');
        } else {
            $party = $record->category->name;
            if ($record->trip_id) {
                $trip = Trip::findOrFail($record->trip_id);
                $source = ['kind' => 'trip', 'id' => $trip->id, 'number' => $trip->number];
            }
        }

        return Inertia::render('Finance/Show', ['kind' => $kind->value, 'record' => $record, 'party' => $party, 'source' => $source,
            'allocations' => $allocations, 'canReverse' => Gate::allows('reverse', $record),
            'attachments' => DB::table('attachments')->where('entity_type', $kind->value)->where('entity_id', $record->id)->get(['id', 'original_name', 'size_bytes'])]);
    }

    public function reverse(Request $request, ReverseFinance $action)
    {
        $kind = $this->kind($request);
        $record = $kind->model()::findOrFail($request->route('document'));
        $data = $request->validate(['version' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'max:1000'],
            'return_reference' => ['required', 'string', 'max:1000'], 'idempotency_key' => ['required', 'uuid']]);
        $action->handle($request->user(), $kind, $record, $data);

        return back()->with('success', 'Voucher dibalik pada hari ini. Histori asli tetap tersimpan.');
    }
}

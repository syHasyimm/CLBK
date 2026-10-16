<?php

namespace App\Http\Controllers;

use App\Actions\Finance\CreateBusinessCash;
use App\Actions\Finance\ManualCash;
use App\Enums\FinanceKind;
use App\Models\CashAccount;
use App\Services\BusinessCash;
use App\Services\CashLedger;
use App\Support\BusinessClock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class CashController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', FinanceKind::Customer->model());
        $filters = $request->validate(['cash_account_id' => ['nullable', 'integer', 'min:1'], 'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])]]);
        unset($filters['cash_account_id']);
        $account = app(BusinessCash::class)->account();
        $query = DB::table('cash_entries')->where('cash_account_id', $account?->id ?? 0);
        foreach (['from' => '>=', 'to' => '<='] as $key => $operator) {
            if (! empty($filters[$key])) {
                $query->where('business_date', $operator, $filters[$key]);
            }
        }
        $entries = $query->orderByDesc('occurred_at')->orderByDesc('id')->paginate(25)->appends($request->except('cash_account_id'))->through(function ($entry) {
            $source = $entry->reversal_of_id ? DB::table('cash_entries')->find($entry->reversal_of_id) : $entry;
            $entry->is_reversed = DB::table('cash_entries')->where('reversal_of_id', $entry->id)->exists();
            $entry->voucher = null;
            foreach (FinanceKind::cases() as $kind) {
                if ($source->{$kind->sourceColumn()}) {
                    $record = $kind->model()::findOrFail($source->{$kind->sourceColumn()});
                    $entry->voucher = ['kind' => $kind->value, 'id' => $record->id, 'number' => $record->number];
                }
            }

            return $entry;
        });

        return Inertia::render('Finance/Cash', ['account' => $account ? $account->toArray() + ['balance_idr' => app(CashLedger::class)->balance($account->id)] : null,
            'entries' => $entries, 'filters' => $filters, 'canManage' => Gate::allows('manage-finance-capital'),
            'canCreateAccount' => Gate::allows('manage-finance-capital') && ! CashAccount::query()->exists(),
            'localNow' => app(BusinessClock::class)->local(app(BusinessClock::class)->now())]);
    }

    public function createAccount(Request $request, CreateBusinessCash $action)
    {
        Gate::authorize('manage-finance-capital');
        $action->handle($request->user());

        return to_route('cash.index')->with('success', 'Kas Usaha berhasil dibuat. Saldo awal atau setoran modal dapat dicatat melalui mutasi kas.');
    }

    public function references(Request $request)
    {
        Gate::authorize('viewAny', FinanceKind::Customer->model());
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $kind = $request->route('referenceKind');
        $query = CashAccount::query()->whereKey(app(BusinessCash::class)->account()?->id ?? 0);
        if ($kind === 'accounts') {
            $query->where('is_active', true);
        }
        if (! empty($data['q'])) {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$data['q'].'%')->orWhere('code', 'like', '%'.$data['q'].'%'));
        }

        return response()->json($query->orderBy('code')->paginate(25)->through(fn ($account) => ['id' => $account->id,
            'label' => $account->code.' · '.$account->name.' · '.($account->type === 'bank' ? 'Bank' : 'Tunai').' · Saldo Rp'.number_format(app(CashLedger::class)->balance($account->id), 0, ',', '.').($account->is_active ? '' : ' · Nonaktif')]));
    }

    public function store(Request $request, ManualCash $action)
    {
        Gate::authorize('manage-finance-capital');
        $data = $request->validate(['cash_account_id' => ['nullable', 'integer', 'min:1'], 'kind' => ['required', 'in:opening,capital,withdrawal'],
            'amount_idr' => ['required', 'integer', 'between:1,100000000000000'], 'occurred_at' => ['required', 'string', 'max:19'],
            'reason' => ['required', 'string', 'max:1000'], 'reference' => ['nullable', 'string', 'max:255'],
            'backdate_reason' => ['nullable', 'string', 'max:1000'], 'idempotency_key' => ['required', 'uuid']]);
        $action->post($request->user(), $data);

        return to_route('cash.index')->with('success', 'Mutasi kas dicatat tanpa mengubah pendapatan atau laba.');
    }

    public function reverse(Request $request, int $entry, ManualCash $action)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000'], 'return_reference' => ['required', 'string', 'max:1000'], 'idempotency_key' => ['required', 'uuid']]);
        $action->reverse($request->user(), $entry, $data);

        return back()->with('success', 'Mutasi manual dibalik dengan jurnal kas lawan arah.');
    }
}

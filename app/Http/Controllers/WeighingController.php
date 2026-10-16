<?php

namespace App\Http\Controllers;

use App\Actions\Weighings\CreateDraftWeighing;
use App\Actions\Weighings\DeleteDraftWeighing;
use App\Actions\Weighings\PostWeighing;
use App\Actions\Weighings\SaveDirectWeighing;
use App\Actions\Weighings\VoidWeighing;
use App\Domain\Pricing\PriceSnapshotResolver;
use App\Domain\Weighings\DirectWeighingInput;
use App\Domain\Weighings\WeighingCalculator;
use App\Domain\Weighings\WeighingPreview;
use App\Enums\TransactionWorkflow;
use App\Enums\UserRole;
use App\Http\Requests\Weighings\SaveWeighingRequest;
use App\Http\Requests\Weighings\StoreDirectWeighingRequest;
use App\Models\Customer;
use App\Models\LoadingWorker;
use App\Models\Vehicle;
use App\Models\Weighing;
use App\Queries\OperationalScope;
use App\Queries\PaymentBalances;
use App\Services\DriverVehicleAssignment;
use App\Support\BusinessClock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class WeighingController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Weighing::class);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:draft,posted,voided'],
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])]]);
        $balances = app(PaymentBalances::class);
        $query = OperationalScope::weighings($request->user());
        if (! empty($filters['q'])) {
            $query->where(fn ($q) => $q->where('number', 'like', '%'.$filters['q'].'%')->orWhereHas('customer', fn ($c) => $c->where('name', 'like', '%'.$filters['q'].'%')));
        }
        foreach (['status' => '=', 'from' => '>=', 'to' => '<='] as $field => $operator) {
            if (! empty($filters[$field])) {
                $query->where($field === 'status' ? 'status' : 'business_date', $operator, $filters[$field]);
            }
        }

        $summary = (clone $query)->where('status', 'posted')->selectRaw('COUNT(*) as ticket_count,
            COALESCE(SUM(loading_basis_grams), 0) as weight_grams,
            COALESCE(SUM(total_purchase_idr), 0) as purchase_idr,
            COALESCE(SUM(total_loading_wage_idr), 0) as loading_wage_idr')->toBase()->first();

        $rows = $balances->withCustomerPaid($query)
            ->with('customer:id,name', 'vehicle:id,plate_number', 'items', 'loaders.worker:id,name')
            ->orderByDesc('id')->paginate(20)->withQueryString()->through(function ($record) use ($balances) {
                $paid = (int) $record->paid_idr;
                $record->setAttribute('paid_idr', $paid);
                $record->setAttribute('payment_status', $record->status === 'posted' ? $balances->status($record->total_purchase_idr, $paid) : null);
                $record->setAttribute('remaining_idr', $record->status === 'posted' ? $record->total_purchase_idr - $paid : 0);

                return $record;
            });

        return Inertia::render('Weighings/Index', ['weighings' => $rows, 'filters' => $filters,
            'summary' => ['ticket_count' => (int) $summary->ticket_count, 'weight_grams' => (int) $summary->weight_grams,
                'purchase_idr' => (int) $summary->purchase_idr, 'loading_wage_idr' => (int) $summary->loading_wage_idr],
            'savedWeighing' => $request->session()->get('saved_weighing')]);
    }

    public function create(Request $request)
    {
        Gate::authorize('create', Weighing::class);
        $params = $request->validate(['vehicle_id' => ['nullable', 'integer', 'min:1'], 'customer_id' => ['nullable', 'integer', 'min:1'], 'replaces_weighing_id' => ['nullable', 'integer', 'min:1']]);
        $replacement = isset($params['replaces_weighing_id']) ? Weighing::with('customer', 'vehicle')->findOrFail($params['replaces_weighing_id']) : null;
        if ($replacement) {
            Gate::authorize('void', $replacement);
            abort_unless($replacement->status === 'voided', 422);
        }

        $assignment = app(DriverVehicleAssignment::class)->forForm($request->user());
        $vehicle = $assignment !== null ? null : (isset($params['vehicle_id']) ? Vehicle::whereKey($params['vehicle_id'])->where('is_active', true)->firstOrFail() : $replacement?->vehicle);
        $customer = isset($params['customer_id']) ? Customer::whereKey($params['customer_id'])->where('is_active', true)->firstOrFail() : null;

        return Inertia::render('Weighings/Form', [
            'selectedCustomer' => $customer ? ['id' => $customer->id, 'label' => $customer->name] : null,
            'selectedVehicle' => $assignment !== null ? $assignment['vehicle'] : ($vehicle?->is_active ? ['id' => $vehicle->id, 'label' => $vehicle->plate_number] : null),
            'vehicleAssignment' => $assignment,
            'availableWorkers' => $request->user()->role === UserRole::Driver
                ? LoadingWorker::query()->where('is_active', true)->orderBy('name')->orderBy('id')->get(['id', 'name'])
                    ->map(fn (LoadingWorker $worker) => ['id' => $worker->id, 'label' => $worker->name])->all()
                : [],
            'replacement' => $replacement ? ['id' => $replacement->id, 'number' => $replacement->number, 'customer' => ['id' => $replacement->customer_id, 'label' => $replacement->customer->name]] : null]);
    }

    public function preview(Request $request)
    {
        Gate::authorize('create', Weighing::class);
        $data = $request->validate(['tbs_kg' => ['required', 'string', 'max:15'], 'sort_kg' => ['required', 'string', 'max:15'],
            'worker_ids' => ['present', 'array', 'max:100'], 'worker_ids.*' => ['integer', 'min:1', 'distinct']]);
        $input = app(DirectWeighingInput::class);
        $weights = $input->weights($data);
        $workers = $input->workers($data);
        $input->checkWorkers($workers);
        $time = app(BusinessClock::class)->now();
        try {
            $price = app(PriceSnapshotResolver::class)->resolve($time);
        } catch (ValidationException) {
            throw ValidationException::withMessages(['price_schedule_id' => 'Harga hari ini belum tersedia. Minta manajer atau kasir menerbitkan harga di Harga & Upah Muat.']);
        }
        $preview = $weights ? app(WeighingCalculator::class)->calculate($weights, $workers, $price)
            + ['weighed_at' => $time->toIso8601String(), 'effective_from' => $price->effective_from->toIso8601String()] : null;

        return response()->json(['price' => $price->only(['id', 'effective_from', 'tbs_buy_price_idr', 'sort_buy_price_idr', 'loading_rate_idr']),
            'preview' => $preview]);
    }

    public function store(StoreDirectWeighingRequest $request, SaveDirectWeighing $action)
    {
        $record = $action->handle($request->user(), $request->validated());

        return to_route('weighings.index')->with('success', 'Timbangan tersimpan. Harga dan pembagian upah sudah terkunci.')
            ->with('saved_weighing', $record->only(['id', 'number', 'total_purchase_idr']));
    }

    public function edit(Weighing $weighing)
    {
        Gate::authorize('update', $weighing);
        abort_unless($weighing->status === 'draft' && $weighing->workflow === TransactionWorkflow::Legacy, 403);
        $weighing->load('customer:id,name', 'trip.vehicle', 'items', 'loaders.worker:id,name');
        $weighing->trip->setHidden($this->tripHiddenFields());

        return Inertia::render('Weighings/LegacyForm', ['record' => $weighing,
            'localNow' => app(BusinessClock::class)->local($weighing->weighed_at),
            'selectedTrip' => ['id' => $weighing->trip_id, 'label' => $weighing->trip->number.' · '.$weighing->trip->vehicle->plate_number],
            'replacement' => $weighing->replaces_weighing_id ? ['id' => $weighing->replaces_weighing_id, 'number' => Weighing::findOrFail($weighing->replaces_weighing_id)->number, 'customer' => ['id' => $weighing->customer_id, 'label' => $weighing->customer->name]] : null]);
    }

    public function update(SaveWeighingRequest $request, Weighing $weighing, CreateDraftWeighing $action)
    {
        return to_route('weighings.show', $action->handle($request->user(), $request->validated(), $weighing))->with('success', 'Draft diperbarui.');
    }

    public function show(Request $request, Weighing $weighing)
    {
        Gate::authorize('view', $weighing);
        $weighing->load('customer:id,name', 'vehicle:id,plate_number', 'trip.vehicle', 'items.lot.movements', 'loaders.worker:id,name', 'priceSchedule');
        $weighing->trip?->setHidden($this->tripHiddenFields());
        if ($request->user()->role !== UserRole::Manager) {
            foreach ($weighing->items as $item) {
                $item->lot?->setHidden(['initial_purchase_cost_idr', 'initial_loading_cost_idr']);
                foreach ($item->lot?->movements ?? [] as $movement) {
                    $movement->setHidden(['purchase_cost_delta_idr', 'loading_cost_delta_idr']);
                }
            }
        }
        $preview = null;
        $previewError = null;
        if ($weighing->status === 'draft' && $weighing->workflow === TransactionWorkflow::Legacy) {
            try {
                $preview = app(WeighingPreview::class)->calculate($request->user(), $weighing);
            } catch (ValidationException $error) {
                $previewError = $error->errors()['operation'][0];
            }
        }

        $paid = app(PaymentBalances::class)->customerPaid($weighing->id);
        $weighing->setAttribute('paid_idr', $paid);
        $weighing->setAttribute('remaining_idr', $weighing->status === 'posted' ? $weighing->total_purchase_idr - $paid : 0);
        $weighing->setAttribute('payment_status', $weighing->status === 'posted' ? app(PaymentBalances::class)->status($weighing->total_purchase_idr, $paid) : null);

        return Inertia::render('Weighings/Show', ['record' => $weighing, 'preview' => $preview, 'previewError' => $previewError,
            'canEdit' => Gate::allows('update', $weighing), 'canVoid' => Gate::allows('void', $weighing)]);
    }

    public function post(Request $request, Weighing $weighing, PostWeighing $action)
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:1'], 'price_schedule_id' => ['required', 'integer', 'min:1'], 'idempotency_key' => ['required', 'uuid']]);
        $action->handle($request->user(), $weighing, $data);

        return to_route('weighings.show', $weighing)->with('success', 'Tiket sah. Harga, hak konsumen, hak anggota, dan stok sortir sudah terkunci.');
    }

    public function void(Request $request, Weighing $weighing, VoidWeighing $action)
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'max:1000'], 'idempotency_key' => ['required', 'uuid']]);
        $action->handle($request->user(), $weighing, $data);

        return to_route('weighings.show', $weighing)->with('success', $weighing->workflow === TransactionWorkflow::Direct
            ? 'Timbangan dibatalkan. Riwayat harga, berat, dan pembagian upah tetap tersimpan.'
            : 'Tiket dibatalkan. Stok sortir lama dibalik dan riwayat tetap tersimpan.');
    }

    public function destroy(Request $request, Weighing $weighing, DeleteDraftWeighing $action)
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:1']]);
        $action->handle($request->user(), $weighing, (int) $data['version']);

        return to_route('weighings.index')->with('success', 'Draft dihapus.');
    }

    private function tripHiddenFields(): array
    {
        return request()->user()->role === UserRole::Manager ? [] : ['tbs_purchase_cost_snapshot_idr', 'tbs_loading_cost_snapshot_idr'];
    }
}

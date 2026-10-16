<?php

namespace App\Http\Controllers;

use App\Actions\Sales\ApproveVariance;
use App\Actions\Sales\PostSale;
use App\Actions\Sales\ReverseSale;
use App\Actions\Sales\SaveDirectSale;
use App\Actions\Sales\SaveSale;
use App\Domain\Inventory\Stock;
use App\Domain\Sales\DirectSaleInput;
use App\Domain\Sales\FactoryCalculation;
use App\Domain\Shared\Weight;
use App\Enums\TransactionWorkflow;
use App\Enums\UserRole;
use App\Http\Requests\Sales\SaveSaleRequest;
use App\Http\Requests\Sales\StoreDirectSaleRequest;
use App\Models\FactoryPriceSchedule;
use App\Models\FactorySale;
use App\Models\Sale;
use App\Models\SaleReceipt;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Services\AuditService;
use App\Services\DriverVehicleAssignment;
use App\Support\BusinessClock;
use App\Support\Database\WriteTransaction;
use App\Support\OperationalPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SaleController extends Controller
{
    private function record(Request $request): Sale
    {
        return SaveSale::model($request->route('kind'))::findOrFail($request->route('sale'));
    }

    private function routeName(Request $request, string $action): string
    {
        return $request->route('kind').'-sales.'.$action;
    }

    public function index(Request $request)
    {
        $kind = $request->route('kind');
        Gate::authorize('viewAny', SaveSale::model($kind));
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:draft,posted,reversed'], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])]]);
        $query = SaveSale::model($kind)::query()->with('vehicle:id,plate_number', 'trip.vehicle:id,plate_number', 'trip.driver:id,name');
        if ($kind === 'factory') {
            $query->with('factory:id,name');
        }
        if ($request->user()->role === UserRole::Driver) {
            $query->where(fn ($q) => $q->where(fn ($direct) => $direct->where('workflow', 'direct')->where('created_by', $request->user()->id))
                ->orWhere(fn ($legacy) => $legacy->where('workflow', 'legacy')->whereHas('trip', fn ($trip) => $trip->where('driver_id', $request->user()->id))));
        } elseif ($request->user()->role === UserRole::Cashier) {
            $query->where('status', '!=', 'draft');
        }
        if (! empty($filters['q'])) {
            $query->where(fn ($q) => $q->where('number', 'like', '%'.$filters['q'].'%')->orWhere($kind === 'factory' ? 'invoice_number' : 'buyer_name', 'like', '%'.$filters['q'].'%'));
        }
        foreach (['status' => '=', 'from' => '>=', 'to' => '<='] as $field => $operator) {
            if (! empty($filters[$field])) {
                $query->where($field === 'status' ? 'status' : 'business_date', $operator, $filters[$field]);
            }
        }

        return Inertia::render('Sales/Index', ['kind' => $kind, 'filters' => $filters, 'savedSale' => $request->session()->get('saved_sale'),
            'sales' => $query->orderByDesc('id')->paginate(20)->withQueryString()->through(fn ($sale) => app(OperationalPresenter::class)->sale($request->user(), $sale))]);
    }

    public function create(Request $request)
    {
        $kind = $request->route('kind');
        Gate::authorize('create', SaveSale::model($kind));
        $data = $request->validate(['vehicle_id' => ['nullable', 'integer', 'min:1'], 'replaces_sale_id' => ['nullable', 'integer', 'min:1']]);
        $previous = isset($data['replaces_sale_id']) ? SaveSale::model($kind)::findOrFail($data['replaces_sale_id']) : null;
        if ($previous) {
            Gate::authorize('reverse', $previous);
            abort_unless($previous->status === 'reversed', 422);
        }
        $assignment = app(DriverVehicleAssignment::class)->forForm($request->user());
        $vehicle = $assignment !== null ? null : (isset($data['vehicle_id']) ? Vehicle::findOrFail($data['vehicle_id']) : $previous?->vehicle);
        if ($vehicle) {
            abort_unless($vehicle->is_active, 422);
        }
        $factory = $previous instanceof FactorySale ? $previous->factory : null;

        return Inertia::render('Sales/Form', ['kind' => $kind,
            'selectedVehicle' => $assignment !== null ? $assignment['vehicle'] : ($vehicle ? ['id' => $vehicle->id, 'label' => $vehicle->plate_number] : null),
            'vehicleAssignment' => $assignment,
            'selectedFactory' => $factory ? ['id' => $factory->id, 'label' => $factory->name] : null,
            'replacement' => $previous ? app(OperationalPresenter::class)->sale($request->user(), $previous) : null]);
    }

    private function form(Request $request, ?Sale $sale, ?Trip $trip, ?Sale $previous = null)
    {
        $factory = $sale instanceof FactorySale ? $sale->factory : ($previous instanceof FactorySale ? $previous->factory : $trip?->plannedFactory);

        return Inertia::render('Sales/LegacyForm', ['kind' => $request->route('kind'), 'record' => $sale ? app(OperationalPresenter::class)->sale($request->user(), $sale) : null,
            'localNow' => app(BusinessClock::class)->local($sale?->sold_at ?? app(BusinessClock::class)->now()),
            'selectedTrip' => $trip ? ['id' => $trip->id, 'label' => $trip->number.' · '.$trip->vehicle->plate_number] : null,
            'selectedVehicle' => $trip ? ['id' => $trip->vehicle_id, 'label' => $trip->vehicle->plate_number] : null,
            'selectedFactory' => $factory ? ['id' => $factory->id, 'label' => $factory->name] : null,
            'replacement' => $previous ? app(OperationalPresenter::class)->sale($request->user(), $previous) : null]);
    }

    public function edit(Request $request)
    {
        $sale = $this->record($request);
        Gate::authorize('update', $sale);
        abort_unless($sale->status === 'draft' && $sale->workflow === TransactionWorkflow::Legacy, 403);
        $sale->load('trip.vehicle');

        return $this->form($request, $sale, $sale->trip, $sale->replaces_sale_id ? get_class($sale)::findOrFail($sale->replaces_sale_id) : null);
    }

    public function store(StoreDirectSaleRequest $request, SaveDirectSale $action)
    {
        $sale = $action->handle($request->user(), $request->route('kind'), $request->validated());

        return to_route($this->routeName($request, 'index'))->with('success', 'Penjualan tersimpan. Catat uang yang diterima melalui Penerimaan Penjualan.')
            ->with('saved_sale', ['id' => $sale->id, 'number' => $sale->number, 'revenue_idr' => $sale->revenue_idr]);
    }

    public function update(SaveSaleRequest $request, SaveSale $action)
    {
        $sale = $action->handle($request->user(), $request->route('kind'), $request->validated(), $this->record($request));

        return to_route($this->routeName($request, 'show'), ['sale' => $sale->id])->with('success', 'Draft diperbarui. Persetujuan selisih sebelumnya perlu diperiksa kembali.');
    }

    public function show(Request $request)
    {
        $sale = $this->record($request);
        Gate::authorize('view', $sale);
        $sale->load('vehicle:id,plate_number', 'trip.vehicle', 'trip.driver');
        $sale instanceof FactorySale ? $sale->load('factory:id,name', 'factoryReturn') : $sale->load('items');
        $preview = null;
        $error = null;
        if ($sale->status === 'draft' && $sale->workflow === TransactionWorkflow::Legacy) {
            try {
                $preview = $sale instanceof FactorySale ? app(FactoryCalculation::class)->calculate($sale, $sale->trip) : app(Stock::class)->preview($sale);
                $preview = app(OperationalPresenter::class)->costs($request->user(), $preview);
            } catch (ValidationException $exception) {
                $error = array_values($exception->errors())[0][0];
            }
        }

        $receipts = SaleReceipt::where($sale->kind().'_sale_id', $sale->id)->with('cashAccount:id,name')->orderBy('id')->get();

        return Inertia::render('Sales/Show', ['kind' => $sale->kind(), 'record' => app(OperationalPresenter::class)->sale($request->user(), $sale),
            'canReceive' => Gate::allows('create', SaleReceipt::class),
            'cashReceipts' => $receipts->map(fn ($receipt) => ['id' => $receipt->id, 'number' => $receipt->number,
                'amount_idr' => $receipt->amount_idr, 'status' => $receipt->status, 'account_name' => $receipt->cashAccount->name,
                'method' => $receipt->method, 'reference' => $receipt->reference, 'occurred_at' => $receipt->occurred_at]),
            'preview' => $preview, 'previewError' => $error, 'canEdit' => Gate::allows('update', $sale), 'canReverse' => Gate::allows('reverse', $sale),
            'attachments' => DB::table('attachments')->where('entity_type', $sale->kind())->where('entity_id', $sale->id)->get(['id', 'original_name', 'size_bytes'])]);
    }

    public function post(Request $request, PostSale $action)
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:1'], 'preview_hash' => ['nullable', 'string', 'size:64'], 'idempotency_key' => ['required', 'uuid'],
            'cash_account_id' => ['prohibited'], 'payment_reference' => ['prohibited']]);
        $action->handle($request->user(), $this->record($request), $data);

        return to_route($this->routeName($request, 'show'), ['sale' => $request->route('sale')])->with('success', 'Penjualan sah. Catat uang yang diterima melalui Penerimaan Penjualan.');
    }

    public function reverse(Request $request, ReverseSale $action)
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'max:1000'], 'idempotency_key' => ['required', 'uuid']]);
        $action->handle($request->user(), $this->record($request), $data);

        return to_route($this->routeName($request, 'show'), ['sale' => $request->route('sale')])->with('success', 'Penjualan dan uang masuk dibalik. Riwayat tetap tersimpan.');
    }

    public function approve(Request $request, ApproveVariance $action)
    {
        abort_unless($request->route('kind') === 'factory', 404);
        $data = $request->validate(['version' => ['required', 'integer', 'min:1'], 'reason' => ['nullable', 'string', 'max:1000'], 'idempotency_key' => ['required', 'uuid']]);
        $action->handle($request->user(), $this->record($request), $data);

        return back()->with('success', 'Selisih pada draft ini telah disetujui.');
    }

    public function destroy(Request $request)
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:1']]);
        $files = app(WriteTransaction::class)->run(function () use ($request, $data) {
            $actor = $request->user()->fresh();
            $sale = $this->record($request);
            Gate::forUser($actor)->authorize('update', $sale);
            if ($sale->status !== 'draft' || $sale->version !== (int) $data['version']) {
                throw ValidationException::withMessages(['operation' => 'Draft berubah atau sudah disahkan.']);
            }
            $proofs = DB::table('attachments')->where('entity_type', $sale->kind())->where('entity_id', $sale->id)->get();
            app(AuditService::class)->record($actor, 'delete-draft', $sale, app(AuditService::class)->snapshot($sale) + ['proofs' => $proofs->toArray()], null);
            DB::table('attachments')->where('entity_type', $sale->kind())->where('entity_id', $sale->id)->delete();
            $sale->delete();

            return $proofs->pluck('storage_path')->all();
        });
        // External cleanup runs once after a successful commit, outside the retry callback.
        if ($files !== []) {
            Storage::disk('local')->delete($files);
        }

        return to_route($this->routeName($request, 'index'))->with('success', 'Draft dihapus.');
    }

    public function factoryPrice(Request $request)
    {
        Gate::authorize('create', FactorySale::class);
        $data = $request->validate(['factory_id' => ['required', 'integer', 'min:1'], 'sold_at' => ['nullable', 'string', 'max:19']]);
        $clock = app(BusinessClock::class);
        $time = $request->user()->role === UserRole::Manager && ! empty($data['sold_at']) ? $clock->parse($data['sold_at'], 'sold_at') : $clock->now();
        $price = FactoryPriceSchedule::where('factory_id', $data['factory_id'])->where('effective_from', '<=', $time)->orderByDesc('effective_from')->first();

        return response()->json(['id' => $price?->id, 'price_idr' => $price?->tbs_sell_price_idr, 'effective_from' => $price?->effective_from]);
    }

    public function preview(Request $request)
    {
        $kind = $request->route('kind');
        Gate::authorize('create', SaveSale::model($kind));
        $data = $request->validate($kind === 'factory'
            ? ['factory_id' => ['required', 'integer', 'min:1'], 'accepted_kg' => ['required', 'string', 'max:15'], 'deduction_idr' => ['required', 'integer', 'between:0,100000000000000']]
            : ['sold_kg' => ['required', 'string', 'max:15'], 'sell_price_snapshot_idr' => ['required', 'integer', 'between:1,100000000']]);
        $input = app(DirectSaleInput::class);
        $price = $kind === 'factory' ? $input->price((int) $data['factory_id'], app(BusinessClock::class)->now()) : null;
        $rate = $price?->tbs_sell_price_idr ?? (int) $data['sell_price_snapshot_idr'];
        $field = $kind === 'factory' ? 'accepted_kg' : 'sold_kg';
        $grams = Weight::grams($data[$field], $field);

        return response()->json(['price' => ['id' => $price?->id, 'price_idr' => $rate], 'preview' => $grams > 0 ? $input->calculate($kind, $data, $rate) : null]);
    }
}

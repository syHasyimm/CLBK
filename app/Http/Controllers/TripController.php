<?php

namespace App\Http\Controllers;

use App\Actions\Trips\ChangeTripState;
use App\Actions\Trips\OpenTrip;
use App\Domain\Inventory\Stock;
use App\Enums\UserRole;
use App\Models\FactorySale;
use App\Models\SortLot;
use App\Models\SortSale;
use App\Models\Trip;
use App\Queries\OperationalScope;
use App\Support\OperationalPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TripController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Trip::class);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:loading,dispatched,factory_recorded,closed,cancelled,correction_pending']]);
        $query = OperationalScope::trips($request->user())->with('vehicle:id,plate_number', 'driver:id,name', 'plannedFactory:id,name');
        if (! empty($filters['q'])) {
            $query->where(fn ($q) => $q->where('number', 'like', '%'.$filters['q'].'%')->orWhereHas('vehicle', fn ($v) => $v->where('plate_number', 'like', '%'.$filters['q'].'%')));
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return Inertia::render('Trips/Index', ['trips' => $query->orderByDesc('id')->paginate(20)->withQueryString()->through(fn ($trip) => app(OperationalPresenter::class)->trip($request->user(), $trip)), 'filters' => $filters]);
    }

    public function store(Request $request, OpenTrip $action)
    {
        Gate::authorize('create', Trip::class);
        $data = $request->validate(['vehicle_id' => ['required', 'integer', 'min:1'],
            'driver_id' => [$request->user()->role === UserRole::Driver ? 'prohibited' : 'required', 'integer', 'min:1'],
            'planned_factory_id' => ['nullable', 'integer', 'min:1']]);
        $trip = $action->handle($request->user(), $data);

        return to_route('trips.show', $trip)->with('success', 'Perjalanan dibuka dan siap menerima tiket timbang.');
    }

    public function show(Request $request, Trip $trip)
    {
        Gate::authorize('view', $trip);
        $trip->load('vehicle:id,plate_number', 'driver:id,name', 'plannedFactory:id,name');

        $sales = [];
        foreach (['factory' => FactorySale::class, 'sort' => SortSale::class] as $kind => $model) {
            $sales[$kind] = $model::where('trip_id', $trip->id)->when($request->user()->role === UserRole::Cashier, fn ($q) => $q->where('status', '!=', 'draft'))->orderByDesc('id')->get(['id', 'number', 'status', 'revenue_idr']);
        }
        $stock = SortLot::where('trip_id', $trip->id)->where('status', 'active')->get()->sum(fn ($lot) => app(Stock::class)->balance($lot)['grams']);

        return Inertia::render('Trips/Show', ['trip' => app(OperationalPresenter::class)->trip($request->user(), $trip), 'sales' => $sales, 'stockGrams' => $stock,
            'weighings' => OperationalScope::weighings($request->user())->where('trip_id', $trip->id)->with('customer:id,name')->orderByDesc('id')->paginate(20)]);
    }

    public function dispatch(Request $request, Trip $trip, ChangeTripState $action)
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:1'], 'idempotency_key' => ['required', 'uuid']]);
        $action->handle($request->user(), $trip, 'dispatch', $data);

        return to_route('trips.show', $trip)->with('success', 'Keberangkatan tercatat. Referensi tiket dan biaya TBS terkunci.');
    }

    public function close(Request $request, Trip $trip, ChangeTripState $action)
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:1'], 'idempotency_key' => ['required', 'uuid']]);
        $action->handle($request->user(), $trip, 'close', $data);

        return to_route('trips.show', $trip)->with('success', 'Perjalanan selesai.');
    }
}

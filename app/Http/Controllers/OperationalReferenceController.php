<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Weighing;
use App\Queries\OperationalScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OperationalReferenceController extends Controller
{
    public function __invoke(Request $request, string $kind)
    {
        Gate::authorize('create', Weighing::class);
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'between:1,1000000'], 'vehicle_id' => ['nullable', 'integer', 'min:1']]);
        $search = $data['q'] ?? '';
        if ($kind === 'drivers') {
            Gate::authorize('manage-settings');
            $query = User::query()->where('role', UserRole::Driver)->where('is_active', true)->where('name', 'like', '%'.$search.'%')->orderBy('name');
            $results = $query->paginate(25, ['id', 'name'])->withQueryString();
        } elseif ($kind === 'vehicles') {
            $results = Vehicle::query()->where('is_active', true)->where(fn ($q) => $q->where('code', 'like', '%'.$search.'%')->orWhere('plate_number', 'like', '%'.$search.'%'))
                ->when($request->user()->role === UserRole::Driver, fn ($q) => $q->where('id', $request->user()->assigned_vehicle_id ?? 0))
                ->orderBy('code')->paginate(25, ['id', 'code', 'plate_number', 'default_driver_id'])->withQueryString();
        } else {
            $statuses = match ($kind) {
                'factory-trips' => ['dispatched', 'correction_pending'], 'sort-trips' => ['loading', 'dispatched', 'factory_recorded', 'correction_pending'], default => ['loading']
            };
            $results = OperationalScope::trips($request->user())->whereIn('status', $statuses)->when(! empty($data['vehicle_id']), fn ($q) => $q->where('vehicle_id', $data['vehicle_id']))->with('vehicle:id,plate_number')
                ->where(fn ($q) => $q->where('number', 'like', '%'.$search.'%')->orWhereHas('vehicle', fn ($v) => $v->where('plate_number', 'like', '%'.$search.'%')))
                ->orderByDesc('id')->paginate(25)->withQueryString();
        }
        $results->through(fn ($row) => ['id' => $row->id, 'label' => $kind === 'drivers' ? $row->name : ($kind === 'vehicles' ? $row->code.' · '.$row->plate_number : $row->number.' · '.$row->vehicle->plate_number)]);

        return response()->json($results);
    }
}

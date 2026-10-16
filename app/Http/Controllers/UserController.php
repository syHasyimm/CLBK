<?php

namespace App\Http\Controllers;

use App\Actions\Accounts\SaveUser;
use App\Enums\UserRole;
use App\Http\Requests\SaveUserRequest;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:all,active,inactive'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ]);
        $q = $filters['q'] ?? '';
        $status = $filters['status'] ?? 'all';
        $query = User::query();
        if ($q !== '') {
            $query->where(fn ($query) => $query->where('name', 'like', '%'.$q.'%')->orWhere('email', 'like', '%'.$q.'%'));
        }
        if ($status !== 'all') {
            $query->where('is_active', $status === 'active');
        }

        return Inertia::render('Users/Index', [
            'records' => $query->with('assignedVehicle:id,plate_number,is_active')->orderBy('name')
                ->paginate(15, ['id', 'name', 'email', 'role', 'is_active', 'assigned_vehicle_id'])->withQueryString()
                ->through(fn ($user) => $user->only(['id', 'name', 'email', 'role', 'is_active', 'assigned_vehicle_id']) + [
                    'assigned_vehicle' => $user->role === UserRole::Driver
                        ? ($user->assignedVehicle ? $user->assignedVehicle->plate_number.($user->assignedVehicle->is_active ? '' : ' (nonaktif)') : 'Belum ditetapkan') : '—',
                ]),
            'filters' => ['q' => $q, 'status' => $status],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', User::class);

        return $this->form();
    }

    public function edit(User $user): Response
    {
        Gate::authorize('update', $user);

        return $this->form($user);
    }

    public function store(SaveUserRequest $request, SaveUser $action): RedirectResponse
    {
        $action->handle($request->user(), null, $request->validated());

        return to_route('users.index')->with('success', 'Akun berhasil ditambahkan.');
    }

    public function update(SaveUserRequest $request, User $user, SaveUser $action): RedirectResponse
    {
        Gate::authorize('update', $user);
        $action->handle($request->user(), $user->id, $request->validated());

        return $this->destination($request)->with('success', 'Akun berhasil diperbarui.');
    }

    public function status(Request $request, User $user, SaveUser $action): RedirectResponse
    {
        Gate::authorize('update', $user);
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $action->handle($request->user(), $user->id, $data);

        return $this->destination($request)->with('success', $data['is_active'] ? 'Akun diaktifkan.' : 'Akun dinonaktifkan.');
    }

    private function destination(Request $request): RedirectResponse
    {
        $actor = $request->user()->fresh();

        return to_route($actor->is_active && $actor->role === UserRole::Manager ? 'users.index' : 'dashboard');
    }

    private function form(?User $user = null): Response
    {
        $vehicles = Vehicle::where('is_active', true)->orderBy('plate_number')->get(['id', 'plate_number'])
            ->map(fn ($vehicle) => ['value' => (string) $vehicle->id, 'label' => $vehicle->plate_number])->all();
        if ($user?->assignedVehicle && ! $user->assignedVehicle->is_active) {
            $vehicles[] = ['value' => (string) $user->assigned_vehicle_id, 'label' => $user->assignedVehicle->plate_number.' (nonaktif)', 'disabled' => true];
        }

        return Inertia::render('Users/Form', [
            'record' => $user?->only(['id', 'name', 'email', 'role', 'is_active', 'assigned_vehicle_id']),
            'roles' => array_map(fn ($role) => ['value' => $role->value, 'label' => $role->label()], UserRole::cases()),
            'vehicles' => $vehicles,
        ]);
    }
}

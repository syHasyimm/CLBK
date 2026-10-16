<?php

namespace App\Http\Controllers\MasterData;

use App\Actions\MasterData\DeleteMasterData;
use App\Actions\MasterData\SaveMasterData;
use App\Enums\MasterResource;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\SaveMasterDataRequest;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MasterDataController extends Controller
{
    public function index(Request $request, string $resource): Response|RedirectResponse
    {
        $definition = MasterResource::from($resource);
        Gate::authorize('viewAny', $definition->model());
        if ($definition === MasterResource::CashAccounts) {
            return to_route('cash.index');
        }
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:all,active,inactive'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ]);
        $q = $filters['q'] ?? '';
        $status = $filters['status'] ?? 'all';
        $query = $definition->model()::query();
        if ($q !== '') {
            $query->where(function ($query) use ($definition, $q) {
                foreach ($definition->searchable() as $column) {
                    $query->orWhere($column, 'like', '%'.$q.'%');
                }
            });
        }
        if ($status !== 'all') {
            $query->where('is_active', $status === 'active');
        }
        if ($definition === MasterResource::Vehicles) {
            $query->with('defaultDriver:id,name');
        }
        $records = $query->orderBy('code')->paginate(15)->withQueryString();
        $records->through(fn ($record) => $this->serialize($record));

        return Inertia::render('MasterData/Index', [
            'resource' => $resource, 'title' => $definition->title(),
            'description' => $definition->description(),
            'columns' => $definition->columns(), 'records' => $records,
            'filters' => ['q' => $q, 'status' => $status],
        ]);
    }

    public function create(string $resource): Response
    {
        $definition = MasterResource::from($resource);
        Gate::authorize('viewAny', $definition->model());
        Gate::authorize('create', $definition->model());

        return $this->form($definition);
    }

    public function edit(string $resource, int $record): Response
    {
        $definition = MasterResource::from($resource);
        $model = $definition->model()::query()->findOrFail($record);
        Gate::authorize('update', $model);

        return $this->form($definition, $model);
    }

    public function store(SaveMasterDataRequest $request, string $resource, SaveMasterData $action): RedirectResponse
    {
        $action->handle($request->user(), MasterResource::from($resource), null, $request->payload());

        return to_route('master-data.index', $resource)->with('success', 'Data berhasil ditambahkan.');
    }

    public function update(SaveMasterDataRequest $request, string $resource, int $record, SaveMasterData $action): RedirectResponse
    {
        $definition = MasterResource::from($resource);
        Gate::authorize('update', $definition->model()::query()->findOrFail($record));
        $action->handle($request->user(), $definition, $record, $request->payload());

        return to_route('master-data.index', $resource)->with('success', 'Perubahan berhasil disimpan.');
    }

    public function status(Request $request, string $resource, int $record, SaveMasterData $action): RedirectResponse
    {
        $definition = MasterResource::from($resource);
        Gate::authorize('update', $definition->model()::query()->findOrFail($record));
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $action->handle($request->user(), $definition, $record, $data);

        return back()->with('success', $data['is_active'] ? 'Data diaktifkan kembali.' : 'Data dinonaktifkan. Riwayatnya tetap tersimpan.');
    }

    public function destroy(Request $request, string $resource, int $record, DeleteMasterData $action): RedirectResponse
    {
        $definition = MasterResource::from($resource);
        Gate::authorize('delete', $definition->model()::query()->findOrFail($record));
        $action->handle($request->user(), $definition, $record);

        return back()->with('success', 'Data yang belum digunakan berhasil dihapus.');
    }

    private function form(MasterResource $definition, ?Model $record = null): Response
    {
        $options = [];
        if ($definition === MasterResource::Vehicles) {
            $options['default_driver_id'] = User::query()
                ->where('role', UserRole::Driver)->where('is_active', true)
                ->orderBy('name')->get(['id', 'name'])
                ->map(fn ($driver) => ['value' => (string) $driver->id, 'label' => $driver->name])->all();
            if ($record?->default_driver_id && ! collect($options['default_driver_id'])->contains('value', (string) $record->default_driver_id)) {
                $driver = User::query()->find($record->default_driver_id);
                $options['default_driver_id'][] = ['value' => (string) $record->default_driver_id, 'label' => ($driver?->name ?? 'Supir').' (tidak tersedia)', 'disabled' => true];
            }
        }

        return Inertia::render('MasterData/Form', [
            'resource' => $definition->value, 'title' => $definition->title(),
            'fields' => $definition->fields(), 'options' => $options,
            'record' => $record ? $this->serialize($record) : null,
        ]);
    }

    private function serialize(Model $record): array
    {
        $data = $record->toArray();
        if ($record instanceof Vehicle) {
            $data['capacity_kg'] = $record->capacity_grams === null ? null
                : intdiv($record->capacity_grams, 1000).'.'.str_pad((string) ($record->capacity_grams % 1000), 3, '0', STR_PAD_LEFT);
            $data['driver_name'] = $record->defaultDriver?->name;
            unset($data['default_driver']);
        }
        if (isset($data['type'])) {
            $data['type_label'] = $data['type'] === 'cash' ? 'Kas tunai' : 'Bank';
        }

        return $data;
    }
}

<?php

namespace App\Http\Controllers\MasterData;

use App\Enums\MasterResource;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Services\BusinessCash;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferenceDataController extends Controller
{
    public function __invoke(Request $request, string $resource): JsonResponse
    {
        $definition = MasterResource::from($resource);
        if (in_array($definition, [MasterResource::CashAccounts, MasterResource::ExpenseCategories], true)) {
            abort_unless(in_array($request->user()->role, [UserRole::Manager, UserRole::Cashier], true), 403);
        }
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ]);
        $label = $definition === MasterResource::Vehicles ? 'plate_number' : 'name';
        $query = $definition->model()::query()->where('is_active', true);
        if ($definition === MasterResource::CashAccounts) {
            $query->whereKey(app(BusinessCash::class)->account()?->id ?? 0);
        }
        if (! empty($filters['q'])) {
            $query->where(fn ($query) => $query->where('code', 'like', '%'.$filters['q'].'%')->orWhere($label, 'like', '%'.$filters['q'].'%'));
        }

        // Hanya identitas referensi; telepon/alamat/catatan tidak dikirim ke pemilih.
        return response()->json($query->orderBy('code')->paginate(25, ['id', 'code', $label])->withQueryString());
    }
}

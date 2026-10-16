<?php

namespace App\Http\Controllers;

use App\Actions\Inventory\AdjustStock;
use App\Domain\Inventory\Stock;
use App\Models\FactoryReturn;
use App\Models\FactorySale;
use App\Models\SortLot;
use App\Models\SortSale;
use App\Models\SortStockMovement;
use App\Models\WeighingItem;
use App\Support\OperationalPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class InventoryController extends Controller
{
    private function present(Request $request, SortLot $lot): array
    {
        $presenter = app(OperationalPresenter::class);
        $data = $presenter->costs($request->user(), $lot->toArray());
        $data['trip'] = $presenter->trip($request->user(), $lot->trip);
        $data['balance'] = $presenter->costs($request->user(), app(Stock::class)->balance($lot));
        $source = $lot->source_type === 'INITIAL_SORT' ? WeighingItem::findOrFail($lot->source_weighing_item_id)->weighing : FactorySale::findOrFail(FactoryReturn::findOrFail($lot->source_factory_return_id)->factory_sale_id);
        $data['source'] = Gate::allows('view', $source) ? ['id' => $source->id, 'number' => $source->number, 'kind' => $lot->source_type === 'INITIAL_SORT' ? 'weighing' : 'factory'] : null;

        return $data;
    }

    public function show(Request $request, SortLot $lot)
    {
        Gate::authorize('view', $lot->trip);
        $lot->load('trip.vehicle');
        $movements = $lot->movements()->orderByDesc('id')->paginate(20)->withQueryString()->through(function ($movement) use ($request) {
            $itemId = $movement->sort_sale_item_id ?? ($movement->reversal_of_id ? SortStockMovement::findOrFail($movement->reversal_of_id)->sort_sale_item_id : null);
            $sale = $itemId ? SortSale::whereHas('items', fn ($q) => $q->whereKey($itemId))->first() : null;
            $document = $sale && Gate::allows('view', $sale) ? ['id' => $sale->id, 'number' => $sale->number] : null;

            return app(OperationalPresenter::class)->costs($request->user(), $movement->toArray() + ['is_reversed' => SortStockMovement::where('reversal_of_id', $movement->id)->exists(), 'sale' => $document]);
        });

        return Inertia::render('Inventory/Show', ['lot' => $this->present($request, $lot), 'movements' => $movements]);
    }

    public function adjust(Request $request, SortLot $lot, AdjustStock $action)
    {
        $data = $request->validate(['loss_kg' => ['required', 'string', 'max:15'], 'reason' => ['required', 'string', 'max:1000'], 'idempotency_key' => ['required', 'uuid']]);
        $action->handle($request->user(), $lot, $data);

        return back()->with('success', 'Kehilangan stok dan biayanya tercatat.');
    }

    public function reverse(Request $request, SortLot $lot, SortStockMovement $movement, AdjustStock $action)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000'], 'idempotency_key' => ['required', 'uuid']]);
        $action->handle($request->user(), $lot, $data, $movement);

        return back()->with('success', 'Kehilangan dibalik. Stok dan biaya dikembalikan.');
    }
}

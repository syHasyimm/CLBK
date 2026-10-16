<?php

namespace App\Http\Controllers;

use App\Actions\Prices\PublishPrice;
use App\Models\FactoryPriceSchedule;
use App\Models\PriceSchedule;
use App\Support\BusinessClock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class PriceController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('view-prices');
        $clock = app(BusinessClock::class);
        $now = $clock->now();

        return Inertia::render('Prices/Index', ['prices' => PriceSchedule::query()->orderByDesc('effective_from')->paginate(15)->withQueryString(),
            'factoryPrices' => FactoryPriceSchedule::with('factory:id,name')->orderByDesc('effective_from')->paginate(15, ['*'], 'factory_page')->withQueryString(),
            'currentPrice' => PriceSchedule::query()->where('business_date', $clock->date($now))
                ->where('effective_from', '<=', $now)->orderByDesc('effective_from')->first(),
            'serverNow' => $now->toIso8601String(),
            'localNow' => $clock->local($now),
            'can' => ['publishPrice' => Gate::allows('manage-prices'), 'publishFactoryPrice' => Gate::allows('manage-prices')]]);
    }

    public function store(Request $request, PublishPrice $action)
    {
        Gate::authorize('manage-prices');
        $data = $request->validate(['effective_from' => ['required', 'string', 'max:19'],
            'tbs_buy_price_idr' => ['required', 'integer', 'between:1,100000000'],
            'sort_buy_price_idr' => ['required', 'integer', 'between:1,100000000'],
            'loading_rate_idr' => ['required', 'integer', 'between:0,100000000'], 'notes' => ['nullable', 'string', 'max:1000']]);
        $action->handle($request->user(), $data);

        return to_route('prices.index')->with('success', 'Versi harga beli dan upah muat diterbitkan.');
    }

    public function factoryStore(Request $request, PublishPrice $action)
    {
        Gate::authorize('manage-prices');
        $data = $request->validate(['factory_id' => ['required', 'integer', 'min:1'],
            'effective_from' => ['required', 'string', 'max:19'], 'tbs_sell_price_idr' => ['required', 'integer', 'between:1,100000000']]);
        $action->handle($request->user(), $data, true);

        return to_route('prices.index')->with('success', 'Versi harga referensi pabrik diterbitkan.');
    }
}

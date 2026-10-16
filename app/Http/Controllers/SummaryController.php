<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Queries\PeriodSummary;
use App\Support\BusinessClock;
use App\Support\Database\ReadSnapshot;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SummaryController extends Controller
{
    public function __invoke(Request $request, PeriodSummary $summary)
    {
        $data = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
            'vehicle_id' => ['nullable', 'integer', 'min:1', 'exists:vehicles,id']]);
        $clock = app(BusinessClock::class);
        $today = $clock->date($clock->now());
        $filters = ['from' => $data['from'] ?? $data['to'] ?? $today, 'to' => $data['to'] ?? $data['from'] ?? $today, 'vehicle_id' => $data['vehicle_id'] ?? null];

        return app(ReadSnapshot::class)->run(fn () => Inertia::render('Dashboard/Index', [
            'filters' => $filters, 'summary' => $summary->get($request->user(), $filters),
            'vehicles' => Vehicle::orderBy('plate_number')->get(['id', 'plate_number'])]));
    }
}

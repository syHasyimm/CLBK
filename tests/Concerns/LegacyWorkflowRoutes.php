<?php

namespace Tests\Concerns;

use App\Actions\Sales\SaveSale;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\OperationalReferenceController;
use App\Http\Controllers\TripController;
use App\Http\Requests\Sales\SaveSaleRequest;
use Illuminate\Support\Facades\Route;

trait LegacyWorkflowRoutes
{
    protected function registerLegacyWorkflowTestRoutes(): void
    {
        // Test-only harness for preserved historical business rules. These URLs never exist in the application.
        config(['inertia.testing.ensure_pages_exist' => false]);
        // Archived pages have no frontend entry in a current production build.
        $this->withoutVite();
        Route::middleware(['web', 'auth', 'active', 'auth.session'])->prefix('__test_archive')->group(function () {
            Route::post('sale-receipts', [FinanceController::class, 'store'])->defaults('kind', 'receipt')->name('legacy-test.sale-receipts.store');
            Route::resource('trips', TripController::class)->only(['index', 'store', 'show']);
            Route::post('trips/{trip}/dispatch', [TripController::class, 'dispatch'])->name('trips.dispatch');
            Route::post('trips/{trip}/close', [TripController::class, 'close'])->name('trips.close');
            Route::get('inventory/{lot}', [InventoryController::class, 'show'])->name('inventory.show');
            Route::post('inventory/{lot}/loss', [InventoryController::class, 'adjust'])->name('inventory.adjust');
            Route::post('inventory/{lot}/loss/{movement}/reverse', [InventoryController::class, 'reverse'])->name('inventory.reverse');
            Route::get('references/{kind}', OperationalReferenceController::class)->name('legacy-test.references');
            foreach (['factory', 'sort'] as $kind) {
                Route::post($kind.'-sales', function (SaveSaleRequest $request, SaveSale $action) {
                    $sale = $action->handle($request->user(), $request->route('kind'), $request->validated());

                    return to_route($sale->kind().'-sales.show', ['sale' => $sale->id]);
                })->defaults('kind', $kind)->name('legacy-test.'.$kind.'-sales.store');
            }
        });
        Route::getRoutes()->refreshNameLookups();
    }
}

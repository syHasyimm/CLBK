<?php

use App\Enums\MasterResource;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BusinessSettingsController;
use App\Http\Controllers\CashController;
use App\Http\Controllers\DriverCustomerController;
use App\Http\Controllers\FinanceAttachmentController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\FonnteWebhookController;
use App\Http\Controllers\MasterData\MasterDataController;
use App\Http\Controllers\MasterData\ReferenceDataController;
use App\Http\Controllers\OperationalReferenceController;
use App\Http\Controllers\PriceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleAttachmentController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SummaryController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WeighingController;
use App\Http\Controllers\WhatsAppConnectionController;
use App\Http\Controllers\WhatsAppController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

Route::post('/integrations/fonnte/webhook/{secret}', FonnteWebhookController::class)
    ->where('secret', '[A-Za-z0-9_-]{32,128}')
    ->withoutMiddleware([PreventRequestForgery::class])
    ->middleware('throttle:120,1')->name('integrations.fonnte.webhook');

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::get('/dashboard', SummaryController::class)->middleware(['auth', 'active', 'auth.session'])->name('dashboard');

Route::middleware(['auth', 'active', 'auth.session'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('/reports/print', [ReportController::class, 'print'])->name('reports.print');
    Route::patch('/profile', [ProfileController::class, 'update'])
        ->middleware('throttle:password-recheck')->name('profile.update');
    Route::get('/customers/create', [DriverCustomerController::class, 'create'])->name('customers.create');
    Route::post('/customers', [DriverCustomerController::class, 'store'])->name('customers.store');

    Route::prefix('master/{resource}')
        ->whereIn('resource', array_column(MasterResource::cases(), 'value'))
        ->name('master-data.')->group(function () {
            Route::get('/', [MasterDataController::class, 'index'])->name('index');
            Route::get('/create', [MasterDataController::class, 'create'])->name('create');
            Route::post('/', [MasterDataController::class, 'store'])->name('store');
            Route::get('/{record}/edit', [MasterDataController::class, 'edit'])->whereNumber('record')->name('edit');
            Route::patch('/{record}', [MasterDataController::class, 'update'])->whereNumber('record')->name('update');
            Route::patch('/{record}/status', [MasterDataController::class, 'status'])->whereNumber('record')->name('status');
            Route::delete('/{record}', [MasterDataController::class, 'destroy'])->whereNumber('record')->name('destroy');
        });
    Route::get('/references/{resource}', ReferenceDataController::class)
        ->whereIn('resource', array_column(MasterResource::cases(), 'value'))->name('references.index');
    Route::resource('users', UserController::class)->only(['index', 'create', 'store', 'edit', 'update']);
    Route::patch('/users/{user}/status', [UserController::class, 'status'])->name('users.status');
    Route::get('/settings/business', [BusinessSettingsController::class, 'edit'])->name('settings.business.edit');
    Route::get('/settings/whatsapp', [WhatsAppController::class, 'index'])->name('whatsapp.index');
    Route::redirect('/whatsapp', '/settings/whatsapp');
    Route::put('/settings/whatsapp/connection', [WhatsAppConnectionController::class, 'update'])->middleware('throttle:password-recheck')->name('whatsapp.connection.update');
    Route::post('/settings/whatsapp/connection/check', [WhatsAppConnectionController::class, 'check'])->middleware('throttle:whatsapp-connection')->name('whatsapp.connection.check');
    Route::patch('/settings/whatsapp', [WhatsAppController::class, 'update'])->name('whatsapp.settings');
    Route::post('/whatsapp/test', [WhatsAppController::class, 'test'])->middleware('throttle:password-recheck')->name('whatsapp.test');
    Route::get('/whatsapp/{message}', [WhatsAppController::class, 'show'])->whereNumber('message')->name('whatsapp.show');
    Route::post('/whatsapp/{message}/retry', [WhatsAppController::class, 'retry'])->whereNumber('message')->middleware('throttle:password-recheck')->name('whatsapp.retry');
    Route::patch('/settings/business', [BusinessSettingsController::class, 'update'])->name('settings.business.update');
    Route::post('/settings/backups', [BackupController::class, 'store'])->middleware('throttle:password-recheck')->name('backups.store');
    Route::get('/settings/backups/{backup}', [BackupController::class, 'download'])
        ->middleware(['can:manage-settings', 'password.confirm'])->name('backups.download');
    Route::get('/prices', [PriceController::class, 'index'])->name('prices.index');
    Route::post('/prices', [PriceController::class, 'store'])->name('prices.store');
    Route::post('/prices/factory', [PriceController::class, 'factoryStore'])->name('prices.factory-store');
    Route::get('/operational-references/{kind}', OperationalReferenceController::class)
        ->whereIn('kind', ['vehicles'])->name('operational-references.index');
    Route::get('/factory-price-reference', [SaleController::class, 'factoryPrice'])->name('factory-price-reference');
    foreach (['factory', 'sort'] as $kind) {
        Route::prefix($kind.'-sales')->name($kind.'-sales.')->group(function () use ($kind) {
            $controller = SaleController::class;
            Route::get('/', [$controller, 'index'])->defaults('kind', $kind)->name('index');
            Route::get('/create', [$controller, 'create'])->defaults('kind', $kind)->name('create');
            Route::post('/', [$controller, 'store'])->defaults('kind', $kind)->name('store');
            Route::post('/preview', [$controller, 'preview'])->defaults('kind', $kind)->name('preview');
            Route::get('/{sale}', [$controller, 'show'])->whereNumber('sale')->defaults('kind', $kind)->name('show');
            Route::get('/{sale}/edit', [$controller, 'edit'])->whereNumber('sale')->defaults('kind', $kind)->name('edit');
            Route::patch('/{sale}', [$controller, 'update'])->whereNumber('sale')->defaults('kind', $kind)->name('update');
            Route::delete('/{sale}', [$controller, 'destroy'])->whereNumber('sale')->defaults('kind', $kind)->name('destroy');
            foreach (['post', 'reverse', 'approve'] as $action) {
                Route::post('/{sale}/'.$action, [$controller, $action])->whereNumber('sale')->defaults('kind', $kind)->name($action);
            }
            Route::post('/{sale}/proofs', [SaleAttachmentController::class, 'store'])->middleware('throttle:proof-uploads')->whereNumber('sale')->defaults('kind', $kind)->name('proofs');
        });
    }
    Route::get('/sale-proofs/{attachment}', [SaleAttachmentController::class, 'download'])->whereNumber('attachment')->name('sale-proofs.download');
    Route::post('/weighings/preview', [WeighingController::class, 'preview'])->name('weighings.preview');
    Route::resource('weighings', WeighingController::class);
    Route::post('/weighings/{weighing}/post', [WeighingController::class, 'post'])->name('weighings.post');
    Route::post('/weighings/{weighing}/void', [WeighingController::class, 'void'])->name('weighings.void');
    Route::get('/customer-payments', [FinanceController::class, 'worklist'])->defaults('kind', 'customer')->name('customer-payments.index');
    Route::get('/customer-payments/{recipient}', [FinanceController::class, 'payee'])->whereNumber('recipient')->defaults('kind', 'customer')->name('customer-payments.payee');
    Route::post('/customer-payments', [FinanceController::class, 'store'])->defaults('kind', 'customer')->name('customer-payments.store');
    Route::get('/payroll', [FinanceController::class, 'worklist'])->defaults('kind', 'payroll')->name('payroll.index');
    Route::get('/payroll/history', [FinanceController::class, 'payrollHistory'])->defaults('kind', 'payroll')->name('payroll.history');
    Route::get('/payroll/{recipient}', [FinanceController::class, 'payee'])->whereNumber('recipient')->defaults('kind', 'payroll')->name('payroll.payee');
    Route::post('/payroll-payments', [FinanceController::class, 'store'])->defaults('kind', 'payroll')->name('payroll-payments.store');
    Route::get('/sale-receipts', [FinanceController::class, 'index'])->defaults('kind', 'receipt')->name('sale-receipts.index');
    Route::get('/sale-receipts/create', [FinanceController::class, 'create'])->defaults('kind', 'receipt')->name('sale-receipts.create');
    Route::post('/sale-receipts', [FinanceController::class, 'store'])->defaults('kind', 'receipt')->name('sale-receipts.store');
    Route::get('/daily-expenses', [FinanceController::class, 'index'])->defaults('kind', 'expense')->name('daily-expenses.index');
    Route::get('/daily-expenses/create', [FinanceController::class, 'create'])->defaults('kind', 'expense')->name('daily-expenses.create');
    Route::post('/daily-expenses', [FinanceController::class, 'store'])->defaults('kind', 'expense')->name('daily-expenses.store');
    Route::prefix('payments/{kind}')->whereIn('kind', ['customer', 'payroll', 'receipt', 'expense'])->name('finance.')->group(function () {
        Route::get('/', [FinanceController::class, 'index'])->name('index');
        Route::get('/{document}', [FinanceController::class, 'show'])->whereNumber('document')->name('show');
        Route::post('/{document}/reverse', [FinanceController::class, 'reverse'])->whereNumber('document')->name('reverse');
        Route::post('/{document}/proofs', [FinanceAttachmentController::class, 'store'])->middleware('throttle:proof-uploads')->whereNumber('document')->name('proofs');
    });
    Route::get('/finance-proofs/{attachment}', [FinanceAttachmentController::class, 'download'])->whereNumber('attachment')->name('finance-proofs.download');
    Route::get('/cash-ledger', [CashController::class, 'index'])->name('cash.index');
    Route::post('/cash-ledger', [CashController::class, 'store'])->name('cash.store');
    Route::post('/cash-ledger/account', [CashController::class, 'createAccount'])->name('cash.account.store');
    Route::post('/cash-ledger/{entry}/reverse', [CashController::class, 'reverse'])->whereNumber('entry')->name('cash.reverse');
    Route::get('/finance-references/{referenceKind}', [CashController::class, 'references'])->whereIn('referenceKind', ['accounts', 'ledger-accounts'])->name('finance-references.index');
});

require __DIR__.'/auth.php';

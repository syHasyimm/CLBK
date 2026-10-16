<?php

namespace App\Http\Controllers;

use App\Actions\Settings\SaveBusinessSettings;
use App\Models\AppSetting;
use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BusinessSettingsController extends Controller
{
    public function edit(): Response
    {
        Gate::authorize('manage-settings');

        $backups = app(BackupService::class);
        $status = ['files' => [], 'error' => null, 'retention' => $backups->retention(), 'time' => config('backups.time')];
        try {
            $status['files'] = array_slice($backups->listing(), 0, 12);
        } catch (\RuntimeException $error) {
            $status['error'] = $error->getMessage();
        }

        return Inertia::render('Settings/Business', ['settings' => AppSetting::values(), 'backups' => $status]);
    }

    public function update(Request $request, SaveBusinessSettings $action): RedirectResponse
    {
        Gate::authorize('manage-settings');
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:150'],
            'receipt_prefix' => ['required', 'string', 'max:12', 'regex:/^[A-Z0-9]+$/'],
            'business_timezone' => ['prohibited'],
            'weight_tolerance_basis_points' => ['prohibited'],
            'backup_keep_daily' => ['sometimes', 'integer', 'between:1,90'],
            'backup_keep_weekly' => ['sometimes', 'integer', 'between:1,52'],
        ], ['receipt_prefix.regex' => 'Awalan nomor bukti hanya memakai huruf A–Z dan angka.']);
        $action->handle($request->user(), $data);

        return back()->with('success', 'Pengaturan usaha berhasil disimpan.');
    }
}

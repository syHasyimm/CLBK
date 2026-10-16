<?php

namespace App\Http\Controllers;

use App\Services\BackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class BackupController extends Controller
{
    public function store(Request $request, BackupService $backups)
    {
        Gate::authorize('manage-settings');
        $request->validate(['current_password' => ['bail', 'required', 'string', 'current_password:web']]);
        try {
            $result = $backups->create($request->user());
        } catch (\RuntimeException $error) {
            throw ValidationException::withMessages(['backup' => $error->getMessage()]);
        }
        DB::table('audit_logs')->insert(['actor_id' => $request->user()->id, 'action' => 'backup-created', 'entity_type' => 'backups',
            'entity_id' => $result['id'], 'before_json' => null, 'after_json' => null, 'reason' => 'Cadangan manual manajer.', 'occurred_at' => now()]);

        return back()->with('success', 'Cadangan database dan bukti berhasil dibuat.');
    }

    public function download(Request $request, string $backup, BackupService $backups)
    {
        Gate::authorize('manage-settings');
        try {
            $path = $backups->path($backup);
        } catch (\RuntimeException) {
            abort(404);
        }

        return response()->download($path, $backup.'.zip', ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\FinanceKind;
use App\Models\FinanceDocument;
use App\Models\User;
use App\Services\ProofStorage;
use App\Support\ProofUploadRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class FinanceAttachmentController extends Controller
{
    public function store(Request $request, ProofStorage $proofs)
    {
        $kind = FinanceKind::from($request->route('kind'));
        $record = $kind->model()::findOrFail($request->route('document'));
        Gate::authorize('create', $kind->model());
        abort_unless($record->status === 'posted', 403);
        $request->validate(['proof' => ProofUploadRules::proof()]);
        $proofs->store($request->user(), $record, $kind->value, $request->file('proof'),
            function (User $actor, FinanceDocument $record) use ($kind) {
                Gate::forUser($actor)->authorize('create', $kind->model());
                abort_unless($record->status === 'posted', 403);
            });

        return back()->with('success', 'Bukti tersimpan secara privat.');
    }

    public function download(Request $request, int $attachment)
    {
        $file = DB::table('attachments')->find($attachment);
        abort_unless($file && $kind = FinanceKind::tryFrom($file->entity_type), 404);
        $record = $kind->model()::findOrFail($file->entity_id);
        Gate::authorize('view', $record);

        return Storage::disk('local')->download($file->storage_path, $file->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }
}

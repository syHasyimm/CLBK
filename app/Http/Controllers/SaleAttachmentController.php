<?php

namespace App\Http\Controllers;

use App\Actions\Sales\SaveSale;
use App\Enums\TransactionWorkflow;
use App\Models\Sale;
use App\Models\User;
use App\Services\ProofStorage;
use App\Support\ProofUploadRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class SaleAttachmentController extends Controller
{
    public function store(Request $request, ProofStorage $proofs)
    {
        $sale = SaveSale::model($request->route('kind'))::findOrFail($request->route('sale'));
        Gate::authorize('update', $sale);
        abort_unless($sale->status !== 'reversed' && $sale->workflow === TransactionWorkflow::Legacy, 403);
        $request->validate(['proof' => ProofUploadRules::proof()]);
        $proofs->store($request->user(), $sale, $sale->kind(), $request->file('proof'),
            function (User $actor, Sale $sale) {
                Gate::forUser($actor)->authorize('update', $sale);
                abort_unless($sale->status !== 'reversed' && $sale->workflow === TransactionWorkflow::Legacy, 403);
            });

        return back()->with('success', 'Bukti tersimpan secara privat.');
    }

    public function download(Request $request, int $attachment)
    {
        $file = DB::table('attachments')->find($attachment);
        abort_unless($file && in_array($file->entity_type, ['factory', 'sort'], true), 404);
        $sale = SaveSale::model($file->entity_type)::findOrFail($file->entity_id);
        Gate::authorize('view', $sale);

        return Storage::disk('local')->download($file->storage_path, $file->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }
}

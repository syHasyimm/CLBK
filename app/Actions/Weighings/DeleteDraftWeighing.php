<?php

namespace App\Actions\Weighings;

use App\Enums\TransactionWorkflow;
use App\Models\User;
use App\Models\Weighing;
use App\Services\AuditService;
use App\Support\Database\WriteTransaction;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DeleteDraftWeighing
{
    public function handle(User $actor, Weighing $record, int $version): void
    {
        app(WriteTransaction::class)->run(function () use ($actor, $record, $version) {
            $actor = $actor->fresh();
            $record = $record->fresh();
            Gate::forUser($actor)->authorize('delete', $record);
            if ($record->workflow === TransactionWorkflow::Direct) {
                throw ValidationException::withMessages(['operation' => 'Timbangan baru dikoreksi melalui pembatalan, bukan penghapusan draft.']);
            }
            if ($record->status !== 'draft' || $record->version !== $version || $record->trip->status !== 'loading') {
                throw ValidationException::withMessages(['operation' => 'Hanya draft terbaru pada perjalanan loading yang dapat dihapus.']);
            }
            $audit = app(AuditService::class);
            $record->load('items', 'loaders');
            $audit->record($actor, 'delete-draft', $record, $audit->snapshot($record)
                + ['items' => $record->items->toArray(), 'loaders' => $record->loaders->toArray()], null);
            $record->items()->delete();
            $record->loaders()->delete();
            $record->delete();
        });
    }
}

<?php

namespace App\Actions\MasterData;

use App\Enums\MasterResource;
use App\Models\User;
use App\Services\AuditService;
use App\Support\Database\WriteTransaction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DeleteMasterData
{
    public function __construct(private WriteTransaction $transactions, private AuditService $audit) {}

    public function handle(User $actor, MasterResource $resource, int $id): void
    {
        try {
            $this->transactions->run(function () use ($actor, $resource, $id) {
                $record = $resource->model()::query()->findOrFail($id);
                Gate::forUser($actor->fresh())->authorize('delete', $record);
                $before = $this->audit->snapshot($record);
                $record->delete();
                $this->audit->record($actor, 'deleted', $record, $before, null);
            });
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'FOREIGN KEY constraint failed')) {
                throw ValidationException::withMessages(['operation' => 'Data sudah digunakan. Nonaktifkan data untuk mempertahankan riwayatnya.']);
            }
            throw $exception;
        }
    }
}

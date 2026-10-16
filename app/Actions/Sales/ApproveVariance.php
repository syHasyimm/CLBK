<?php

namespace App\Actions\Sales;

use App\Models\FactorySale;
use App\Models\User;
use App\Services\AuditService;
use App\Services\IdempotencyService;
use App\Support\Database\WriteTransaction;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ApproveVariance
{
    public function handle(User $actor, FactorySale $sale, array $data): void
    {
        app(WriteTransaction::class)->run(function () use ($actor, $sale, $data) {
            $actor = $actor->fresh();
            $sale = $sale->fresh();
            Gate::forUser($actor)->authorize('reverse', $sale);
            $data['reason'] = trim($data['reason'] ?? '') ?: 'Persetujuan selisih berat oleh manajer';
            app(IdempotencyService::class)->run($actor, 'approve-factory-variance', $data['idempotency_key'], ['sale_id' => $sale->id, 'version' => (int) $data['version'], 'reason' => trim($data['reason'])], function () use ($actor, $sale, $data) {
                if ($sale->status !== 'draft' || $sale->version !== (int) $data['version']) {
                    throw ValidationException::withMessages(['operation' => 'Periksa draft terbaru sebelum menyetujui selisih berat.']);
                }
                $audit = app(AuditService::class);
                $before = $audit->snapshot($sale);
                $sale->update(['variance_approved_by' => $actor->id, 'variance_reason' => trim($data['reason']), 'version' => $sale->version + 1, 'variance_approved_version' => $sale->version + 1]);
                $audit->record($actor, 'approve-variance', $sale, $before, $audit->snapshot($sale));

                return $sale->id;
            });
        });
    }
}

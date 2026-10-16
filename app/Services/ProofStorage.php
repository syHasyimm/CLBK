<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\User;
use App\Support\BusinessClock;
use App\Support\Database\WriteTransaction;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProofStorage
{
    public function __construct(private WriteTransaction $transactions, private AuditService $audit) {}

    public function store(User $actor, Model $record, string $entityType, UploadedFile $file, Closure $authorize): void
    {
        $size = (int) $file->getSize();
        $this->assertCapacity($actor->id, $entityType, $record->getKey(), $size);
        $directory = $record instanceof Sale ? 'sale-proofs' : 'finance-proofs';
        $name = Str::uuid().'.'.$file->extension();
        $path = $directory.'/'.$name;
        $stored = false;
        $committed = false;

        try {
            $this->transactions->run(function () use ($actor, $record, $entityType, $file, $authorize, $size, $directory, $name, $path, &$stored, &$committed) {
                $actor = $actor->fresh();
                $record = $record->fresh();
                abort_unless($actor && $record, 404);
                $authorize($actor, $record);
                // The MySQL business row lock serializes quota checks and metadata inserts.
                $this->assertCapacity($actor->id, $entityType, $record->getKey(), $size);
                DB::afterCommit(function () use (&$committed) {
                    $committed = true;
                });

                // Stage only after authorization/quota checks; reuse the same file if the DB retries.
                if (! $stored) {
                    if (! Storage::disk('local')->putFileAs($directory, $file, $name, ['visibility' => 'private'])) {
                        throw new \RuntimeException('Bukti tidak dapat disimpan.');
                    }
                    $stored = true;
                }

                $originalName = preg_replace('/[\x00-\x1F\x7F\\\\\/]+/u', '_', $file->getClientOriginalName());
                $id = DB::table('attachments')->insertGetId([
                    'entity_type' => $entityType, 'entity_id' => $record->getKey(), 'storage_path' => $path,
                    'original_name' => mb_substr($originalName ?: 'bukti', 0, 180), 'mime_type' => $file->getMimeType(),
                    'size_bytes' => $size, 'uploaded_by' => $actor->id, 'created_at' => app(BusinessClock::class)->now(),
                ]);
                $this->audit->record($actor, $record instanceof Sale ? 'attach-proof' : 'attach-finance-proof', $record, null, ['attachment_id' => $id]);
            });
        } catch (\Throwable $error) {
            if (! $committed) {
                // The path is known even if the adapter left a partial file before reporting failure.
                Storage::disk('local')->delete($path);
            }
            throw $error;
        }
    }

    private function assertCapacity(int $actorId, string $entityType, int $entityId, int $size): void
    {
        $attachments = DB::table('attachments');
        $documentCount = (clone $attachments)->where('entity_type', $entityType)->where('entity_id', $entityId)->count();
        $actorBytes = (int) (clone $attachments)->where('uploaded_by', $actorId)->sum('size_bytes');
        $totalBytes = (int) (clone $attachments)->sum('size_bytes');

        if ($documentCount >= max(0, (int) config('proofs.max_per_document'))
            || $size > max(0, (int) config('proofs.max_bytes_per_user')) - $actorBytes
            || $size > max(0, (int) config('proofs.max_bytes_total')) - $totalBytes) {
            throw ValidationException::withMessages(['proof' => 'Kuota penyimpanan bukti tercapai. Hubungi manajer.']);
        }
    }
}

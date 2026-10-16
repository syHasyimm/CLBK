<?php

namespace App\Actions\MasterData;

use App\Enums\MasterResource;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\AuditService;
use App\Support\Database\WriteTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveMasterData
{
    public function __construct(private WriteTransaction $transactions, private AuditService $audit) {}

    public function handle(User $actor, MasterResource $resource, ?int $id, array $data): Model
    {
        try {
            return $this->transactions->run(function () use ($actor, $resource, $id, $data) {
                $model = $resource->model();
                $record = $id === null ? new $model : $model::query()->findOrFail($id);
                Gate::forUser($actor->fresh())->authorize($id === null ? 'create' : 'update', $id === null ? $model : $record);
                unset($data['code']);
                if ($id === null) {
                    $data['code'] = $this->nextCode($resource);
                }
                // Validasi ulang referensi setelah write lock; akun bisa berubah bersamaan.
                if (! empty($data['default_driver_id']) && ! User::query()
                    ->whereKey($data['default_driver_id'])->where('role', UserRole::Driver)
                    ->where('is_active', true)->exists()) {
                    throw ValidationException::withMessages(['default_driver_id' => 'Pilih supir yang masih aktif.']);
                }
                $before = $record->exists ? $this->audit->snapshot($record) : null;
                $record->fill($data)->save();
                $this->audit->record($actor, $id === null ? 'created' : 'updated', $record, $before, $this->audit->snapshot($record));

                return $record;
            });
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'UNIQUE constraint failed')) {
                throw ValidationException::withMessages(['operation' => 'Data sudah digunakan. Muat ulang halaman dan periksa kembali isian Anda.']);
            }
            throw $exception;
        }
    }

    // Dipanggil dalam WriteTransaction agar kode unik dan urut saat penyimpanan bersamaan.
    private function nextCode(MasterResource $resource): string
    {
        $prefix = match ($resource) {
            MasterResource::Customers => 'C',
            MasterResource::LoadingWorkers => 'A',
            MasterResource::Vehicles => 'M',
            MasterResource::Factories => 'P',
            MasterResource::CashAccounts => 'K',
            MasterResource::ExpenseCategories => 'E',
        };
        $name = 'master:'.$resource->value;
        DB::table('document_sequences')->insertOrIgnore(['name' => $name, 'next_value' => 1]);
        $value = (int) DB::table('document_sequences')->where('name', $name)->value('next_value');
        $model = $resource->model();
        do {
            $code = $prefix.str_pad((string) $value++, 3, '0', STR_PAD_LEFT);
        } while ($model::query()->where('code', $code)->exists());
        DB::table('document_sequences')->where('name', $name)->update(['next_value' => $value]);

        return $code;
    }
}

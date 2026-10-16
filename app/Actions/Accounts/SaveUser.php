<?php

namespace App\Actions\Accounts;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\AuditService;
use App\Support\Database\WriteTransaction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveUser
{
    public function __construct(private WriteTransaction $transactions, private AuditService $audit) {}

    public function handle(User $actor, ?int $id, array $data): User
    {
        try {
            return $this->transactions->run(function () use ($actor, $id, $data) {
                $user = $id === null ? new User : User::query()->findOrFail($id);
                Gate::forUser($actor->fresh())->authorize($id === null ? 'create' : 'update', $id === null ? User::class : $user);
                $before = $user->exists ? $this->audit->snapshot($user) : null;
                $passwordChanged = ! empty($data['password']);
                if (! $passwordChanged) {
                    unset($data['password']);
                }
                unset($data['password_confirmation']);
                $user->fill($data);
                $user->assigned_vehicle_id = $user->role === UserRole::Driver ? $user->assigned_vehicle_id : null;
                if (array_key_exists('assigned_vehicle_id', $data) && $user->assigned_vehicle_id
                    && ! Vehicle::whereKey($user->assigned_vehicle_id)->where('is_active', true)->exists()) {
                    throw ValidationException::withMessages(['assigned_vehicle_id' => 'Pilih mobil yang masih aktif.']);
                }
                if (! $user->exists) {
                    $user->vehicle_assignment_version = 1;
                } elseif ($user->isDirty('assigned_vehicle_id')) {
                    $user->vehicle_assignment_version = ($user->vehicle_assignment_version ?? 1) + 1;
                }
                if ($id !== null && $before['role'] === UserRole::Manager
                    && $before['is_active'] && (! $user->is_active || $user->role !== UserRole::Manager)
                    && ! User::query()->whereKeyNot($id)->where('role', UserRole::Manager)->where('is_active', true)->exists()) {
                    throw ValidationException::withMessages(['operation' => 'Harus ada minimal satu manajer aktif. Buat manajer lain terlebih dahulu.']);
                }
                if ($user->isDirty('email')) {
                    $user->email_verified_at = null;
                }
                if (! $user->is_active || $passwordChanged) {
                    // Sesi database dan cookie remember lama tidak dapat digunakan kembali.
                    $user->remember_token = Str::random(60);
                    if ($id !== null) {
                        DB::table('sessions')->where('user_id', $id)->delete();
                    }
                }
                $user->save();
                $this->audit->record($actor, $id === null ? 'created' : ($passwordChanged ? 'updated_with_password_reset' : 'updated'), $user, $before, $this->audit->snapshot($user));

                return $user;
            });
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'UNIQUE constraint failed')) {
                throw ValidationException::withMessages(['email' => 'Email sudah digunakan.']);
            }
            throw $exception;
        }
    }
}

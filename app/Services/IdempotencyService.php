<?php

namespace App\Services;

use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IdempotencyService
{
    // Caller memegang write lock dan sudah memeriksa policy, termasuk pada replay.
    public function run(User $actor, string $operation, string $key, array $payload, Closure $callback): int
    {
        ksort($payload);
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        $query = DB::table('idempotency_requests')->where(['actor_id' => $actor->id, 'operation' => $operation, 'key' => $key]);
        if ($request = $query->first()) {
            if (! hash_equals($request->request_hash, $hash)) {
                throw ValidationException::withMessages(['operation' => 'Kunci pengesahan sudah digunakan dengan data berbeda. Muat ulang tiket.']);
            }

            return (int) $request->response_reference;
        }
        $id = $callback();
        DB::table('idempotency_requests')->insert(['actor_id' => $actor->id, 'operation' => $operation, 'key' => $key,
            'request_hash' => $hash, 'response_reference' => $id, 'status' => 'completed', 'created_at' => now()]);

        return $id;
    }
}

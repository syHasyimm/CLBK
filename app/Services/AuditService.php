<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AuditService
{
    public function snapshot(Model $record): array
    {
        // Allowlist mencegah kredensial/atribut baru ikut tersimpan dalam audit.
        $fields = $record instanceof User
            ? ['id', 'name', 'email', 'role', 'is_active', 'assigned_vehicle_id', 'vehicle_assignment_version']
            : array_merge(['id'], $record->getFillable());

        return $record->only($fields);
    }

    public function record(User $actor, string $action, Model $record, ?array $before, ?array $after): void
    {
        DB::table('audit_logs')->insert([
            'actor_id' => $actor->id,
            'action' => $action,
            'entity_type' => $record->getTable(),
            'entity_id' => (string) $record->getKey(),
            'before_json' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
            'after_json' => $after === null ? null : json_encode($after, JSON_THROW_ON_ERROR),
            'occurred_at' => now(),
        ]);
    }
}

<?php

namespace App\Actions\Settings;

use App\Models\AppSetting;
use App\Models\User;
use App\Services\AuditService;
use App\Support\Database\WriteTransaction;
use Illuminate\Support\Facades\Gate;

class SaveBusinessSettings
{
    public function __construct(private WriteTransaction $transactions, private AuditService $audit) {}

    public function handle(User $actor, array $data): void
    {
        $this->transactions->run(function () use ($actor, $data) {
            Gate::forUser($actor->fresh())->authorize('manage-settings');
            foreach ($data as $key => $value) {
                $setting = AppSetting::query()->firstOrNew(['key' => $key]);
                $before = $setting->exists ? $this->audit->snapshot($setting) : null;
                $setting->fill(['value_json' => $value, 'updated_by' => $actor->id])->save();
                $this->audit->record($actor, 'updated', $setting, $before, $this->audit->snapshot($setting));
            }
        });
    }
}

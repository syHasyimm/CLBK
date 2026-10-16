<?php

namespace App\Http\Requests\Weighings;

use App\Enums\UserRole;
use App\Models\Weighing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SaveWeighingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->route('weighing');

        return Gate::allows($record ? 'update' : 'create', $record ?? Weighing::class);
    }

    public function rules(): array
    {
        $driver = $this->user()->role === UserRole::Driver;

        return ['customer_id' => ['required', 'integer', 'min:1'], 'trip_id' => ['required', 'integer', 'min:1', 'exists:trips,id'],
            'tbs_kg' => ['required', 'string', 'max:15'], 'sort_kg' => ['required', 'string', 'max:15'],
            'worker_ids' => ['present', 'array', 'max:100'], 'worker_ids.*' => ['integer', 'min:1', 'distinct'],
            'weighed_at' => [$driver ? 'prohibited' : 'required', 'string', 'max:19'],
            'backdate_reason' => [$driver ? 'prohibited' : 'nullable', 'string', 'max:1000'],
            'replaces_weighing_id' => [$driver ? 'prohibited' : 'nullable', 'integer', 'min:1'],
            'version' => [$this->route('weighing') ? 'required' : 'nullable', 'integer', 'min:1']];
    }
}

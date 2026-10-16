<?php

namespace App\Http\Requests\MasterData;

use App\Enums\MasterResource;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMasterDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', $this->resource()->model())
            && $this->user()->can('create', $this->resource()->model());
    }

    public function resource(): MasterResource
    {
        return MasterResource::from($this->route('resource'));
    }

    protected function prepareForValidation(): void
    {
        $plate = $this->input('plate_number');
        if (is_string($plate)) {
            $this->merge(['plate_number' => preg_replace('/\\s+/', '', mb_strtoupper(trim($plate)))]);
        }
    }

    public function rules(): array
    {
        $resource = $this->resource();
        $id = $this->route('record');
        $rules = ['is_active' => ['required', 'boolean']];
        foreach ($resource->fields() as $field) {
            $key = $field['key'];
            $rules[$key] = ['bail', $field['required'] ? 'required' : 'nullable', 'string'];
            if ($field['maxLength'] !== null) {
                $rules[$key][] = 'max:'.$field['maxLength'];
            }
        }
        if ($resource === MasterResource::LoadingWorkers) {
            $rules['joined_on'] = ['nullable', 'date_format:Y-m-d'];
        }
        if ($resource === MasterResource::Vehicles) {
            $rules['plate_number'][] = 'regex:/^[A-Z0-9-]+$/';
            $rules['plate_number'][] = Rule::unique('vehicles', 'plate_number')->ignore($id);
            $rules['capacity_kg'] = ['bail', 'nullable', 'string', 'regex:/^(?:0|[1-9]\\d{0,6})(?:[.,]\\d{1,3})?$/', function ($attribute, $value, $fail) {
                $grams = $this->grams($value);
                if ($grams < 1 || $grams > 1000000000) {
                    $fail('Kapasitas harus lebih dari 0 dan maksimal 1.000.000 kg.');
                }
            }];
            $rules['default_driver_id'] = ['nullable', 'integer', Rule::exists(User::class, 'id')->where(fn ($query) => $query->where('role', UserRole::Driver->value)->where('is_active', true))];
        }
        if ($resource === MasterResource::CashAccounts) {
            $rules['type'] = ['required', Rule::in(['cash', 'bank'])];
        }

        return $rules;
    }

    public function attributes(): array
    {
        return array_column($this->resource()->fields(), 'label', 'key') + ['is_active' => 'Status aktif'];
    }

    public function payload(): array
    {
        $data = $this->validated();
        if ($this->resource() === MasterResource::Vehicles) {
            $data['capacity_grams'] = ($data['capacity_kg'] ?? null) === null ? null : $this->grams($data['capacity_kg']);
            unset($data['capacity_kg']);
        }

        return $data;
    }

    private function grams(string $kg): int
    {
        [$whole, $fraction] = array_pad(explode('.', str_replace(',', '.', $kg), 2), 2, '');

        return ((int) $whole * 1000) + (int) str_pad($fraction, 3, '0');
    }
}

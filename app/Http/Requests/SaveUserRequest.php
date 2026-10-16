<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SaveUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['bail', 'required', 'email', 'max:255', Rule::unique('users')->ignore($this->route('user'))],
            'role' => ['required', Rule::enum(UserRole::class)],
            'is_active' => ['required', 'boolean'],
            'assigned_vehicle_id' => ['nullable', ...($this->input('role') === UserRole::Driver->value
                ? ['integer', 'min:1', Rule::exists('vehicles', 'id')->where('is_active', true)] : ['prohibited'])],
            'password' => [$this->route('user') ? 'nullable' : 'required', 'confirmed', Password::defaults()],
        ];
    }

    public function attributes(): array
    {
        return ['role' => 'Role', 'is_active' => 'Status aktif', 'assigned_vehicle_id' => 'Mobil yang ditugaskan'];
    }
}

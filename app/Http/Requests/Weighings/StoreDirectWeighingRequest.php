<?php

namespace App\Http\Requests\Weighings;

use App\Enums\UserRole;
use App\Models\Weighing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreDirectWeighingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Weighing::class);
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'min:1'],
            'vehicle_id' => ['required', 'integer', 'min:1'],
            'vehicle_assignment_version' => [$this->user()->role === UserRole::Driver ? 'required' : 'nullable', 'integer', 'min:1'],
            'tbs_kg' => ['required', 'string', 'max:15'],
            'sort_kg' => ['required', 'string', 'max:15'],
            'worker_ids' => ['required', 'array', 'min:1', 'max:100'],
            'worker_ids.*' => ['integer', 'min:1', 'distinct'],
            'price_schedule_id' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'uuid'],
            'replaces_weighing_id' => [$this->user()->role === UserRole::Manager ? 'nullable' : 'prohibited', 'integer', 'min:1'],
            'trip_id' => ['prohibited'], 'weighed_at' => ['prohibited'], 'backdate_reason' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'Pilih konsumen.', 'vehicle_id.required' => 'Pilih mobil yang mengambil buah.',
            'vehicle_assignment_version.required' => 'Muat ulang halaman untuk memeriksa penugasan mobil.',
            'worker_ids.required' => 'Pilih minimal satu anggota muat.', 'worker_ids.min' => 'Pilih minimal satu anggota muat.',
            'worker_ids.*.distinct' => 'Anggota muat tidak boleh dipilih dua kali.',
            'price_schedule_id.required' => 'Periksa ringkasan harga sebelum menyimpan.',
            'trip_id.prohibited' => 'Timbangan baru menggunakan mobil langsung tanpa perjalanan muatan.',
            'weighed_at.prohibited' => 'Waktu timbang dicatat otomatis saat disimpan.',
            'backdate_reason.prohibited' => 'Timbangan baru memakai waktu usaha saat disimpan.',
        ];
    }
}

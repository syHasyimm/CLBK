<?php

namespace App\Http\Requests\Sales;

use App\Actions\Sales\SaveSale;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreDirectSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', SaveSale::model($this->route('kind')));
    }

    public function rules(): array
    {
        $factory = $this->route('kind') === 'factory';

        return ['vehicle_id' => ['required', 'integer', 'min:1'], 'idempotency_key' => ['required', 'uuid'],
            'vehicle_assignment_version' => [$this->user()->role === UserRole::Driver ? 'required' : 'nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'cash_account_id' => ['prohibited'],
            'payment_reference' => ['prohibited'],
            'replaces_sale_id' => ['nullable', ...($this->user()->role === UserRole::Manager ? ['integer', 'min:1'] : ['prohibited'])],
            'correction_reason' => ['nullable', 'string', 'max:1000'],
            'trip_id' => ['prohibited'], 'sold_at' => ['prohibited'], 'initial_kg' => ['prohibited'], 'sort_kg' => ['prohibited'],
            'deduction_reason' => ['prohibited'], 'proof' => ['prohibited'],
        ] + ($factory ? ['factory_id' => ['required', 'integer', 'min:1'], 'accepted_kg' => ['required', 'string', 'max:15'],
            'factory_price_schedule_id' => ['required', 'integer', 'min:1'], 'invoice_number' => ['nullable', 'string', 'max:100'],
            'deduction_idr' => ['nullable', 'integer', 'between:0,100000000000000'], 'sell_price_snapshot_idr' => ['prohibited'],
        ] : ['sold_kg' => ['required', 'string', 'max:15'], 'sell_price_snapshot_idr' => ['required', 'integer', 'between:1,100000000'],
            'buyer_name' => ['nullable', 'string', 'max:150'], 'buyer_contact' => ['nullable', 'string', 'max:100'], 'deduction_idr' => ['prohibited']]);
    }

    public function messages(): array
    {
        return ['cash_account_id.prohibited' => 'Catat uang masuk melalui Penerimaan Penjualan.',
            'payment_reference.prohibited' => 'Catat referensi pembayaran melalui Penerimaan Penjualan.',
            'vehicle_id.required' => 'Pilih mobil yang membawa buah.', 'factory_id.required' => 'Pilih pabrik tujuan.',
            'vehicle_assignment_version.required' => 'Muat ulang halaman untuk memeriksa penugasan mobil.',
            'accepted_kg.required' => 'Isi berat bersih diterima pabrik.', 'sold_kg.required' => 'Isi berat sortir terjual.',
            'factory_price_schedule_id.required' => 'Periksa harga pabrik dan ringkasan sebelum menyimpan.',
            'trip_id.prohibited' => 'Penjualan baru memakai mobil langsung.', 'sort_kg.prohibited' => 'Penjualan pabrik hanya mencatat berat bersih.',
            'sold_at.prohibited' => 'Waktu penjualan dicatat otomatis.', 'sell_price_snapshot_idr.required' => 'Isi harga jual sortir per kg.'];
    }
}

<?php

namespace App\Http\Requests\Sales;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class SaveSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Fresh actor and record policy are checked inside the write lock.
    }

    public function rules(): array
    {
        $rules = ['trip_id' => ['required', 'integer', 'min:1'], 'version' => [$this->isMethod('PATCH') ? 'required' : 'nullable', 'integer', 'min:1'],
            'sold_at' => [$this->user()->role === UserRole::Driver ? 'prohibited' : 'required', 'string', 'max:19'],
            'sell_price_snapshot_idr' => [$this->route('kind') === 'factory' ? 'exclude' : 'required', 'integer', 'between:1,100000000'], 'deduction_idr' => ['required', 'integer', 'between:0,100000000000000'],
            'deduction_reason' => ['nullable', 'string', 'max:1000'], 'notes' => ['nullable', 'string', 'max:1000'],
            'replaces_sale_id' => ['nullable', 'integer', 'min:1'], 'correction_reason' => ['nullable', 'string', 'max:1000']];

        return $rules + ($this->route('kind') === 'factory' ? ['factory_id' => ['required', 'integer', 'min:1'], 'invoice_number' => ['required', 'string', 'max:100'],
            'initial_kg' => ['nullable', 'string', 'max:15'], 'sort_kg' => ['required', 'string', 'max:15'], 'accepted_kg' => ['required', 'string', 'max:15']] :
            ['buyer_name' => ['required', 'string', 'max:150'], 'buyer_contact' => ['nullable', 'string', 'max:100'], 'sold_kg' => ['required', 'string', 'max:15']]);
    }
}

<?php

namespace App\Http\Requests\Finance;

use App\Enums\FinanceKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreFinanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', FinanceKind::from($this->route('kind'))->model());
    }

    public function rules(): array
    {
        $kind = FinanceKind::from($this->route('kind'));
        $rules = ['cash_account_id' => ['nullable', 'integer', 'min:1'], 'amount_idr' => ['required', 'integer', 'between:1,100000000000000'],
            'occurred_at' => ['required', 'string', 'max:19'], 'backdate_reason' => ['nullable', 'string', 'max:1000'],
            'method' => ['required', 'in:cash,bank_transfer'], 'reference' => ['nullable', 'required_if:method,bank_transfer', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'], 'idempotency_key' => ['required', 'uuid'],
            'all_in_period' => ['prohibited']];
        if (in_array($kind, [FinanceKind::Customer, FinanceKind::Payroll], true)) {
            $automatic = $kind === FinanceKind::Customer || ($kind === FinanceKind::Payroll && $this->boolean('all_in_period'));
            $rules += ['recipient_id' => ['required', 'integer', 'min:1'],
                'selected_ids' => $kind === FinanceKind::Customer ? ['sometimes', 'array', 'max:200'] : ($automatic ? ['prohibited'] : ['required', 'array', 'min:1', 'max:200']),
                'expected_remaining_idr' => ['required', 'integer', 'min:0']];
            if (! $automatic || $kind === FinanceKind::Customer) {
                $rules['selected_ids.*'] = ['required', 'integer', 'min:1', 'distinct'];
            }
        }
        if ($kind === FinanceKind::Payroll) {
            $rules['all_in_period'] = ['sometimes', 'boolean'];
            $rules += ['period_start' => ['required', 'date_format:Y-m-d'], 'period_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_start']];
        }
        if ($kind === FinanceKind::Receipt) {
            $rules += ['sale_kind' => ['required', 'in:factory,sort'], 'sale_id' => ['required', 'integer', 'min:1'], 'expected_remaining_idr' => ['required', 'integer', 'min:0']];
        }
        if ($kind === FinanceKind::Expense) {
            $rules += ['expense_category_id' => ['required', 'integer', 'min:1'], 'trip_id' => ['prohibited'], 'description' => ['required', 'string', 'max:1000']];
        }

        return $rules;
    }
}

<?php

namespace App\Http\Requests;

use App\Enums\MasterResource;
use App\Http\Requests\MasterData\SaveMasterDataRequest;
use App\Models\Customer;

class StoreDriverCustomerRequest extends SaveMasterDataRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Customer::class);
    }

    public function resource(): MasterResource
    {
        return MasterResource::Customers;
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        $this->merge(['is_active' => true]);
    }
}

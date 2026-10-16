<?php

namespace App\Http\Controllers;

use App\Actions\MasterData\SaveMasterData;
use App\Enums\MasterResource;
use App\Http\Requests\StoreDriverCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DriverCustomerController extends Controller
{
    public function create(): Response
    {
        Gate::authorize('create', Customer::class);

        return Inertia::render('Customers/Create', [
            'fields' => MasterResource::Customers->fields(),
            'options' => [],
        ]);
    }

    public function store(StoreDriverCustomerRequest $request, SaveMasterData $action): RedirectResponse
    {
        $customer = $action->handle($request->user(), MasterResource::Customers, null, $request->payload());

        return to_route('weighings.create', ['customer_id' => $customer->id])
            ->with('success', 'Konsumen berhasil ditambahkan. Lanjutkan pencatatan timbangan.');
    }
}

<?php

namespace App\Actions\Sales;

use App\Domain\Sales\DirectSaleInput;
use App\Domain\Shared\Weight;
use App\Enums\TransactionWorkflow;
use App\Enums\UserRole;
use App\Models\FactorySale;
use App\Models\Sale;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\AuditService;
use App\Services\DriverVehicleAssignment;
use App\Services\IdempotencyService;
use App\Services\ProfitLedger;
use App\Support\BusinessClock;
use App\Support\Database\WriteTransaction;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveDirectSale
{
    public function handle(User $actor, string $kind, array $data): Sale
    {
        return app(WriteTransaction::class)->run(function () use ($actor, $kind, $data) {
            $actor = $actor->fresh();
            $model = SaveSale::model($kind);
            Gate::forUser($actor)->authorize('create', $model);
            $field = $kind === 'factory' ? 'accepted_kg' : 'sold_kg';
            $text = function ($key) use ($data) {
                $value = trim($data[$key] ?? '');

                return $value === '' ? null : $value;
            };
            $payload = ['vehicle_id' => (int) $data['vehicle_id'], 'grams' => Weight::grams($data[$field], $field),
                'notes' => $text('notes'), 'replaces_sale_id' => empty($data['replaces_sale_id']) ? null : (int) $data['replaces_sale_id'],
                'correction_reason' => $text('correction_reason'), 'cash_account_id' => (int) ($data['cash_account_id'] ?? 0),
                'payment_reference' => $text('payment_reference')];
            $payload += $kind === 'factory' ? ['factory_id' => (int) $data['factory_id'], 'factory_price_schedule_id' => (int) $data['factory_price_schedule_id'],
                'invoice_number' => $text('invoice_number'), 'deduction_idr' => (int) ($data['deduction_idr'] ?? 0)]
                : ['sell_price_snapshot_idr' => (int) $data['sell_price_snapshot_idr'], 'buyer_name' => $text('buyer_name'), 'buyer_contact' => $text('buyer_contact')];
            if ($actor->role === UserRole::Driver) {
                $payload['vehicle_assignment_version'] = isset($data['vehicle_assignment_version']) ? (int) $data['vehicle_assignment_version'] : null;
            }
            $id = app(IdempotencyService::class)->run($actor, 'save-direct-'.$kind.'-sale', $data['idempotency_key'], $payload, function () use ($actor, $kind, $model, $data, $payload) {
                $vehicleId = app(DriverVehicleAssignment::class)->forWrite($actor, $payload['vehicle_id'], $payload['vehicle_assignment_version'] ?? null);
                if (! Vehicle::whereKey($payload['vehicle_id'])->where('is_active', true)->exists()) {
                    throw ValidationException::withMessages(['vehicle_id' => 'Pilih mobil aktif.']);
                }
                if ($payload['replaces_sale_id']) {
                    $previous = $model::findOrFail($payload['replaces_sale_id']);
                    Gate::forUser($actor)->authorize('reverse', $previous);
                    if ($previous->status !== 'reversed' || $previous->vehicle_id !== $payload['vehicle_id'] || ! $payload['correction_reason']
                        || ($kind === 'factory' && $previous->factory_id !== $payload['factory_id'])) {
                        throw ValidationException::withMessages(['correction_reason' => 'Pengganti harus merujuk nota batal untuk mobil dan tujuan yang sama serta mempunyai alasan koreksi.']);
                    }
                    if ($model::where('replaces_sale_id', $previous->id)->where('status', '!=', 'reversed')->exists()) {
                        throw ValidationException::withMessages(['replaces_sale_id' => 'Nota pengganti aktif sudah ada.']);
                    }
                }
                $clock = app(BusinessClock::class);
                $time = $clock->now();
                $input = app(DirectSaleInput::class);
                $price = $kind === 'factory' ? $input->price($payload['factory_id'], $time) : null;
                if ($price && $price->id !== $payload['factory_price_schedule_id']) {
                    throw ValidationException::withMessages(['factory_price_schedule_id' => 'Harga berubah. Periksa ringkasan terbaru lalu klik Simpan Penjualan kembali.']);
                }
                if ($kind === 'factory' && $payload['invoice_number'] && FactorySale::where('factory_id', $payload['factory_id'])->where('invoice_number', $payload['invoice_number'])->whereIn('status', ['draft', 'posted'])->exists()) {
                    throw ValidationException::withMessages(['invoice_number' => 'Nomor nota sudah digunakan pada pabrik ini.']);
                }
                $rate = $price?->tbs_sell_price_idr ?? $payload['sell_price_snapshot_idr'];
                $calc = $input->calculate($kind, $data, $rate);
                $attributes = ['number' => app(DocumentNumber::class)->next($kind === 'factory' ? 'PBR' : 'SRT', $clock->date($time)),
                    'vehicle_id' => $vehicleId, 'workflow' => TransactionWorkflow::Direct, 'trip_id' => null,
                    'sold_at' => $time, 'business_date' => $clock->date($time), 'sell_price_snapshot_idr' => $rate,
                    'revenue_idr' => $calc['revenue_idr'], 'deduction_idr' => $calc['deduction_idr'], 'deduction_reason' => $calc['deduction_idr'] ? 'Potongan penjualan pabrik' : null,
                    'notes' => $payload['notes'], 'status' => 'draft', 'cost_status' => 'unavailable',
                    'replaces_sale_id' => $payload['replaces_sale_id'], 'correction_reason' => $payload['replaces_sale_id'] ? $payload['correction_reason'] : null,
                    'created_by' => $actor->id];
                $attributes += $kind === 'factory' ? ['factory_id' => $payload['factory_id'], 'factory_price_schedule_id' => $price->id,
                    'invoice_number' => $payload['invoice_number'], 'accepted_grams' => $calc['grams']]
                    : ['manual_sold_grams' => $calc['grams'], 'buyer_name' => $payload['buyer_name'], 'buyer_contact' => $payload['buyer_contact']];
                $sale = $model::create($attributes);
                $sale->update(['revenue_idr' => $calc['revenue_idr'], 'status' => 'posted', 'posted_at' => $time, 'posted_by' => $actor->id, 'version' => 2]);
                app(ProfitLedger::class)->sale($actor, $sale);
                $audit = app(AuditService::class);
                $audit->record($actor, 'save-direct', $sale, null, $audit->snapshot($sale));

                return $sale->id;
            });
            $sale = $model::findOrFail($id);
            Gate::forUser($actor)->authorize('view', $sale);

            return $sale;
        });
    }
}

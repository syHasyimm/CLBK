<?php

namespace App\Actions\Sales;

use App\Domain\Shared\Money;
use App\Domain\Shared\Weight;
use App\Enums\TransactionWorkflow;
use App\Enums\UserRole;
use App\Models\Factory;
use App\Models\FactoryPriceSchedule;
use App\Models\FactorySale;
use App\Models\Sale;
use App\Models\SortSale;
use App\Models\Trip;
use App\Models\User;
use App\Services\AuditService;
use App\Support\BusinessClock;
use App\Support\Database\WriteTransaction;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveSale
{
    public static function model(string $kind): string
    {
        return match ($kind) {
            'factory' => FactorySale::class, 'sort' => SortSale::class, default => throw new \InvalidArgumentException('Unknown sale kind.')
        };
    }

    public static function eligible(Trip $trip, string $kind): bool
    {
        return $kind === 'factory' ? in_array($trip->status, ['dispatched', 'correction_pending'], true) : in_array($trip->status, ['loading', 'dispatched', 'factory_recorded', 'correction_pending'], true);
    }

    public function handle(User $actor, string $kind, array $data, ?Sale $sale = null): Sale
    {
        return app(WriteTransaction::class)->run(function () use ($actor, $kind, $data, $sale) {
            $actor = $actor->fresh();
            $model = self::model($kind);
            if ($sale) {
                $sale = $sale->fresh();
                Gate::forUser($actor)->authorize('update', $sale);
                if ($sale->workflow === TransactionWorkflow::Direct) {
                    throw ValidationException::withMessages(['operation' => 'Koreksi penjualan sah melalui pembatalan dan nota pengganti.']);
                }
                if ($sale->status !== 'draft' || $sale->version !== (int) $data['version']) {
                    throw ValidationException::withMessages(['operation' => 'Draft berubah atau sudah disahkan. Muat ulang halaman.']);
                }
            } else {
                Gate::forUser($actor)->authorize('create', $model);
            }
            $trip = Trip::findOrFail($data['trip_id']);
            Gate::forUser($actor)->authorize('view', $trip);
            if (! self::eligible($trip, $kind) || ! $trip->vehicle->is_active || ! $trip->driver->is_active || $trip->driver->role !== UserRole::Driver) {
                throw ValidationException::withMessages(['trip_id' => 'Pilih perjalanan yang masih dapat mencatat penjualan dengan mobil dan supir aktif.']);
            }
            $clock = app(BusinessClock::class);
            $time = $actor->role === UserRole::Driver ? $clock->now() : $clock->parse($data['sold_at'], 'sold_at');
            if ($time->gt($clock->now()) || ($kind === 'factory' && $time->lt($trip->departed_at))) {
                throw ValidationException::withMessages(['sold_at' => 'Waktu penjualan harus setelah keberangkatan dan tidak di masa depan.']);
            }
            $replacementId = $sale?->replaces_sale_id ?? ($data['replaces_sale_id'] ?? null);
            if ($replacementId) {
                $previous = $model::findOrFail($replacementId);
                Gate::forUser($actor)->authorize('reverse', $previous);
                if ($previous->status !== 'reversed' || $previous->trip_id !== $trip->id || $model::where('replaces_sale_id', $previous->id)->when($sale, fn ($q) => $q->where('id', '!=', $sale->id))->exists() || trim($data['correction_reason'] ?? '') === '') {
                    throw ValidationException::withMessages(['correction_reason' => 'Pengganti harus terkait nota terbalik pada perjalanan yang sama, disertai alasan koreksi.']);
                }
            } elseif ($kind === 'factory' && FactorySale::where('trip_id', $trip->id)->where('status', 'reversed')->exists()) {
                throw ValidationException::withMessages(['operation' => 'Buat nota pengganti dari nota pabrik yang dibalik.']);
            }
            $values = ['vehicle_id' => $trip->vehicle_id, 'workflow' => TransactionWorkflow::Legacy, 'trip_id' => $trip->id, 'sold_at' => $time, 'business_date' => $clock->date($time),
                'sell_price_snapshot_idr' => $kind === 'factory' ? 0 : (int) $data['sell_price_snapshot_idr'], 'deduction_idr' => (int) ($data['deduction_idr'] ?? 0),
                'deduction_reason' => trim($data['deduction_reason'] ?? '') ?: null, 'notes' => trim($data['notes'] ?? '') ?: null,
                'correction_reason' => $replacementId ? trim($data['correction_reason']) : null, 'replaces_sale_id' => $replacementId];
            if ($values['deduction_idr'] > 0 && ! $values['deduction_reason']) {
                if ($kind === 'factory') {
                    $values['deduction_reason'] = 'Potongan penjualan pabrik';
                } else {
                    throw ValidationException::withMessages(['deduction_reason' => 'Alasan potongan wajib diisi.']);
                }
            }
            if ($kind === 'factory') {
                if (! Factory::whereKey($data['factory_id'])->where('is_active', true)->exists()) {
                    throw ValidationException::withMessages(['factory_id' => 'Pilih pabrik aktif.']);
                }
                $sorted = Weight::grams($data['sort_kg'], 'sort_kg');
                $accepted = Weight::grams($data['accepted_kg'], 'accepted_kg');
                // Legacy clients may still supply an initial weight; the current form only supplies final weights.
                $initial = isset($data['initial_kg']) ? Weight::grams($data['initial_kg'], 'initial_kg') : $sorted + $accepted;
                if ($initial > 1000000000) {
                    throw ValidationException::withMessages(['accepted_kg' => 'Jumlah berat bersih dan sortir melebihi batas berat yang dapat dicatat.']);
                }
                if ($initial === 0 || $accepted === 0 || $sorted > $initial) {
                    throw ValidationException::withMessages([isset($data['initial_kg']) ? 'initial_kg' : 'accepted_kg' => 'Berat bersih harus positif; sortir tidak melebihi total muatan.']);
                }
                $price = FactoryPriceSchedule::where('factory_id', $data['factory_id'])->where('effective_from', '<=', $time)->orderByDesc('effective_from')->first();
                if (! $price) {
                    throw ValidationException::withMessages(['factory_id' => 'Harga pabrik belum tersedia untuk waktu penjualan ini. Atur harga pabrik terlebih dahulu.']);
                }
                $values['sell_price_snapshot_idr'] = $price->tbs_sell_price_idr;
                $values['factory_price_schedule_id'] = $price->id;
                if (FactorySale::where('trip_id', $trip->id)->whereIn('status', ['draft', 'posted'])->when($sale, fn ($q) => $q->where('id', '!=', $sale->id))->exists()) {
                    throw ValidationException::withMessages(['trip_id' => 'Perjalanan ini sudah mempunyai nota pabrik aktif.']);
                }
                $invoice = trim($data['invoice_number']);
                if ($invoice === '' || FactorySale::where('factory_id', $data['factory_id'])->where('invoice_number', $invoice)->whereIn('status', ['draft', 'posted'])->when($sale, fn ($q) => $q->where('id', '!=', $sale->id))->exists()) {
                    throw ValidationException::withMessages(['invoice_number' => 'Nomor nota wajib diisi dan belum digunakan pada pabrik ini.']);
                }
                $values += ['factory_id' => (int) $data['factory_id'], 'invoice_number' => $invoice, 'initial_load_grams' => $initial,
                    'sorted_out_grams' => $sorted, 'accepted_grams' => $accepted, 'weight_difference_grams' => $initial - $sorted - $accepted,
                    'variance_approved_by' => null, 'variance_approved_version' => null, 'variance_reason' => null];
                $weight = $accepted;
            } else {
                $weight = Weight::grams($data['sold_kg'], 'sold_kg');
                if ($weight === 0 || trim($data['buyer_name']) === '') {
                    throw ValidationException::withMessages(['sold_kg' => 'Berat jual harus positif dan pembeli wajib diisi.']);
                }
                $values += ['manual_sold_grams' => $weight, 'buyer_name' => trim($data['buyer_name']), 'buyer_contact' => trim($data['buyer_contact'] ?? '') ?: null];
            }
            $values['revenue_idr'] = Money::forWeight($weight, $values['sell_price_snapshot_idr']) - $values['deduction_idr'];
            if ($values['revenue_idr'] < 0) {
                throw ValidationException::withMessages(['deduction_idr' => 'Potongan melebihi nilai penjualan.']);
            }
            $audit = app(AuditService::class);
            $before = $sale ? $audit->snapshot($sale) : null;
            if ($sale) {
                $sale->fill($values + ['version' => $sale->version + 1])->save();
            } else {
                $sale = $model::create($values + ['number' => app(DocumentNumber::class)->next($kind === 'factory' ? 'JUAL' : 'SORT', $values['business_date']), 'status' => 'draft', 'created_by' => $actor->id]);
            }
            $audit->record($actor, $before ? 'update-draft' : 'create-draft', $sale, $before, $audit->snapshot($sale));

            return $sale;
        });
    }
}

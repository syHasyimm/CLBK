<?php

namespace App\Actions\Prices;

use App\Models\Factory;
use App\Models\FactoryPriceSchedule;
use App\Models\PriceSchedule;
use App\Models\User;
use App\Models\Weighing;
use App\Services\AuditService;
use App\Support\BusinessClock;
use App\Support\Database\WriteTransaction;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PublishPrice
{
    public function handle(User $actor, array $data, bool $factory = false): PriceSchedule|FactoryPriceSchedule
    {
        return app(WriteTransaction::class)->run(function () use ($actor, $data, $factory) {
            $actor = $actor->fresh();
            Gate::forUser($actor)->authorize('manage-prices');
            $clock = app(BusinessClock::class);
            $time = $clock->parse($data['effective_from']);
            if ($factory) {
                if (! Factory::query()->whereKey($data['factory_id'])->where('is_active', true)->exists()) {
                    throw ValidationException::withMessages(['factory_id' => 'Pilih pabrik aktif.']);
                }
                if (FactoryPriceSchedule::query()->where('factory_id', $data['factory_id'])->where('effective_from', $time)->exists()) {
                    throw ValidationException::withMessages(['effective_from' => 'Versi harga pabrik pada waktu ini sudah ada.']);
                }
                $price = FactoryPriceSchedule::create(['factory_id' => $data['factory_id'], 'effective_from' => $time,
                    'tbs_sell_price_idr' => $data['tbs_sell_price_idr'], 'published_by' => $actor->id]);
            } else {
                $latest = Weighing::query()->whereIn('status', ['posted', 'voided'])->max('weighed_at');
                if ($latest && $time->lessThanOrEqualTo($latest)) {
                    throw ValidationException::withMessages(['effective_from' => 'Harga baru harus berlaku setelah waktu tiket yang sudah disahkan.']);
                }
                if (PriceSchedule::query()->where('effective_from', $time)->exists()) {
                    throw ValidationException::withMessages(['effective_from' => 'Versi harga pada waktu ini sudah ada.']);
                }
                $price = PriceSchedule::create(['business_date' => $clock->date($time), 'effective_from' => $time,
                    'tbs_buy_price_idr' => $data['tbs_buy_price_idr'], 'sort_buy_price_idr' => $data['sort_buy_price_idr'],
                    'loading_rate_idr' => $data['loading_rate_idr'], 'notes' => $data['notes'] ?? null, 'published_by' => $actor->id]);
            }
            $audit = app(AuditService::class);
            $audit->record($actor, 'publish', $price, null, $audit->snapshot($price));

            return $price;
        });
    }
}

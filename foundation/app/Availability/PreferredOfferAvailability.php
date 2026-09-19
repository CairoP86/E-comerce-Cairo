<?php

namespace App\Availability;

use App\Contracts\ProductAvailability;
use App\Models\ProductCommercialSetting;
use App\Models\StockHold;
use App\Models\SupplierProduct;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;

/**
 * Availability from the preferred offer's manual data (V1-B-SCOPE §1-§2). No supplier API is
 * called. resolve() and constrainVisible() express the same rules in PHP and SQL; keep them equal.
 */
class PreferredOfferAvailability implements ProductAvailability
{
    public static function now(): CarbonImmutable
    {
        return now()->toImmutable()->startOfSecond();
    }

    public function forProducts(array $productIds, ?string $holder = null, bool $lock = false): array
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        if (! $productIds) {
            return [];
        }
        $result = array_fill_keys($productIds, Availability::unknown());
        $preferred = ProductCommercialSetting::whereIn('product_id', $productIds)->whereNotNull('preferred_offer_id')->pluck('preferred_offer_id', 'product_id');
        if ($preferred->isEmpty()) {
            return $result;
        }
        $now = self::now();
        $ttl = AvailabilityConfig::ttlMinutes();
        $query = SupplierProduct::whereIn('id', $preferred->values())->with('supplier:id,active')->orderBy('id');
        // Locking the offer row serializes every hold writer for that offer.
        $offers = ($lock ? $query->lockForUpdate() : $query)->get()->keyBy('id');
        $held = StockHold::query()->whereIn('supplier_product_id', $offers->keys())->where('expires_at', '>', $now)
            ->when($holder !== null, fn ($q) => $q->where(fn ($q) => $q->whereNull('holder')->orWhere('holder', '!=', $holder)))
            ->groupBy('supplier_product_id')->selectRaw('supplier_product_id, SUM(quantity) as held')->pluck('held', 'supplier_product_id');
        foreach ($preferred as $productId => $offerId) {
            $offer = $offers->get($offerId);
            $result[(int) $productId] = $offer && $offer->product_id === (int) $productId
                ? self::resolve($offer, (bool) $offer->supplier?->active, (int) ($held[$offerId] ?? 0), $now, $ttl)
                : Availability::unknown();
        }

        return $result;
    }

    public static function resolve(SupplierProduct $offer, bool $supplierActive, int $heldByOthers, CarbonImmutable $now, int $ttlMinutes): Availability
    {
        $observed = $offer->observed_at ? CarbonImmutable::instance($offer->observed_at) : null;
        $make = fn (AvailabilityState $state, ?int $quantity = null) => new Availability($state, $quantity, $observed, $offer->source, $offer->id);
        // Inactive offer or supplier, or an observation in the future: never inferred as available.
        if (! $offer->active || ! $supplierActive || $observed === null || $observed->greaterThan($now)) {
            return $make(AvailabilityState::Unknown);
        }
        // Fresh while strictly younger than the TTL; at exactly TTL minutes it is stale.
        if (! $observed->greaterThan($now->subMinutes($ttlMinutes))) {
            return $make(AvailabilityState::Stale);
        }

        return match ($offer->availability) {
            // Available without a confirmed quantity cannot show an exact number: unknown (D1).
            'available' => match (true) {
                $offer->stock === null => $make(AvailabilityState::Unknown),
                $offer->stock - $heldByOthers > 0 => $make(AvailabilityState::Available, $offer->stock - $heldByOthers),
                default => $make(AvailabilityState::Unavailable, 0),
            },
            // Unavailable with or without an explicit zero: shown as sold out (D2).
            'unavailable' => $make(AvailabilityState::Unavailable, 0),
            default => $make(AvailabilityState::Unknown),
        };
    }

    /** SQL counterpart of resolve(): the product has a fresh preferred offer that is publicly visible. */
    public static function constrainVisible(Builder $query, CarbonImmutable $now, int $ttlMinutes): Builder
    {
        return $query->selectRaw('1')->from('product_commercial_settings as availability_settings')
            ->join('supplier_products as availability_offers', 'availability_offers.id', '=', 'availability_settings.preferred_offer_id')
            ->join('suppliers as availability_suppliers', 'availability_suppliers.id', '=', 'availability_offers.supplier_id')
            ->whereColumn('availability_settings.product_id', 'products.id')
            ->whereColumn('availability_offers.product_id', 'products.id')
            ->where('availability_offers.active', true)
            ->where('availability_suppliers.active', true)
            ->where('availability_offers.observed_at', '>', $now->subMinutes($ttlMinutes))
            ->where('availability_offers.observed_at', '<=', $now)
            ->where(fn ($q) => $q->where(fn ($q) => $q->where('availability_offers.availability', 'available')->whereNotNull('availability_offers.stock'))
                ->orWhere('availability_offers.availability', 'unavailable'));
    }
}

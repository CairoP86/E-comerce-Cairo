<?php

namespace App\Availability;

use App\Contracts\ProductAvailability;
use App\Models\Product;

/**
 * Offer freshness for the admin, per product and summed over the published catalogue. The product
 * list and the operations panel both read it here, so they can never report different numbers.
 */
class CatalogFreshness
{
    public function __construct(private ProductAvailability $availability) {}

    /** Keyed by product id. Internal to the admin: never reaches public pages. */
    public function forProducts(array $ids): array
    {
        $now = PreferredOfferAvailability::now();
        $ttl = AvailabilityConfig::ttlMinutes();

        return collect($this->availability->forProducts($ids))->map(fn ($a) => OfferFreshness::of($a, $now, $ttl))->all();
    }

    /** Among published products, which ones the TTL is about to hide or already hides. */
    public function publishedSummary(): array
    {
        $levels = collect($this->forProducts(Product::where('status', 'published')->where('is_demo', false)->pluck('id')->all()))->countBy('level');

        return ['expired' => $levels->get('expired', 0), 'expiring' => $levels->get('expiring', 0), 'invalid' => $levels->get('invalid', 0) + $levels->get('none', 0)];
    }
}

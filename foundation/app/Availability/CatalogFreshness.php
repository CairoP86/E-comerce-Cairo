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
    private ?array $levels = null;

    /** The product list's filter names, as they appear in its URL, and the levels each one covers. */
    public const FILTERS = ['vencidas' => ['expired'], 'por-vencer' => ['expiring'], 'sin-dato' => ['invalid', 'none']];

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
        $levels = collect($this->publishedLevels())->countBy();

        return ['expired' => $levels->get('expired', 0), 'expiring' => $levels->get('expiring', 0), 'invalid' => $levels->get('invalid', 0) + $levels->get('none', 0)];
    }

    /** The published products behind one number of the summary, so the list can show exactly those. */
    public function publishedIds(string $filter): array
    {
        return array_keys(array_filter($this->publishedLevels(), fn (string $level) => in_array($level, self::FILTERS[$filter], true)));
    }

    /**
     * Level per published, non-demo product, keyed by id. Kept for the request (the class is scoped):
     * the panel, the sidebar counters and the list's filter all ask on the same page.
     */
    private function publishedLevels(): array
    {
        return $this->levels ??= array_map(fn (array $f) => $f['level'], $this->forProducts(Product::where('status', 'published')->where('is_demo', false)->pluck('id')->all()));
    }
}

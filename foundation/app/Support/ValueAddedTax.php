<?php

namespace App\Support;

/**
 * Costa Rican IVA at 13%, already contained in published product prices (owner decision,
 * 2026-09-20; see ADR-004 and ADR-008).
 *
 * This is a display-only breakdown. It never changes a total, is never persisted and is never
 * sent to a payment processor: the authoritative amount stays the one CheckoutService computes.
 * Delivery fees are deliberately outside the calculation until their tax treatment is decided.
 */
class ValueAddedTax
{
    public const RATE_PERCENT = 13;

    /**
     * The IVA already contained in an IVA-inclusive amount: amount × 13/113, in minor units.
     * Integer arithmetic with half-up rounding, so no float ever touches a money value.
     */
    public static function includedIn(int $amountMinor): int
    {
        if ($amountMinor <= 0) {
            return 0;
        }

        return intdiv($amountMinor * self::RATE_PERCENT * 2 + self::RATE_PERCENT + 100, (self::RATE_PERCENT + 100) * 2);
    }

    /** Buyer-facing projection. The base is the product subtotal, never the delivery fee. */
    public static function breakdown(int $productsSubtotalMinor): array
    {
        return [
            'rate_percent' => self::RATE_PERCENT,
            'amount_minor' => self::includedIn($productsSubtotalMinor),
        ];
    }
}

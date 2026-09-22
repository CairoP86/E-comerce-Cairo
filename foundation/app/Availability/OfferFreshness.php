<?php

namespace App\Availability;

use Carbon\CarbonImmutable;

/**
 * How long the preferred offer's observation keeps the product in the public catalogue. Internal
 * only: it tells the operator which offers to observe again before the TTL hides the product.
 *
 * Levels:
 *  - fresh:    observation valid, more than a quarter of the TTL left
 *  - expiring: observation valid, within the last quarter of the TTL
 *  - expired:  past the TTL; the product is already hidden
 *  - invalid:  there is a date, but the offer can never show (inactive offer or supplier,
 *              a future date, or available without a confirmed stock)
 *  - none:     no preferred offer at all
 */
final class OfferFreshness
{
    /** Share of the TTL, at its end, during which an observation is flagged as expiring. */
    public const EXPIRING_SHARE = 0.25;

    public static function of(Availability $availability, CarbonImmutable $now, int $ttlMinutes): array
    {
        if ($availability->offerId === null || $availability->observedAt === null) {
            return self::shape('none', $availability, null, null);
        }
        if ($availability->state === AvailabilityState::Unknown) {
            return self::shape('invalid', $availability, null, null);
        }
        $expires = $availability->observedAt->addMinutes($ttlMinutes);
        // Whole minutes, signed: negative once the observation has expired.
        $remaining = intdiv($expires->getTimestamp() - $now->getTimestamp(), 60);
        $level = match (true) {
            $availability->state === AvailabilityState::Stale => 'expired',
            $remaining <= (int) round($ttlMinutes * self::EXPIRING_SHARE) => 'expiring',
            default => 'fresh',
        };

        return self::shape($level, $availability, $expires, $remaining);
    }

    private static function shape(string $level, Availability $availability, ?CarbonImmutable $expires, ?int $remaining): array
    {
        return [
            'level' => $level,
            'state' => $availability->state->value,
            'quantity' => $availability->quantity,
            'observed_at' => $availability->observedAt?->toIso8601String(),
            'expires_at' => $expires?->toIso8601String(),
            'remaining_minutes' => $remaining,
        ];
    }
}

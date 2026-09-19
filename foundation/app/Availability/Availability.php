<?php

namespace App\Availability;

use Carbon\CarbonImmutable;

/**
 * Availability of one product with provenance. `quantity` is the sellable amount for the
 * viewer: offer stock minus active holds of other carts. Offer data is internal only.
 */
final readonly class Availability
{
    public function __construct(
        public AvailabilityState $state,
        public ?int $quantity,
        public ?CarbonImmutable $observedAt,
        public ?string $source,
        public ?int $offerId,
    ) {}

    public static function unknown(): self
    {
        return new self(AvailabilityState::Unknown, null, null, null, null);
    }

    /** Public projection: state and exact quantity only (V1-B-SCOPE §4). */
    public function toPublic(): array
    {
        return $this->state === AvailabilityState::Available
            ? ['state' => 'available', 'quantity' => $this->quantity]
            : ['state' => 'unavailable', 'quantity' => 0];
    }
}

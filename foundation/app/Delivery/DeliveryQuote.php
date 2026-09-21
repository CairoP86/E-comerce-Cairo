<?php

namespace App\Delivery;

/**
 * One delivery quote: zone, amount charged to the buyer and the rate version it came from.
 * The amount is always decided by the server; the client never supplies it (ADR-008).
 */
final readonly class DeliveryQuote
{
    public function __construct(
        public DeliveryZone $zone,
        public int $amountMinor,
        public bool $free,
        public ?int $freeFromMinor,
        public ?int $missingForFreeMinor,
        public string $currency,
        public int $rateSetId,
        public string $rateSetVersion,
    ) {}

    /** Buyer-facing projection. Never exposes the rate set or internal identifiers. */
    public function toPublic(): array
    {
        return [
            'zone' => $this->zone->value,
            'zone_label' => $this->zone->label(),
            'amount_minor' => $this->amountMinor,
            'free' => $this->free,
            'free_from_minor' => $this->freeFromMinor,
            'missing_for_free_minor' => $this->missingForFreeMinor,
            'currency' => $this->currency,
        ];
    }
}

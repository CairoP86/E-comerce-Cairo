<?php

return [
    'currency' => 'CRC',
    'timezone' => 'America/Costa_Rica',
    // No connection, endpoint or capability is assumed before reviewing official documentation.
    'suppliers' => [
        'eurocomp' => [
            'enabled' => false,
            'transport' => 'api',
            'capabilities' => [],
        ],
    ],
    // V1-B-SCOPE.md. Validated at boot by App\Availability\AvailabilityConfig.
    'availability' => [
        // Minutes an offer observation stays fresh, for manual and supplier data alike.
        // Required, no default: the value differs per environment and the application refuses to boot without it.
        'ttl_minutes' => env('COMMERCE_AVAILABILITY_TTL_MINUTES'),
        // Local cart hold duration (V1-B-SCOPE §5: one hour). Internal only, never a supplier reservation.
        'hold_minutes' => env('COMMERCE_CART_HOLD_MINUTES', 60),
    ],
];

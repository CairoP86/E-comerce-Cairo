<?php

namespace App\Contracts;

use App\Availability\Availability;

interface ProductAvailability
{
    /**
     * Resolve availability for products. Holds owned by $holder do not reduce the quantity it sees.
     * With $lock, the offer rows are locked for update; call inside a transaction.
     *
     * @param  array<int>  $productIds
     * @return array<int, Availability> keyed by product id
     */
    public function forProducts(array $productIds, ?string $holder = null, bool $lock = false): array;
}

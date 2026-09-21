<?php

namespace App\Delivery;

use RuntimeException;

/** Delivery cannot be quoted for this destination, currency or configuration. Message is buyer-safe. */
class DeliveryUnavailable extends RuntimeException {}

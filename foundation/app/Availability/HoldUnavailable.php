<?php

namespace App\Availability;

use RuntimeException;

/** The requested local hold cannot be granted. The message is safe to show to the buyer. */
class HoldUnavailable extends RuntimeException {}

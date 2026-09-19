<?php

namespace App\Availability;

final class AvailabilityConfig
{
    public const TTL_ENV = 'COMMERCE_AVAILABILITY_TTL_MINUTES';

    public const HOLD_ENV = 'COMMERCE_CART_HOLD_MINUTES';

    public static function assertValid(mixed $ttl, mixed $hold): void
    {
        self::minutes($ttl, self::TTL_ENV);
        self::minutes($hold, self::HOLD_ENV);
    }

    public static function ttlMinutes(): int
    {
        return self::minutes(config('commerce.availability.ttl_minutes'), self::TTL_ENV);
    }

    public static function holdMinutes(): int
    {
        return self::minutes(config('commerce.availability.hold_minutes'), self::HOLD_ENV);
    }

    /**
     * Composer runs package:discover before any environment file exists (fresh clone, CI).
     * That build step is the only command exempt from the boot-time check.
     */
    public static function mustValidate(bool $runningInConsole, array $argv): bool
    {
        return ! ($runningInConsole && ($argv[1] ?? null) === 'package:discover');
    }

    private static function minutes(mixed $value, string $env): int
    {
        $minutes = match (true) {
            is_int($value) => $value,
            is_string($value) && preg_match('/^\d{1,9}$/', trim($value)) === 1 => (int) trim($value),
            default => 0,
        };
        if ($minutes < 1) {
            throw new InvalidAvailabilityConfiguration($env.' must be a positive whole number of minutes. Configure it for this environment; there is no default.');
        }

        return $minutes;
    }
}

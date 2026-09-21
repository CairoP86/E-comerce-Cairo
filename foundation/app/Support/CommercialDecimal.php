<?php

namespace App\Support;

final class CommercialDecimal
{
    public static function multiplier(string $value): int
    {
        if (! preg_match('/^(?:[0-9]{1,3})(?:\.[0-9]{1,4})?$/D', $value)) {
            throw new \InvalidArgumentException('Invalid multiplier');
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $units = (int) $whole * 10000 + (int) str_pad($fraction, 4, '0');
        if ($units < 1 || $units > 1000000) {
            throw new \InvalidArgumentException('Multiplier outside range');
        }

        return $units;
    }

    public static function format(int $units): string
    {
        return intdiv($units, 10000).'.'.str_pad((string) ($units % 10000), 4, '0', STR_PAD_LEFT);
    }

    public static function suggested(int $cost, int $units): int
    {
        return intdiv($cost * $units + 5000, 10000);
    }

    /** Rounds minor units to the nearest whole unit, half up: 60682440 becomes 60682400. */
    public static function wholeUnits(int $minor): int
    {
        return intdiv($minor + 50, 100) * 100;
    }
}

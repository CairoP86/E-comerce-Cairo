<?php

namespace App\Support;

class CostaRicaTerritories
{
    public static function all(): array
    {
        return json_decode(file_get_contents(database_path('data/cr-territories-2026.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    public static function find(string $province, string $canton, string $district): ?array
    {
        foreach (self::all() as $row) {
            if ($row['province_code'] === $province && $row['canton_code'] === $canton && $row['code'] === $district) {
                return $row;
            }
        }

        return null;
    }
}

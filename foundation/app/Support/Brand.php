<?php

namespace App\Support;

final class Brand
{
    /** Initials of the first two words, upper case: "TECH COMMERCE" gives "TC", "Nodal" gives "N". */
    public static function markFor(string $name): string
    {
        $words = array_slice(preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY), 0, 2);

        return mb_strtoupper(implode('', array_map(fn (string $word) => mb_substr($word, 0, 1), $words)));
    }
}

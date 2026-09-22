<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Short order numbers that can be read out over the phone: TC-AAMMDD-NNN, the day being the
 * Costa Rican calendar day and NNN its running count. Orders placed before this format keep their
 * hexadecimal number; the two shapes cannot collide and the unique index still guards both.
 */
final class OrderNumber
{
    public const TIMEZONE = 'America/Costa_Rica';

    /**
     * Must run inside the transaction that creates the order. The day's row is locked until that
     * transaction ends, so concurrent checkouts wait for each other, and a failed order gives its
     * number back when it rolls back: the day's sequence has no gaps.
     */
    public static function next(): string
    {
        $day = now(self::TIMEZONE)->format('ymd');
        // Creates the day's row once; a concurrent creator is simply ignored instead of failing.
        DB::table('order_sequences')->insertOrIgnore(['day' => $day, 'last_value' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $last = DB::table('order_sequences')->where('day', $day)->lockForUpdate()->value('last_value');
        $value = $last + 1;
        DB::table('order_sequences')->where('day', $day)->update(['last_value' => $value, 'updated_at' => now()]);

        return 'TC-'.$day.'-'.str_pad((string) $value, 3, '0', STR_PAD_LEFT);
    }
}

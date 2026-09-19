<?php

namespace App\Support;

use Illuminate\Support\Str;

/** Identifies the session cart that owns local holds. Only a hash reaches the database. */
final class CartHolder
{
    private const KEY = 'cart_holder';

    public static function current(): ?string
    {
        $token = session(self::KEY);

        return is_string($token) ? hash('sha256', $token) : null;
    }

    public static function ensure(): string
    {
        if (! is_string(session(self::KEY))) {
            session()->put(self::KEY, Str::random(64));
        }

        return self::current();
    }
}

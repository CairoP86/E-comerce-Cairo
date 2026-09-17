<?php

namespace App\Support;

use Closure;
use Illuminate\Validation\Rules\Password;

class PasswordRequirements
{
    public static function rules(): array
    {
        return ['required', 'string', 'confirmed', Password::min(12)->letters()->numbers(),
            // bcrypt's limit is bytes, not characters; reject rather than silently truncate.
            function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && strlen($value) > 72) {
                    $fail('La contraseña es demasiado larga. Usa hasta 72 bytes; los caracteres especiales pueden ocupar más de uno.');
                }
            },
        ];
    }
}

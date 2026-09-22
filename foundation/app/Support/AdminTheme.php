<?php

namespace App\Support;

/**
 * Which pages run on the light theme. The storefront keeps the dark identity; the private area and
 * the doors into it are light. Decided on the server so the first paint is already the right colour.
 */
class AdminTheme
{
    /** The header and footer stay dark everywhere, as one frame around both themes. */
    public const FRAME_BG = '#10233f';

    public const FRAME_TEXT = '#f6f9ff';

    public const FRAME_MUTED = '#a9b6cc';

    private const LIGHT_PREFIXES = ['admin/', 'account/'];

    public static function isLight(string $component): bool
    {
        foreach (self::LIGHT_PREFIXES as $prefix) {
            if (str_starts_with($component, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public static function bodyClass(string $component): string
    {
        return self::isLight($component) ? 'theme-light' : '';
    }
}

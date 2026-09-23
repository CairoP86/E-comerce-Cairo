<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The storefront's palette, checked the way the admin's is: every pair the identity relies on has
 * to stay readable. The storefront keeps its own tokens, so it gets its own guard — if somebody
 * lightens one of them later, this fails instead of the customer squinting.
 */
class StorefrontContrastTest extends TestCase
{
    /** Relative luminance and contrast ratio as WCAG 2.1 defines them. */
    private function ratio(string $a, string $b): float
    {
        $luminance = function (string $hex): float {
            $channels = array_map(function (string $pair): float {
                $value = hexdec($pair) / 255;

                return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
            }, str_split(substr(ltrim($hex, '#'), 0, 6), 2));

            return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
        };
        $first = $luminance($a);
        $second = $luminance($b);

        return (max($first, $second) + 0.05) / (min($first, $second) + 0.05);
    }

    private function tokens(): array
    {
        $css = file_get_contents(resource_path('css/storefront-tokens.css'));
        preg_match_all('/(--color-[a-z-]+)\s*:\s*(#[0-9a-fA-F]{6,8})/', $css, $matches, PREG_SET_ORDER);
        $tokens = [];
        foreach ($matches as $match) {
            $tokens[$match[1]] = strtolower($match[2]);
        }

        return $tokens;
    }

    public function test_the_storefront_palette_meets_aa_on_light_and_on_dark(): void
    {
        $t = $this->tokens();
        // [foreground, background, minimum]: 4.5 for text, 3.0 for borders and focus rings.
        $pairs = [
            ['--color-text', '--color-background', 4.5], ['--color-text', '--color-surface', 4.5], ['--color-text', '--color-surface-blue', 4.5],
            ['--color-muted', '--color-background', 4.5], ['--color-muted', '--color-surface', 4.5], ['--color-muted', '--color-surface-blue', 4.5],
            ['--color-primary', '--color-background', 4.5], ['--color-primary', '--color-surface', 4.5],
            ['--color-on-primary', '--color-primary', 4.5], ['--color-on-primary', '--color-primary-hover', 4.5],
            // The dark half of the identity: the home hero and the footer.
            ['--color-on-dark', '--color-navy', 4.5], ['--color-on-dark', '--color-navy-raised', 4.5],
            ['--color-muted-on-dark', '--color-navy', 4.5], ['--color-muted-on-dark', '--color-navy-raised', 4.5],
            ['--color-highlight-on-dark', '--color-navy', 4.5],
            // Stock, sold out, hold and errors: each reads on its own tint.
            ['--color-stock-text', '--color-stock-background', 4.5], ['--color-soldout-text', '--color-soldout-background', 4.5],
            ['--color-hold-text', '--color-hold-background', 4.5], ['--color-error-text', '--color-error-background', 4.5],
            ['--color-control-border', '--color-background', 3.0], ['--color-control-border', '--color-surface', 3.0],
            ['--color-focus', '--color-background', 3.0], ['--color-focus-on-dark', '--color-navy', 3.0],
            ['--color-border-dark-control', '--color-navy', 3.0],
            // A badge outline has to be visible against the page and against its own tint.
            ['--color-stock-border', '--color-background', 3.0], ['--color-stock-border', '--color-stock-background', 3.0],
            ['--color-soldout-border', '--color-background', 3.0],
            ['--color-hold-border', '--color-background', 3.0], ['--color-hold-border', '--color-hold-background', 3.0],
        ];
        foreach ($pairs as [$front, $back, $minimum]) {
            $this->assertArrayHasKey($front, $t, $front);
            $this->assertArrayHasKey($back, $t, $back);
            $ratio = $this->ratio($t[$front], $t[$back]);
            $this->assertGreaterThanOrEqual($minimum, round($ratio, 2), "$front sobre $back: {$ratio}");
        }
    }
}

<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Support\AdminTheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin runs on a light theme; the storefront keeps its dark one. The class is decided on the
 * server so the first paint is already right, and every colour pair it turns on is checked for
 * contrast here, so nobody can lighten a token later without the suite noticing.
 */
class AdminLightThemeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /** Relative luminance and contrast ratio as WCAG 2.1 defines them. */
    private function ratio(string $a, string $b): float
    {
        $luminance = function (string $hex): float {
            $channels = array_map(function (string $pair): float {
                $value = hexdec($pair) / 255;

                return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
            }, str_split(ltrim($hex, '#'), 2));

            return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
        };
        $first = $luminance($a);
        $second = $luminance($b);

        return (max($first, $second) + 0.05) / (min($first, $second) + 0.05);
    }

    /** The light tokens as the stylesheet declares them, so the test reads what the browser reads. */
    private function tokens(): array
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertMatchesRegularExpression('/body\.theme-light\s*\{/', $css, 'No encontré el bloque de tokens claros.');
        preg_match('/body\.theme-light\s*\{(.*?)\}/s', $css, $block);
        preg_match_all('/(--admin-[a-z-]+)\s*:\s*(#[0-9a-fA-F]{6})/', $block[1], $matches, PREG_SET_ORDER);
        $tokens = [];
        foreach ($matches as $match) {
            $tokens[$match[1]] = strtolower($match[2]);
        }

        return $tokens;
    }

    public function test_every_text_pair_of_the_light_theme_meets_aa(): void
    {
        $t = $this->tokens();
        // [text, background, minimum]: 4.5 for text, 3.0 for borders, rails and icons (WCAG 1.4.11).
        $pairs = [
            ['--admin-text', '--admin-bg', 4.5], ['--admin-text', '--admin-raised', 4.5], ['--admin-text', '--admin-sunken', 4.5],
            ['--admin-muted', '--admin-bg', 4.5], ['--admin-muted', '--admin-raised', 4.5], ['--admin-muted', '--admin-sunken', 4.5],
            ['--admin-label', '--admin-bg', 4.5], ['--admin-label', '--admin-sunken', 4.5],
            ['--admin-accent', '--admin-bg', 4.5], ['--admin-accent', '--admin-raised', 4.5], ['--admin-accent', '--admin-sunken', 4.5],
            ['--admin-on-primary', '--admin-primary', 4.5], ['--admin-on-primary', '--admin-primary-hover', 4.5],
            ['--admin-ok', '--admin-ok-soft', 4.5], ['--admin-warn', '--admin-warn-soft', 4.5],
            ['--admin-turn', '--admin-turn-soft', 4.5], ['--admin-danger', '--admin-danger-soft', 4.5],
            ['--admin-neutral', '--admin-sunken', 4.5], ['--admin-accent', '--admin-accent-soft', 4.5],
            ['--admin-control-border', '--admin-raised', 3.0], ['--admin-control-border', '--admin-bg', 3.0],
            ['--admin-focus', '--admin-bg', 3.0], ['--admin-turn', '--admin-bg', 3.0], ['--admin-danger', '--admin-bg', 3.0],
        ];
        foreach ($pairs as [$front, $back, $minimum]) {
            $this->assertArrayHasKey($front, $t, $front);
            $this->assertArrayHasKey($back, $t, $back);
            $ratio = $this->ratio($t[$front], $t[$back]);
            $this->assertGreaterThanOrEqual($minimum, round($ratio, 2), "$front sobre $back: {$ratio}");
        }
    }

    public function test_the_dark_frame_keeps_its_own_contrast_over_the_light_body(): void
    {
        // The header and footer stay dark on every page; their text is checked against that band.
        $this->assertGreaterThanOrEqual(4.5, $this->ratio(AdminTheme::FRAME_TEXT, AdminTheme::FRAME_BG));
        $this->assertGreaterThanOrEqual(4.5, $this->ratio(AdminTheme::FRAME_MUTED, AdminTheme::FRAME_BG));
        // The seam between the dark band and the light canvas has to be visible on its own.
        $this->assertGreaterThanOrEqual(3.0, $this->ratio(AdminTheme::FRAME_BG, '#f4f6fa'));
    }

    public function test_private_pages_ask_for_the_light_theme_and_the_storefront_does_not(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        foreach (['/admin', '/admin/catalog/products', '/admin/orders', '/account'] as $path) {
            $this->get($path)->assertOk()->assertSee('class="theme-light"', false);
        }
        foreach (['/', '/catalog'] as $path) {
            $this->get($path)->assertOk()->assertDontSee('class="theme-light"', false);
        }
    }

    public function test_the_theme_is_decided_by_the_page_being_rendered(): void
    {
        $this->assertTrue(AdminTheme::isLight('admin/Overview'));
        $this->assertTrue(AdminTheme::isLight('account/Overview'));
        $this->assertFalse(AdminTheme::isLight('catalog/Show'));
        $this->assertFalse(AdminTheme::isLight('Home'));
        $this->assertSame('theme-light', AdminTheme::bodyClass('admin/orders/Show'));
        $this->assertSame('', AdminTheme::bodyClass('checkout/Confirmation'));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The home lists categories with a number on each. The number has to be what the customer finds
 * after clicking it, so it follows the catalogue's own rules: visible products, the default
 * currency, and the subcategories the filter includes.
 */
class HomeCategoriesTest extends TestCase
{
    use RefreshDatabase;

    private array $c = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->c['parent'] = Category::factory()->create(['name' => 'Componentes de PC', 'slug' => 'componentes', 'status' => 'published']);
        $this->c['child'] = Category::factory()->create(['name' => 'Memoria RAM', 'slug' => 'componentes-ram', 'status' => 'published', 'parent_id' => $this->c['parent']->id]);
        $this->c['other'] = Category::factory()->create(['name' => 'Redes', 'slug' => 'redes', 'status' => 'published']);
    }

    private function counts(): array
    {
        return collect($this->get('/')->assertOk()->viewData('page')['props']['categories'])->pluck('products', 'slug')->all();
    }

    private function inCatalogue(string $slug): int
    {
        return count($this->get('/catalog?category='.$slug)->assertOk()->viewData('page')['props']['products']['data']);
    }

    public function test_a_category_counts_the_products_its_own_filter_would_show(): void
    {
        Product::factory()->sellable()->count(2)->create(['category_id' => $this->c['parent']->id, 'status' => 'published', 'currency' => 'CRC']);
        Product::factory()->sellable()->create(['category_id' => $this->c['child']->id, 'status' => 'published', 'currency' => 'CRC']);

        $counts = $this->counts();
        // The catalogue's category filter includes the subcategories, so the number does too.
        $this->assertSame(3, $counts['componentes']);
        $this->assertSame(1, $counts['componentes-ram']);
        $this->assertSame(0, $counts['redes']);
        $this->assertSame($this->inCatalogue('componentes'), $counts['componentes']);
        $this->assertSame($this->inCatalogue('componentes-ram'), $counts['componentes-ram']);
    }

    public function test_what_the_storefront_hides_is_not_counted(): void
    {
        // Published and fresh: counted.
        Product::factory()->sellable()->create(['category_id' => $this->c['other']->id, 'status' => 'published', 'currency' => 'CRC']);
        // A draft, and one whose offer expired: the catalogue hides both, so they add nothing.
        Product::factory()->sellable()->create(['category_id' => $this->c['other']->id, 'status' => 'draft', 'currency' => 'CRC']);
        $ttl = config('commerce.availability.ttl_minutes');
        Product::factory()->sellable(1, ['observed_at' => now()->subMinutes($ttl + 5)])->create(['category_id' => $this->c['other']->id, 'status' => 'published', 'currency' => 'CRC']);

        $this->assertSame(1, $this->counts()['redes']);
        $this->assertSame($this->inCatalogue('redes'), $this->counts()['redes']);
    }

    public function test_the_count_follows_the_currency_the_catalogue_opens_with(): void
    {
        Product::factory()->sellable()->create(['category_id' => $this->c['other']->id, 'status' => 'published', 'currency' => 'CRC']);
        Product::factory()->sellable()->create(['category_id' => $this->c['other']->id, 'status' => 'published', 'currency' => 'USD']);

        // The catalogue opens in colones, so a dollar product is not part of what the visitor lands on.
        $this->assertSame(1, $this->counts()['redes']);
        $this->assertSame($this->inCatalogue('redes'), $this->counts()['redes']);
    }
}

<?php

namespace Tests\Feature;

use App\Availability\AvailabilityConfig;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\SupplierProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StorefrontAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function product(int $stock = 5, array $offer = [], array $attributes = []): Product
    {
        return Product::factory()->sellable($stock, $offer)->create(['status' => 'published', 'published_at' => now(), 'featured' => true, ...$attributes]);
    }

    private function offerOf(Product $product): SupplierProduct
    {
        return SupplierProduct::where('product_id', $product->id)->firstOrFail();
    }

    private function staleObservation(): array
    {
        return ['observed_at' => now()->subMinutes(AvailabilityConfig::ttlMinutes() + 1)];
    }

    public function test_available_product_shows_its_exact_quantity_on_every_public_page(): void
    {
        $product = $this->product(3);
        foreach (['/', '/catalog', '/catalog/'.$product->slug] as $url) {
            $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('availability.'.$product->slug.'.state', 'available')
                ->where('availability.'.$product->slug.'.quantity', 3));
        }
    }

    public function test_sold_out_product_stays_visible_as_unavailable(): void
    {
        $product = $this->product(0, ['availability' => 'unavailable']);
        $this->get('/catalog')->assertInertia(fn (Assert $page) => $page->where('products.total', 1)
            ->where('availability.'.$product->slug, ['state' => 'unavailable', 'quantity' => 0]));
        $this->get('/catalog/'.$product->slug)->assertOk();
    }

    public function test_stale_unknown_and_unconfigured_products_are_hidden_everywhere(): void
    {
        $hidden = [
            $this->product(5, $this->staleObservation()),
            $this->product(5, ['availability' => 'unknown', 'stock' => null]),
            $this->product(5, ['stock' => null]),
            Product::factory()->create(['status' => 'published', 'published_at' => now(), 'featured' => true]),
        ];
        $visible = $this->product();
        foreach ($hidden as $product) {
            $image = ProductImage::factory()->create(['product_id' => $product->id]);
            $this->get('/catalog/'.$product->slug)->assertNotFound();
            $this->get('/catalog-images/'.$image->id)->assertNotFound();
            $this->get('/catalog?'.http_build_query(['q' => $product->sku]))->assertInertia(fn (Assert $page) => $page->where('products.total', 0));
        }
        $this->get('/catalog')->assertInertia(fn (Assert $page) => $page->where('products.total', 1)->where('products.data.0.slug', $visible->slug));
        $this->get('/')->assertInertia(fn (Assert $page) => $page->has('featured', 1)->has('recent', 1)->where('featured.0.slug', $visible->slug));
    }

    public function test_hidden_products_are_excluded_from_related_items(): void
    {
        $product = $this->product();
        $related = $this->product(5, [], ['category_id' => $product->category_id]);
        $this->product(5, $this->staleObservation(), ['category_id' => $product->category_id]);
        $this->get('/catalog/'.$product->slug)->assertInertia(fn (Assert $page) => $page->has('related', 1)->where('related.0.slug', $related->slug));
    }

    public function test_supplier_outage_empties_its_category_without_failing(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $first = $this->product(5, [], ['category_id' => $category->id]);
        $second = $this->product(5, [], ['category_id' => $category->id]);
        $this->get('/catalog?category='.$category->slug)->assertInertia(fn (Assert $page) => $page->where('products.total', 2));
        SupplierProduct::whereIn('product_id', [$first->id, $second->id])->update(['observed_at' => now()->subMinutes(AvailabilityConfig::ttlMinutes() + 30)]);
        $this->get('/catalog?category='.$category->slug)->assertOk()->assertInertia(fn (Assert $page) => $page->where('products.total', 0)->where('activeCategory.name', $category->name));
    }

    public function test_inactive_offer_or_supplier_hides_the_product(): void
    {
        $offerOff = $this->product();
        $this->offerOf($offerOff)->update(['active' => false]);
        $supplierOff = $this->product();
        $this->offerOf($supplierOff)->supplier->update(['active' => false]);
        foreach ([$offerOff, $supplierOff] as $product) {
            $this->get('/catalog/'.$product->slug)->assertNotFound();
        }
    }

    public function test_public_availability_exposes_only_state_and_quantity(): void
    {
        $product = $this->product(4);
        $response = $this->get('/catalog/'.$product->slug)->assertOk();
        $this->assertSame(['state', 'quantity'], array_keys($response->viewData('page')['props']['availability'][$product->slug]));
        foreach (['observed_at', 'offerId', 'supplier_sku', 'supplier_product_id', 'Proveedor de prueba'] as $private) {
            $response->assertDontSee($private, false);
        }
    }
}

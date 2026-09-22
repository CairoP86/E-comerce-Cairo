<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminCatalogOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
    }

    /** A published product whose preferred offer was observed $minutesAgo minutes ago. */
    private function observedProduct(int $minutesAgo, array $attributes = []): Product
    {
        // The factory defaults to demonstration products; these stand for real catalogue items.
        return Product::factory()->sellable(1, ['observed_at' => now()->subMinutes($minutesAgo)])->create(['status' => 'published', 'is_demo' => false, ...$attributes]);
    }

    public function test_the_product_list_tells_which_offers_are_expiring_or_expired(): void
    {
        $ttl = config('commerce.availability.ttl_minutes');
        $fresh = $this->observedProduct(1);
        $expiring = $this->observedProduct((int) ($ttl * 0.9));
        $expired = $this->observedProduct($ttl + 5);

        $this->get('/admin/catalog/products')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where("freshness.{$fresh->id}.level", 'fresh')
            ->where("freshness.{$expiring->id}.level", 'expiring')
            ->where("freshness.{$expired->id}.level", 'expired')
            ->where('freshnessSummary.expiring', 1)
            ->where('freshnessSummary.expired', 1));
    }

    public function test_the_product_detail_carries_its_offer_freshness(): void
    {
        $product = $this->observedProduct(1);
        $this->get("/admin/catalog/products/{$product->id}/edit")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('freshness.level', 'fresh')->has('freshness.expires_at'));
    }

    public function test_demonstration_products_are_hidden_by_default_and_can_be_shown(): void
    {
        $real = $this->observedProduct(1);
        $demo = Product::factory()->create(['status' => 'archived', 'is_demo' => true]);

        $this->get('/admin/catalog/products')->assertInertia(fn (Assert $page) => $page
            ->where('products.total', 1)->where('products.data.0.id', $real->id)->where('hiddenDemo', 1));
        $this->get('/admin/catalog/products?demo=1')->assertInertia(fn (Assert $page) => $page
            ->where('products.total', 2)->where('hiddenDemo', 0));
        // Hidden is not deleted.
        $this->assertModelExists($demo);
    }

    public function test_demonstration_categories_are_flagged_without_being_removed(): void
    {
        $root = Category::factory()->create(['name' => 'Tecnología DEMO', 'slug' => 'demo-tecnologia', 'status' => 'archived']);
        $real = Category::factory()->create(['name' => 'Computadoras', 'slug' => 'computadoras', 'status' => 'published']);

        $entries = collect($this->get('/admin/catalog/categories')->assertOk()->viewData('page')['props']['entries'])->keyBy('id');
        $this->assertTrue($entries[$root->id]['is_demo']);
        $this->assertFalse($entries[$real->id]['is_demo']);
        // Brands never carry the flag: the demo rule belongs to categories.
        $this->assertFalse(collect($this->get('/admin/catalog/brands')->viewData('page')['props']['entries'])->contains('is_demo', true));
    }

    public function test_freshness_never_reaches_the_public_catalog(): void
    {
        $product = $this->observedProduct(1);
        $body = $this->get('/catalog/'.$product->slug)->assertOk()->getContent();
        foreach (['remaining_minutes', 'expires_at', 'freshness'] as $private) {
            $this->assertStringNotContainsString($private, $body);
        }
    }
}

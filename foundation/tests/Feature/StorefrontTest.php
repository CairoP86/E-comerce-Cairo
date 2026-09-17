<?php

namespace Tests\Feature;

use App\Http\Resources\PublicProductResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function product(array $attributes = []): Product
    {
        return Product::factory()->create(['status' => 'published', 'published_at' => now(), ...$attributes]);
    }

    public function test_home_is_public_and_selects_only_published_local_products(): void
    {
        $product = $this->product(['featured' => true, 'previous_price_minor' => 15000000]);
        Product::factory()->create(['featured' => true]);
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Home')
            ->where('auth.user', null)->where('identity.name', 'TECH COMMERCE')
            ->has('featured', 1)->where('featured.0.slug', $product->slug)
            ->has('recent', 1)->has('offers', 1)->has('categories')->has('brands')
            ->missing('suppliers')->missing('cost')->missing('stock'));
        $this->assertGuest();
    }

    public function test_home_and_catalog_render_empty_without_authentication(): void
    {
        $this->get('/')->assertInertia(fn (Assert $page) => $page->has('featured', 0)->has('recent', 0)->has('offers', 0));
        $this->get('/catalog')->assertOk()->assertInertia(fn (Assert $page) => $page->where('products.total', 0));
    }

    public function test_search_by_name_and_sku_treats_wildcards_literally(): void
    {
        $product = $this->product(['name' => 'Laptop Studio', 'sku' => 'DEMO-SEARCH']);
        $this->product(['name' => 'Mouse']);
        foreach (['Laptop', 'DEMO-SEARCH'] as $term) {
            $this->get('/catalog?'.http_build_query(['q' => $term]))->assertInertia(fn (Assert $page) => $page
                ->where('products.total', 1)->where('products.data.0.slug', $product->slug)->where('filters.q', $term));
        }
        foreach (['%', '_', 'nonexistent'] as $term) {
            $this->get('/catalog?'.http_build_query(['q' => $term]))->assertInertia(fn (Assert $page) => $page->where('products.total', 0));
        }
    }

    public function test_category_filter_includes_descendants_but_not_hidden_branches(): void
    {
        $root = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $root->id]);
        $leaf = Category::factory()->create(['parent_id' => $child->id]);
        $hidden = Category::factory()->create(['parent_id' => $root->id, 'status' => 'draft']);
        $this->product(['category_id' => $root->id]);
        $this->product(['category_id' => $child->id]);
        $this->product(['category_id' => $leaf->id]);
        $this->product(['category_id' => $hidden->id]);
        $this->product();
        $this->get('/catalog?category='.$root->slug)->assertInertia(fn (Assert $page) => $page->where('products.total', 3)->where('activeCategory.name', $root->name));
        $this->get('/catalog?category='.$child->slug)->assertInertia(fn (Assert $page) => $page->where('products.total', 2));
        $this->get('/catalog?category='.$hidden->slug)->assertNotFound();
        $this->get('/catalog?category=missing')->assertNotFound();
    }

    public function test_combined_brand_price_editorial_and_featured_filters(): void
    {
        $brand = Brand::factory()->create();
        $product = $this->product(['brand_id' => $brand->id, 'price_minor' => 12345, 'featured' => true, 'previous_price_minor' => 15000]);
        $this->product(['brand_id' => $brand->id, 'price_minor' => 12346]);
        $this->product(['price_minor' => 12345, 'featured' => true]);
        $this->get('/catalog?'.http_build_query(['brand' => $brand->slug, 'min' => '123.45', 'max' => '123.45', 'featured' => '1', 'offers' => '1', 'editorial' => 'demo']))
            ->assertInertia(fn (Assert $page) => $page->where('products.total', 1)->where('products.data.0.slug', $product->slug));
        $this->get('/catalog?editorial=standard')->assertInertia(fn (Assert $page) => $page->where('products.total', 0));
        $this->get('/catalog?brand=missing')->assertNotFound();
    }

    public function test_currency_is_explicit_and_prices_are_not_compared_across_currencies(): void
    {
        $crc = $this->product(['price_minor' => 100000, 'currency' => 'CRC']);
        $usd = $this->product(['price_minor' => 1000, 'currency' => 'USD']);
        $this->get('/catalog?sort=price_asc')->assertInertia(fn (Assert $page) => $page->where('products.total', 1)->where('products.data.0.slug', $crc->slug));
        $this->get('/catalog?currency=USD&max=10.00')->assertInertia(fn (Assert $page) => $page->where('products.total', 1)->where('products.data.0.slug', $usd->slug));
    }

    public function test_supported_sort_orders_and_stable_ties(): void
    {
        $first = $this->product(['name' => 'Alpha', 'price_minor' => 20000, 'published_at' => now()->subDays(2)]);
        $second = $this->product(['name' => 'Beta', 'price_minor' => 10000, 'featured' => true]);
        foreach (['price_asc' => $second, 'price_desc' => $first, 'name_asc' => $first, 'name_desc' => $second, 'newest' => $second, 'featured' => $second] as $sort => $expected) {
            $this->get('/catalog?sort='.$sort)->assertInertia(fn (Assert $page) => $page->where('products.data.0.slug', $expected->slug));
        }
        $third = $this->product(['price_minor' => 10000]);
        $this->get('/catalog?sort=price_asc')->assertInertia(fn (Assert $page) => $page->where('products.data.0.slug', $third->slug));
    }

    public function test_pagination_preserves_only_validated_filters(): void
    {
        $category = Category::factory()->create();
        Product::factory()->count(14)->create(['status' => 'published', 'category_id' => $category->id, 'featured' => true, 'name' => 'Laptop']);
        $url = '/catalog?'.http_build_query(['q' => 'Laptop', 'category' => $category->slug, 'featured' => 1, 'sort' => 'price_asc', 'secret' => 'not-forwarded']);
        $response = $this->get($url)->assertInertia(fn (Assert $page) => $page->has('products.data', 12)->where('products.total', 14)->missing('filters.secret'));
        $next = $response->viewData('page')['props']['products']['next_page_url'];
        $this->assertStringContainsString('q=Laptop', $next);
        $this->assertStringContainsString('featured=1', $next);
        $this->assertStringNotContainsString('secret', $next);
        $this->get($next)->assertInertia(fn (Assert $page) => $page->has('products.data', 2)->where('products.current_page', 2));
    }

    public function test_invalid_filters_are_rejected_without_exposing_internal_queries(): void
    {
        foreach ([['sort' => 'cost'], ['min' => '-1'], ['min' => '1.001'], ['currency' => 'EUR'], ['page' => '0'], ['editorial' => 'stock'], ['min' => '20', 'max' => '10'], ['q' => str_repeat('x', 101)]] as $filters) {
            $this->from('/catalog')->get('/catalog?'.http_build_query($filters))->assertRedirect('/catalog')->assertSessionHasErrors();
        }
    }

    public function test_product_detail_and_related_are_public_and_exclude_self_and_unpublished(): void
    {
        $product = $this->product();
        $image = ProductImage::factory()->create(['product_id' => $product->id]);
        $related = $this->product(['category_id' => $product->category_id]);
        Product::factory()->create(['category_id' => $product->category_id]);
        $this->product();
        $this->get('/catalog/'.$product->slug)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('catalog/Show')->where('auth.user', null)->where('product.sku', $product->sku)
            ->where('product.images.0.id', $image->id)->has('product.specifications', 1)
            ->where('product.warranty', $product->warranty)->has('related', 1)->where('related.0.slug', $related->slug));
        $this->assertGuest();
    }

    public function test_unpublished_products_and_taxonomies_are_invisible_everywhere(): void
    {
        $hidden = Brand::factory()->create(['status' => 'archived']);
        $product = $this->product(['brand_id' => $hidden->id, 'featured' => true]);
        $draft = Product::factory()->create();
        $this->get('/catalog/'.$product->slug)->assertNotFound();
        $this->get('/catalog/'.$draft->slug)->assertNotFound();
        $this->get('/catalog')->assertInertia(fn (Assert $page) => $page->where('products.total', 0));
        $this->get('/')->assertInertia(fn (Assert $page) => $page->has('featured', 0)->has('recent', 0)->has('brands', 1));
    }

    public function test_metadata_is_specific_escaped_and_consistent_with_public_props(): void
    {
        $product = $this->product(['meta_title' => 'Laptop <script>alert(1)</script>', 'meta_description' => 'Descripción "segura"']);
        $url = route('catalog.show', $product->slug);
        $this->get($url)->assertSee('Laptop &lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertInertia(fn (Assert $page) => $page->where('seo.title', $product->meta_title.' · TECH COMMERCE')
                ->where('seo.description', $product->meta_description)->where('seo.url', $url)->where('seo.robots', 'noindex, nofollow'));
        $category = $product->category;
        $category->update(['description' => 'Equipos para crear']);
        $this->get('/catalog?category='.$category->slug.'&q=Laptop')->assertInertia(fn (Assert $page) => $page
            ->where('seo.title', $category->name.' · TECH COMMERCE')->where('seo.description', 'Equipos para crear')
            ->where('seo.url', route('catalog.index', ['category' => $category->slug])));
    }

    public function test_public_allowlist_cannot_leak_future_internal_fields(): void
    {
        $product = $this->product()->load(['category', 'brand', 'images']);
        $product->setAttribute('supplier_cost', 9123);
        $product->setAttribute('supplier_token', 'private');
        $product->setRelation('supplier_products', collect([['secret' => true]]));
        $data = (new PublicProductResource($product))->resolve();
        $expected = ['name', 'sku', 'slug', 'short_description', 'description', 'warranty', 'specifications', 'price_minor', 'previous_price_minor', 'currency', 'featured', 'is_demo', 'meta_title', 'meta_description', 'category', 'brand', 'images'];
        $this->assertEqualsCanonicalizing($expected, array_keys($data));
        $this->assertSame(['name', 'slug'], array_keys($data['category']));
        $this->assertSame(['name', 'slug'], array_keys($data['brand']));
        foreach (['/', '/catalog', '/catalog/'.$product->slug] as $url) {
            $this->get($url)->assertOk()->assertDontSee('supplier_cost')->assertDontSee('supplier_token')->assertDontSee('supplier_products');
        }
    }

    public function test_identity_can_change_without_changing_product_routes(): void
    {
        config(['storefront.name' => 'Otra marca', 'storefront.mark' => 'OM']);
        $product = $this->product();
        $this->get('/catalog/'.$product->slug)->assertInertia(fn (Assert $page) => $page
            ->where('identity.name', 'Otra marca')->where('identity.mark', 'OM')
            ->where('seo.title', $product->name.' · Otra marca')->where('seo.url', route('catalog.show', $product->slug)));
    }
}

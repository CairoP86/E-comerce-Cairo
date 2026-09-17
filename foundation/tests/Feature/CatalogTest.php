<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Database\Seeders\CatalogDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('catalog');
        $this->admin = User::factory()->create(['role' => Role::Admin]);
        $this->actingAs($this->admin);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Laptop demostración', 'sku' => 'OWN-001', 'slug' => 'laptop-demostracion',
            'category_id' => Category::factory()->create()->id, 'brand_id' => Brand::factory()->create()->id,
            'short_description' => 'Descripción breve', 'description' => 'Descripción completa', 'warranty' => 'Garantía de ejemplo',
            'price_minor' => 12345678, 'previous_price_minor' => 14000000, 'currency' => 'CRC', 'featured' => true,
            'specifications' => [['key' => 'ram_gb', 'label' => 'Memoria RAM', 'value' => '16', 'unit' => 'GB', 'group' => 'Memoria']],
            'meta_title' => 'Título SEO', 'meta_description' => 'Descripción SEO',
        ], $overrides);
    }

    public function test_admin_can_create_edit_publish_and_archive_categories_and_brands(): void
    {
        foreach (['categories' => Category::class, 'brands' => Brand::class] as $kind => $model) {
            $data = ['name' => 'Demo', 'slug' => 'demo', 'description' => 'Manual', 'status' => 'draft'];
            $this->post('/admin/catalog/'.$kind, $data)->assertSessionHasNoErrors()->assertRedirect();
            $item = $model::firstOrFail();
            $this->put('/admin/catalog/'.$kind.'/'.$item->id, [...$data, 'name' => 'Editada', 'status' => 'published'])->assertSessionHasNoErrors();
            $this->assertSame('published', $item->fresh()->status);
            $this->put('/admin/catalog/'.$kind.'/'.$item->id, [...$data, 'status' => 'archived'])->assertSessionHasNoErrors();
            $this->assertDatabaseHas($kind, ['id' => $item->id, 'status' => 'archived']);
            $this->delete('/admin/catalog/'.$kind.'/'.$item->id)->assertStatus(405);
            $this->put('/admin/catalog/'.$kind.'/'.$item->id, [...$data, 'status' => 'draft'])->assertSessionHasNoErrors();
            $this->get('/admin/catalog/'.$kind)->assertOk();
        }
        $this->assertSame(8, AuditLog::where('event', 'like', 'catalog.%')->count());
    }

    public function test_subcategories_and_cycle_prevention(): void
    {
        $parent = Category::factory()->create();
        $data = ['name' => 'Laptops', 'slug' => 'laptops', 'parent_id' => $parent->id, 'status' => 'published'];
        $this->post('/admin/catalog/categories', $data)->assertSessionHasNoErrors();
        $child = Category::where('slug', 'laptops')->firstOrFail();
        $this->assertSame($parent->id, $child->parent->id);
        $this->put('/admin/catalog/categories/'.$parent->id, ['name' => $parent->name, 'slug' => $parent->slug, 'parent_id' => (string) $child->id, 'status' => 'published'])->assertSessionHasErrors('parent_id');
        $this->put('/admin/catalog/categories/'.$child->id, [...$data, 'parent_id' => (string) $child->id])->assertSessionHasErrors('parent_id');
        $this->assertSame($parent->id, $child->fresh()->parent_id);
    }

    public function test_taxonomy_slugs_are_unique_even_when_archived(): void
    {
        Category::factory()->create(['slug' => 'reserved', 'status' => 'archived']);
        Brand::factory()->create(['slug' => 'reserved', 'status' => 'archived']);
        foreach (['categories', 'brands'] as $kind) {
            $this->post('/admin/catalog/'.$kind, ['name' => 'Other', 'slug' => 'reserved', 'status' => 'draft'])->assertSessionHasErrors('slug');
        }
    }

    public function test_product_creation_editing_and_specifications(): void
    {
        $data = $this->payload();
        $this->post('/admin/catalog/products', $data)->assertSessionHasNoErrors()->assertRedirect();
        $product = Product::firstOrFail();
        $this->assertSame('draft', $product->status);
        $this->assertSame(12345678, $product->price_minor);
        $this->assertSame($data['specifications'], $product->specifications);
        $this->put('/admin/catalog/products/'.$product->id, [...$data, 'name' => 'Actualizada', 'currency' => 'USD', 'price_minor' => 125099, 'previous_price_minor' => null])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Actualizada', 'price_minor' => 125099, 'currency' => 'USD']);
        $this->get('/admin/catalog/products/'.$product->id.'/edit')->assertOk();
        $this->delete('/admin/catalog/products/'.$product->id)->assertStatus(405);
    }

    public function test_sku_and_slug_are_unique_and_sku_is_normalized(): void
    {
        $data = $this->payload(['sku' => 'lower-123']);
        $this->post('/admin/catalog/products', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['sku' => 'LOWER-123']);
        $this->post('/admin/catalog/products', $data)->assertSessionHasErrors(['sku', 'slug']);
        Product::first()->forceFill(['status' => 'archived'])->save();
        $this->post('/admin/catalog/products', $data)->assertSessionHasErrors(['sku', 'slug']);
    }

    public function test_money_requires_positive_integer_minor_units_supported_currency_and_real_discount(): void
    {
        $data = $this->payload();
        foreach ([0, -1, 12.5, '12.50', 'NaN', 1000000000000] as $price) {
            $this->post('/admin/catalog/products', [...$data, 'price_minor' => $price])->assertSessionHasErrors('price_minor');
        }
        $this->post('/admin/catalog/products', [...$data, 'currency' => 'EUR'])->assertSessionHasErrors('currency');
        $this->post('/admin/catalog/products', [...$data, 'previous_price_minor' => $data['price_minor']])->assertSessionHasErrors('previous_price_minor');
        $this->post('/admin/catalog/products', [...$data, 'previous_price_minor' => -1])->assertSessionHasErrors('previous_price_minor');
        $this->assertDatabaseCount('products', 0);
    }

    public function test_specifications_reject_duplicate_keys_and_unknown_nested_fields(): void
    {
        $data = $this->payload();
        $this->post('/admin/catalog/products', [...$data, 'specifications' => [$data['specifications'][0], $data['specifications'][0]]])->assertSessionHasErrors('specifications.0.key');
        $this->post('/admin/catalog/products', [...$data, 'specifications' => [[...$data['specifications'][0], 'private_cost' => 100]]])->assertSessionHasErrors('specifications.0');
    }

    public function test_mass_assignment_and_unauthorized_administrative_fields_are_blocked(): void
    {
        $data = $this->payload();
        $this->post('/admin/catalog/products', [...$data, 'status' => 'published', 'is_demo' => true, 'cost' => 1, 'supplier_id' => 1])->assertSessionHasErrors(['status', 'is_demo', 'cost', 'supplier_id']);
        $this->post('/admin/catalog/products', [...$data, 'unknown_internal_field' => 'secret'])->assertSessionHasNoErrors();
        $product = Product::firstOrFail();
        $this->assertArrayNotHasKey('unknown_internal_field', $product->getAttributes());
        $this->assertSame('draft', $product->status);
    }

    public function test_customer_and_operator_permissions_for_all_catalog_writes(): void
    {
        $product = Product::factory()->create();
        $image = ProductImage::factory()->create(['product_id' => $product->id]);
        foreach ([Role::Customer, Role::Operator] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach (['products', 'categories', 'brands'] as $path) {
                $this->get('/admin/catalog/'.$path)->assertStatus($role === Role::Operator ? 200 : 403);
                $this->post('/admin/catalog/'.$path, [])->assertForbidden();
            }
            $this->get('/admin/catalog/products/create')->assertForbidden();
            $this->get('/admin/catalog/products/'.$product->id.'/edit')->assertStatus($role === Role::Operator ? 200 : 403);
            $this->put('/admin/catalog/products/'.$product->id, [])->assertForbidden();
            $this->put('/admin/catalog/categories/'.$product->category_id, [])->assertForbidden();
            $this->put('/admin/catalog/brands/'.$product->brand_id, [])->assertForbidden();
            $this->patch('/admin/catalog/products/'.$product->id.'/status', ['status' => 'published'])->assertForbidden();
            $this->post('/admin/catalog/products/'.$product->id.'/images', [])->assertForbidden();
            $this->patch('/admin/catalog/products/'.$product->id.'/images', [])->assertForbidden();
            $this->delete('/admin/catalog/products/'.$product->id.'/images/'.$image->id)->assertForbidden();
        }
    }

    public function test_publication_requires_active_taxonomy_and_an_image(): void
    {
        $product = Product::factory()->create();
        $url = '/admin/catalog/products/'.$product->id.'/status';
        $this->patch($url, ['status' => 'published'])->assertSessionHasErrors('publication');
        ProductImage::factory()->create(['product_id' => $product->id]);
        $product->brand->forceFill(['status' => 'draft'])->save();
        $this->patch($url, ['status' => 'published'])->assertSessionHasErrors('publication');
        $product->brand->forceFill(['status' => 'published'])->save();
        $this->patch($url, ['status' => 'published'])->assertSessionHasNoErrors();
        $this->assertNotNull($product->fresh()->published_at);
    }

    public function test_drafts_archived_products_and_inactive_ancestors_are_invisible_publicly(): void
    {
        $product = Product::factory()->create();
        ProductImage::factory()->create(['product_id' => $product->id]);
        $url = '/admin/catalog/products/'.$product->id.'/status';
        $this->get('/catalog/'.$product->slug)->assertNotFound();
        $this->patch($url, ['status' => 'published'])->assertSessionHasNoErrors();
        $this->get('/catalog/'.$product->slug)->assertOk()->assertInertia(fn (Assert $page) => $page->where('product.sku', $product->sku)->missing('product.status')->missing('product.cost')->missing('product.supplier_products')->missing('product.images.0.path'));
        $this->patch($url, ['status' => 'draft']);
        $this->get('/catalog/'.$product->slug)->assertNotFound();
        $this->patch($url, ['status' => 'archived']);
        $this->patch($url, ['status' => 'published'])->assertSessionHasErrors('publication');
        $this->patch($url, ['status' => 'draft']);
        $this->patch($url, ['status' => 'published']);
        $ancestor = Category::factory()->create(['status' => 'archived']);
        $product->category->update(['parent_id' => $ancestor->id]);
        $this->get('/catalog/'.$product->slug)->assertNotFound();
        $this->get('/catalog')->assertInertia(fn (Assert $page) => $page->has('products.data', 0));
    }

    public function test_multiple_images_order_primary_alt_and_cross_product_protection(): void
    {
        $product = Product::factory()->create();
        $url = '/admin/catalog/products/'.$product->id.'/images';
        $this->post($url, ['files' => [UploadedFile::fake()->image('front.jpg', 100, 100), UploadedFile::fake()->image('back.png', 100, 100)], 'alt' => 'Vista de prueba'])->assertSessionHasNoErrors();
        $images = $product->images()->get();
        $this->assertCount(2, $images);
        Storage::disk('catalog')->assertExists($images[0]->path);
        $this->assertTrue($images[0]->is_primary);
        $rows = [['id' => $images[1]->id, 'alt' => 'Posterior'], ['id' => $images[0]->id, 'alt' => 'Frontal']];
        $this->patch($url, ['images' => $rows, 'primary_id' => $images[1]->id])->assertSessionHasNoErrors();
        $this->assertSame($images[1]->id, $product->images()->first()->id);
        $this->assertSame(1, $product->images()->where('is_primary', true)->count());
        $this->assertSame('Posterior', $images[1]->fresh()->alt);
        $foreign = ProductImage::factory()->create();
        $this->patch($url, ['images' => [['id' => $foreign->id, 'alt' => 'Injected']], 'primary_id' => $foreign->id])->assertSessionHasErrors('images');
        $this->delete($url.'/'.$foreign->id)->assertNotFound();
    }

    public function test_image_upload_validation_and_private_visibility(): void
    {
        $product = Product::factory()->create();
        $url = '/admin/catalog/products/'.$product->id.'/images';
        $this->post($url, ['files' => [UploadedFile::fake()->createWithContent('bad.svg', '<svg onload="alert(1)"></svg>')], 'alt' => 'Invalid'])->assertSessionHasErrors('files.0');
        $this->post($url, ['files' => [UploadedFile::fake()->image('wide.jpg', 4097, 5)], 'alt' => 'Too wide'])->assertSessionHasErrors('files.0');
        $this->post($url, ['files' => [UploadedFile::fake()->image('ok.jpg', 20, 20)], 'alt' => 'Valid'])->assertSessionHasNoErrors();
        $image = $product->images()->first();
        $this->get('/catalog-images/'.$image->id)->assertOk();
        $this->app['auth']->forgetGuards();
        $this->get('/catalog-images/'.$image->id)->assertNotFound();
        $product->forceFill(['status' => 'published'])->save();
        $this->get('/catalog-images/'.$image->id)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $product->forceFill(['status' => 'draft'])->save();
        $this->get('/catalog-images/'.$image->id)->assertNotFound();
    }

    public function test_image_removal_is_soft_and_last_published_image_cannot_be_removed(): void
    {
        $product = Product::factory()->create(['status' => 'published']);
        $image = ProductImage::factory()->create(['product_id' => $product->id]);
        Storage::disk('catalog')->put($image->path, 'demo');
        $url = '/admin/catalog/products/'.$product->id.'/images/'.$image->id;
        $this->delete($url)->assertSessionHasErrors('images');
        $product->forceFill(['status' => 'draft'])->save();
        $this->delete($url)->assertSessionHasNoErrors();
        $this->assertSoftDeleted('product_images', ['id' => $image->id]);
        Storage::disk('catalog')->assertExists($image->path);
    }

    public function test_audit_contains_entity_and_changed_fields_without_commercial_payload(): void
    {
        $data = $this->payload();
        $this->post('/admin/catalog/products', $data)->assertSessionHasNoErrors();
        $product = Product::firstOrFail();
        $this->put('/admin/catalog/products/'.$product->id, [...$data, 'name' => 'Changed'])->assertSessionHasNoErrors();
        $audit = AuditLog::where('event', 'catalog.updated')->firstOrFail();
        $this->assertSame($product->id, $audit->metadata['entity_id']);
        $this->assertSame('products', $audit->metadata['entity_type']);
        $this->assertContains('name', $audit->metadata['changed_fields']);
        $this->assertArrayNotHasKey('price_minor', $audit->metadata);
        $this->assertNull($audit->subject_id);
        $this->assertSame($this->admin->id, $audit->actor_id);
    }

    public function test_seed_is_varied_idempotent_and_clearly_demonstration(): void
    {
        $this->seed(CatalogDemoSeeder::class);
        $this->assertDatabaseCount('products', 20);
        $this->assertDatabaseCount('product_images', 21);
        $this->assertSame(20, Product::where('is_demo', true)->count());
        $this->assertSame(11, Product::distinct()->count('category_id'));
        $first = Product::first();
        $first->update(['name' => 'Edición manual']);
        $this->seed(CatalogDemoSeeder::class);
        $this->assertDatabaseCount('products', 20);
        $this->assertSame('Edición manual', $first->fresh()->name);
    }
}

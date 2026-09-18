<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCommercialSetting;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\CommercialPricing;
use Database\Seeders\CommercialSetupSeeder;
use Database\Seeders\CommercialTaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CommercialTaxonomyTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_branches_are_archived_without_deletion_or_reassigning_products(): void
    {
        $demo = Category::factory()->create(['name' => 'Tecnología DEMO', 'slug' => 'demo-tecnologia']);
        $child = Category::factory()->create(['name' => 'Subcategoría histórica', 'parent_id' => $demo->id]);
        $leaf = Category::factory()->create(['parent_id' => $child->id]);
        $slugMarker = Category::factory()->create(['name' => 'Histórica', 'slug' => 'demo-otra']);
        $unrelated = Category::factory()->create(['name' => 'Democracia', 'slug' => 'democracia']);
        $product = Product::factory()->create(['category_id' => $leaf->id]);
        $before = $product->fresh()->getAttributes();

        $this->seed(CommercialTaxonomySeeder::class);

        foreach ([$demo, $child, $leaf, $slugMarker] as $category) {
            $this->assertSame('archived', $category->fresh()->status);
            $this->assertSame($category->parent_id, $category->fresh()->parent_id);
            $this->assertNotContains($category->id, Category::publicIds());
        }
        $this->assertSame('published', $unrelated->fresh()->status);
        $this->assertSame($before, $product->fresh()->getAttributes());
        $this->assertDatabaseCount('categories', 29);
        $this->assertDatabaseCount('products', 1);
        $this->assertSame(4, AuditLog::where('event', 'category.demo_archived')->count());
    }

    public function test_initial_tree_is_draft_idempotent_and_preserves_existing_roots_and_rules(): void
    {
        $this->seed(CommercialSetupSeeder::class);
        $roots = Category::orderBy('id')->pluck('id', 'slug')->all();
        $rules = DB::table('category_pricing_rules')->orderBy('category_id')->get()->toJson();
        $this->seed(CommercialTaxonomySeeder::class);
        $categories = Category::orderBy('id')->get()->toJson();
        $auditCount = AuditLog::count();
        $this->seed(CommercialTaxonomySeeder::class);

        $this->assertSame($categories, Category::orderBy('id')->get()->toJson());
        $this->assertSame($auditCount, AuditLog::count());
        $this->assertDatabaseCount('categories', 24);
        $this->assertSame(['draft'], Category::pluck('status')->unique()->values()->all());
        $this->assertSame($roots, Category::whereNull('parent_id')->orderBy('id')->pluck('id', 'slug')->all());
        $this->assertSame($rules, DB::table('category_pricing_rules')->orderBy('category_id')->get()->toJson());
        $this->assertSame([], Category::publicIds());
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('supplier_products', 0);
        $components = Category::where('slug', 'computo-componentes')->firstOrFail();
        $this->assertSame($roots['computo'], $components->parent_id);
        $this->assertEqualsCanonicalizing(['Procesadores', 'Memoria RAM', 'Almacenamiento', 'Tarjetas gráficas'], $components->children()->pluck('name')->all());
        $this->assertSame(5, Category::findOrFail($roots['computo'])->children()->count());
        $this->assertSame(6, Category::findOrFail($roots['redes'])->children()->count());
        $this->assertSame(6, Category::findOrFail($roots['perifericos'])->children()->count());
    }

    public function test_all_initial_descendants_inherit_the_root_multiplier_including_ram(): void
    {
        $this->seed(CommercialTaxonomySeeder::class);
        $supplier = Supplier::where('code', 'eurocomp')->firstOrFail();
        foreach (Category::whereNotNull('parent_id')->get() as $category) {
            // Synthetic commerce exists only inside the test transaction.
            $product = Product::factory()->create(['category_id' => $category->id]);
            $offer = new SupplierProduct(['supplier_id' => $supplier->id, 'supplier_sku' => 'TEST-'.$category->id, 'cost_minor' => 10000, 'currency' => 'CRC', 'availability' => 'unknown', 'active' => true, 'observed_at' => now()]);
            $offer->product_id = $product->id;
            $offer->source = 'manual';
            $offer->save();
            $setting = new ProductCommercialSetting(['preferred_offer_id' => $offer->id]);
            $setting->product_id = $product->id;
            $setting->save();
            $expected = str_starts_with($category->slug, 'computo-') ? 14000 : (str_starts_with($category->slug, 'redes-') ? 15000 : 17000);
            $quote = app(CommercialPricing::class)->quote($product);
            $this->assertSame($expected, $quote['suggested_minor'], $category->name);
            $this->assertNull($quote['reason']);
            $this->assertDatabaseMissing('category_pricing_rules', ['category_id' => $category->id]);
            if ($category->name === 'Memoria RAM') {
                $this->assertSame('Componentes', $category->parent->name);
                $this->assertSame('Cómputo', $category->parent->parent->name);
                $this->assertSame('1.4000', $quote['multiplier']);
                $setting->update(['multiplier_units' => 12500]);
                $this->assertSame(12500, app(CommercialPricing::class)->quote($product)['suggested_minor']);
            }
        }
        $this->assertSame(21, Category::whereNotNull('parent_id')->count());
    }
}

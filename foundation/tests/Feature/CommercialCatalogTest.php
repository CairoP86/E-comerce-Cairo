<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderItem;
use App\Models\PricingRule;
use App\Models\Product;
use App\Models\ProductCommercialSetting;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\CommercialPricing;
use App\Support\CommercialDecimal;
use Database\Seeders\CommercialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CommercialCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function admin(): User
    {
        $user = User::factory()->create(['role' => Role::Admin]);
        $this->actingAs($user);

        return $user;
    }

    private function supplier(): Supplier
    {
        return Supplier::create(['name' => 'QA PRIVATE Supplier', 'code' => 'qa-'.Str::lower(Str::random(10)), 'active' => true]);
    }

    private function payload(Supplier $supplier, array $extra = []): array
    {
        return ['supplier_id' => $supplier->id, 'supplier_sku' => 'QA-PRIVATE-SKU', 'reference' => 'QA PRIVATE reference', 'cost_minor' => 123457, 'currency' => 'CRC', 'stock' => 3, 'availability' => 'available', 'observed_at' => now()->subMinute()->toISOString(), 'active' => true, 'notes' => 'QA PRIVATE notes', ...$extra];
    }

    private function offer(Product $product, array $extra = []): SupplierProduct
    {
        $offer = new SupplierProduct($this->payload($this->supplier(), $extra));
        $offer->product_id = $product->id;
        $offer->source = 'manual';
        $offer->save();

        return $offer;
    }

    private function select(Product $product, SupplierProduct $offer, ?string $multiplier = '1.4000'): void
    {
        $this->put('/admin/commercial/products/'.$product->id.'/settings', ['preferred_offer_id' => $offer->id, 'multiplier' => $multiplier])->assertSessionHasNoErrors();
    }

    public function test_setup_is_idempotent_and_creates_no_invented_offers_or_products(): void
    {
        $this->seed(CommercialSetupSeeder::class);
        $this->seed(CommercialSetupSeeder::class);
        $this->assertDatabaseCount('suppliers', 3);
        $this->assertDatabaseCount('pricing_rules', 3);
        $this->assertDatabaseCount('category_pricing_rules', 3);
        $this->assertDatabaseCount('supplier_products', 0);
        $this->assertDatabaseCount('products', 0);
        $this->assertSame([14000, 15000, 17000], PricingRule::orderBy('id')->pluck('multiplier_units')->all());
        $this->assertSame(['draft'], Category::pluck('status')->unique()->values()->all());
    }

    public function test_setup_does_not_reassign_existing_categories(): void
    {
        $category = Category::factory()->create(['slug' => 'redes']);
        $this->seed(CommercialSetupSeeder::class);
        $this->assertDatabaseMissing('category_pricing_rules', ['category_id' => $category->id]);
    }

    public function test_guests_and_customers_cannot_read_or_write_commercial_data(): void
    {
        $product = Product::factory()->create();
        $supplier = $this->supplier();
        foreach (['/suppliers', '/suppliers/'.$supplier->id, '/products/'.$product->id, '/rules'] as $path) {
            $this->get('/admin/commercial'.$path)->assertRedirect('/login');
        }
        $this->actingAs(User::factory()->create(['role' => Role::Customer]));
        foreach (['/suppliers', '/suppliers/'.$supplier->id, '/products/'.$product->id, '/rules'] as $path) {
            $this->get('/admin/commercial'.$path)->assertForbidden();
        }
        $this->post('/admin/commercial/suppliers', [])->assertForbidden();
        $this->post('/admin/commercial/products/'.$product->id.'/offers', $this->payload($supplier))->assertForbidden();
    }

    public function test_operator_can_read_but_cannot_use_any_commercial_write_route(): void
    {
        $product = Product::factory()->create();
        $offer = $this->offer($product);
        $rule = PricingRule::create(['name' => 'QA', 'multiplier_units' => 14000]);
        $this->actingAs(User::factory()->create(['role' => Role::Operator]));
        foreach (['/suppliers', '/suppliers/'.$offer->supplier_id, '/products/'.$product->id, '/rules'] as $path) {
            $this->get('/admin/commercial'.$path)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertInertia(fn (Assert $p) => $p->where('canManage', false));
        }
        foreach ([['post', '/suppliers'], ['put', '/suppliers/'.$offer->supplier_id], ['post', '/products/'.$product->id.'/offers'], ['put', '/products/'.$product->id.'/offers/'.$offer->id], ['put', '/products/'.$product->id.'/settings'], ['post', '/products/'.$product->id.'/apply-price'], ['post', '/rules'], ['put', '/rules/'.$rule->id], ['put', '/category-rule']] as [$method, $path]) {
            $this->{$method}('/admin/commercial'.$path, [])->assertForbidden();
        }
    }

    public function test_admin_creates_updates_and_deactivates_supplier_with_audit(): void
    {
        $this->admin();
        $data = ['name' => 'QA provider', 'code' => 'qa-provider', 'active' => true, 'notes' => 'PRIVATE note'];
        $this->post('/admin/commercial/suppliers', $data)->assertSessionHasNoErrors();
        $supplier = Supplier::firstOrFail();
        $this->put('/admin/commercial/suppliers/'.$supplier->id, [...$data, 'active' => false])->assertSessionHasNoErrors();
        $this->assertFalse($supplier->fresh()->active);
        $this->assertDatabaseHas('audit_logs', ['event' => 'supplier.created']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'supplier.updated']);
        $this->assertStringNotContainsString('PRIVATE note', AuditLog::all()->toJson());
    }

    public function test_admin_records_multiple_manual_offers_without_changing_price(): void
    {
        $this->admin();
        $p = Product::factory()->create(['price_minor' => 500000]);
        foreach (range(1, 3) as $i) {
            $this->post('/admin/commercial/products/'.$p->id.'/offers', $this->payload($this->supplier()))->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('supplier_products', 3);
        $offer = SupplierProduct::firstOrFail();
        $this->assertSame('QA-PRIVATE-SKU', $offer->supplier_sku);
        $this->assertSame(123457, $offer->cost_minor);
        $this->assertSame('manual', $offer->source);
        $this->assertSame('CRC', $offer->currency);
        $this->assertSame(3, $offer->stock);
        $this->assertSame('available', $offer->availability);
        $this->assertSame(500000, $p->fresh()->price_minor);
        $this->assertDatabaseCount('product_commercial_settings', 0);
    }

    public function test_offer_validation_rejects_invalid_cost_currency_stock_date_and_injected_fields(): void
    {
        $this->admin();
        $p = Product::factory()->create();
        $supplier = $this->supplier();
        foreach ([['cost_minor' => -1], ['cost_minor' => '1.5'], ['cost_minor' => 1000000000000], ['currency' => 'EUR'], ['stock' => -1], ['stock' => 0], ['availability' => 'unknown'], ['observed_at' => now()->addDay()->toISOString()], ['source' => 'api'], ['product_id' => 99], ['price_minor' => 1]] as $extra) {
            $this->post('/admin/commercial/products/'.$p->id.'/offers', $this->payload($supplier, $extra))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('supplier_products', 0);
        $this->assertNull(session()->getOldInput('cost_minor'));
    }

    public function test_unknown_cost_and_availability_are_not_invented_and_sku_is_unique_per_supplier(): void
    {
        $this->admin();
        $p = Product::factory()->create();
        $supplier = $this->supplier();
        $data = $this->payload($supplier, ['cost_minor' => null, 'stock' => null, 'availability' => 'unknown']);
        $this->post('/admin/commercial/products/'.$p->id.'/offers', $data)->assertSessionHasNoErrors();
        $offer = SupplierProduct::firstOrFail();
        $this->select($p, $offer);
        $this->assertNull(app(CommercialPricing::class)->quote($p)['suggested_minor']);
        $this->post('/admin/commercial/products/'.$p->id.'/offers', $data)->assertSessionHasErrors('supplier_sku');
    }

    public function test_preference_is_explicit_and_rejects_foreign_or_inactive_offers(): void
    {
        $this->admin();
        $p = Product::factory()->create();
        $offer = $this->offer($p);
        $this->offer($p, ['cost_minor' => 1]);
        $this->assertNull(app(CommercialPricing::class)->quote($p)['preferred_offer_id']);
        $this->select($p, $offer);
        $this->assertSame($offer->id, app(CommercialPricing::class)->quote($p)['preferred_offer_id']);
        $foreign = $this->offer(Product::factory()->create());
        $this->put('/admin/commercial/products/'.$p->id.'/settings', ['preferred_offer_id' => $foreign->id])->assertSessionHasErrors('preferred_offer_id');
        $this->put('/admin/commercial/products/'.$p->id.'/offers/'.$foreign->id, $this->payload($foreign->supplier))->assertNotFound();
        $offer->update(['active' => false]);
        $this->put('/admin/commercial/products/'.$p->id.'/settings', ['preferred_offer_id' => $offer->id])->assertSessionHasErrors('preferred_offer_id');
        $this->assertNull(app(CommercialPricing::class)->quote($p)['suggested_minor']);
    }

    public function test_category_ancestor_rule_and_product_override_have_explicit_priority(): void
    {
        $this->admin();
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);
        $p = Product::factory()->create(['category_id' => $child->id]);
        $offer = $this->offer($p, ['cost_minor' => 10000]);
        $rule = PricingRule::create(['name' => 'QA root', 'multiplier_units' => 14000]);
        $this->put('/admin/commercial/category-rule', ['category_id' => $parent->id, 'pricing_rule_id' => $rule->id])->assertSessionHasNoErrors();
        $this->select($p, $offer, null);
        $this->assertSame(14000, app(CommercialPricing::class)->quote($p)['suggested_minor']);
        $near = PricingRule::create(['name' => 'QA child', 'multiplier_units' => 15000]);
        $this->put('/admin/commercial/category-rule', ['category_id' => $child->id, 'pricing_rule_id' => $near->id])->assertSessionHasNoErrors();
        $this->assertSame(15000, app(CommercialPricing::class)->quote($p)['suggested_minor']);
        $this->select($p, $offer, '1.25');
        $this->assertSame(12500, app(CommercialPricing::class)->quote($p)['suggested_minor']);
        $this->assertSame(12500000, $p->fresh()->price_minor);
    }

    public function test_fixed_decimal_precision_rounding_and_bounds(): void
    {
        $this->assertSame(14000, CommercialDecimal::multiplier('1.40'));
        $this->assertSame(1, CommercialDecimal::multiplier('0.0001'));
        $this->assertSame('1.4000', CommercialDecimal::format(14000));
        $this->assertSame(172840, CommercialDecimal::suggested(123457, 14000));
        $this->assertSame(2, CommercialDecimal::suggested(1, 15000));
        $this->assertSame(99999999999900, CommercialDecimal::suggested(999999999999, 1000000));
        foreach (['0', '-1', '100.0001', '1.23456', '1e2', '1,4', 'NaN'] as $invalid) {
            try {
                CommercialDecimal::multiplier($invalid);
                $this->fail('Accepted invalid multiplier');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_cost_and_rule_changes_do_not_publish_and_stale_apply_is_rejected(): void
    {
        $this->admin();
        $p = Product::factory()->create(['price_minor' => 500000]);
        $offer = $this->offer($p);
        $this->select($p, $offer);
        $quote = app(CommercialPricing::class)->quote($p);
        $this->put('/admin/commercial/products/'.$p->id.'/offers/'.$offer->id, $this->payload($offer->supplier, ['cost_minor' => 200000]))->assertSessionHasNoErrors();
        $this->assertSame(500000, $p->fresh()->price_minor);
        $this->post('/admin/commercial/products/'.$p->id.'/apply-price', ['revision' => $quote['revision']])->assertSessionHasErrors('commercial');
        $quote = app(CommercialPricing::class)->quote($p->fresh());
        $this->assertSame(280000, $quote['suggested_minor']);
        $this->post('/admin/commercial/products/'.$p->id.'/apply-price', ['revision' => $quote['revision']])->assertSessionHasNoErrors();
        $this->assertSame(280000, $p->fresh()->price_minor);
        $this->assertDatabaseHas('audit_logs', ['event' => 'commercial.price_applied']);
        $json = AuditLog::all()->toJson();
        foreach (['123457', '200000', '280000', 'QA-PRIVATE-SKU', 'QA PRIVATE notes'] as $private) {
            $this->assertStringNotContainsString($private, $json);
        }
    }

    public function test_rule_edit_requires_new_quote_and_does_not_publish(): void
    {
        $this->admin();
        $p = Product::factory()->create();
        $offer = $this->offer($p, ['cost_minor' => 10000]);
        $this->post('/admin/commercial/rules', ['name' => 'QA rule', 'multiplier' => '1.4'])->assertSessionHasNoErrors();
        $rule = PricingRule::firstOrFail();
        $this->put('/admin/commercial/category-rule', ['category_id' => $p->category_id, 'pricing_rule_id' => $rule->id])->assertSessionHasNoErrors();
        $this->select($p, $offer, null);
        $quote = app(CommercialPricing::class)->quote($p);
        $this->put('/admin/commercial/rules/'.$rule->id, ['name' => 'QA rule', 'multiplier' => '1.7'])->assertSessionHasNoErrors();
        $this->post('/admin/commercial/products/'.$p->id.'/apply-price', ['revision' => $quote['revision']])->assertSessionHasErrors('commercial');
        $this->assertSame(17000, app(CommercialPricing::class)->quote($p)['suggested_minor']);
        $this->assertSame(12500000, $p->fresh()->price_minor);
    }

    public function test_currency_mismatch_inactive_supplier_and_price_overflow_block_apply(): void
    {
        $this->admin();
        $p = Product::factory()->create();
        $offer = $this->offer($p, ['currency' => 'USD']);
        $this->select($p, $offer);
        $pricing = app(CommercialPricing::class);
        $this->assertNull($pricing->quote($p)['suggested_minor']);
        $this->post('/admin/commercial/products/'.$p->id.'/apply-price', ['revision' => $pricing->quote($p)['revision']])->assertSessionHasErrors('commercial');
        $offer->update(['currency' => 'CRC', 'cost_minor' => 999999999999]);
        $this->assertNull($pricing->quote($p)['suggested_minor']);
        $offer->update(['cost_minor' => 10000]);
        $offer->supplier->update(['active' => false]);
        $this->assertNull($pricing->quote($p)['suggested_minor']);
    }

    public function test_public_catalog_cart_checkout_and_order_never_expose_offers_or_use_cost(): void
    {
        $this->admin();
        $p = Product::factory()->create(['status' => 'published', 'price_minor' => 500000]);
        $offer = $this->offer($p);
        $this->offer($p, ['cost_minor' => 1]);
        $this->select($p, $offer);
        auth()->logout();
        $this->get('/catalog')->assertInertia(fn (Assert $a) => $a->where('products.total', 1)->missing('products.data.0.cost_minor')->missing('products.data.0.supplier_id')->missing('products.data.0.multiplier'));
        $this->post('/cart/items', ['mutation_id' => (string) Str::uuid(), 'revision' => 0, 'product_slug' => $p->slug, 'quantity' => 2])->assertSessionHasNoErrors();
        $this->get('/checkout')->assertInertia(fn (Assert $a) => $a->where('review.total_minor', 1000000));
        foreach (['/', '/catalog', '/catalog/'.$p->slug, '/cart', '/checkout'] as $path) {
            $response = $this->get($path)->assertOk();
            foreach (['cost_minor', 'multiplier_units', 'preferred_offer_id', 'supplier_sku', 'QA PRIVATE', 'QA-PRIVATE-SKU'] as $private) {
                $response->assertDontSee($private, false);
            }
        }
        $this->post('/checkout', ['token' => session('checkout_review.token'), 'first_name' => 'QA', 'last_name' => 'Test', 'email' => 'qa@example.test', 'phone' => '+50688887777', 'province_code' => '1', 'canton_code' => '101', 'district_code' => '10101', 'exact_address' => 'Direccion ficticia QA, casa de pruebas.', 'additional' => ''])->assertSessionHasNoErrors();
        $order = Order::firstOrFail();
        $snapshot = $order->publicSummary();
        $offer->update(['cost_minor' => 100000]);
        $actor = $this->admin();
        app(CommercialPricing::class)->apply($p, app(CommercialPricing::class)->quote($p)['revision'], $actor->id);
        $this->assertSame($snapshot, $order->fresh()->publicSummary());
        $this->assertSame(1000000, $order->total_minor);
    }

    public function test_archiving_demo_is_non_destructive_and_preserves_real_products(): void
    {
        $demo = Product::factory()->create(['status' => 'published', 'is_demo' => true]);
        $real = Product::factory()->create(['status' => 'published', 'is_demo' => false]);
        $this->artisan('catalog:archive-demo')->assertSuccessful();
        $this->artisan('catalog:archive-demo')->assertSuccessful();
        $this->assertSame('archived', $demo->fresh()->status);
        $this->assertSame('published', $real->fresh()->status);
        $this->assertDatabaseCount('products', 2);
        $this->get('/catalog')->assertInertia(fn (Assert $a) => $a->where('products.total', 1)->where('products.data.0.slug', $real->slug));
    }

    public function test_mass_assignment_lists_protect_commercial_and_order_ownership(): void
    {
        $this->assertFalse((new SupplierProduct)->isFillable('product_id'));
        $this->assertFalse((new SupplierProduct)->isFillable('source'));
        $this->assertFalse((new ProductCommercialSetting)->isFillable('product_id'));
        $this->assertFalse((new Product)->isFillable('cost_minor'));
        $this->assertFalse((new Order)->isFillable('total_minor'));
        $this->assertFalse((new OrderItem)->isFillable('order_id'));
        $this->assertFalse((new OrderAddress)->isFillable('order_id'));
    }
}

<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function product(array $attributes = []): Product
    {
        return Product::factory()->create(['status' => 'published', ...$attributes]);
    }

    private function mutation(array $extra = []): array
    {
        return ['mutation_id' => (string) Str::uuid(), 'revision' => session('shopping_cart.revision', 0), ...$extra];
    }

    private function add(Product $product, int $quantity = 1): void
    {
        $this->from('/cart')->post('/cart/items', $this->mutation(['product_slug' => $product->slug, 'quantity' => $quantity]))->assertRedirect('/cart')->assertSessionHasNoErrors();
    }

    private function line(): string
    {
        return array_key_first(session('shopping_cart.items'));
    }

    private function cart(): array
    {
        return $this->get('/cart')->assertOk()->viewData('page')['props']['cart'];
    }

    public function test_guest_cart_is_public_empty_and_does_not_create_an_account(): void
    {
        $this->get('/cart')->assertOk()->assertInertia(fn (Assert $page) => $page->component('cart/Index')
            ->where('auth.user', null)->has('cart.lines', 0)->where('cart.units', 0)
            ->where('cart.total_minor', 0)->where('cart.currency', null)->where('cartSummary.units', 0));
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertStringContainsString('no-store', $this->get('/cart')->headers->get('Cache-Control'));
    }

    public function test_guest_adds_repeated_product_to_one_line_and_counter_persists_across_navigation(): void
    {
        $product = $this->product();
        $this->add($product);
        $id = $this->line();
        $this->add($product, 2);
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('cartSummary.units', 3)->where('cartSummary.revision', 2));
        $this->get('/catalog')->assertInertia(fn (Assert $page) => $page->where('cartSummary.units', 3));
        $cart = $this->cart();
        $this->assertCount(1, $cart['lines']);
        $this->assertSame($id, $cart['lines'][0]['id']);
        $this->assertSame(3, $cart['lines'][0]['quantity']);
        $this->assertGuest();
    }

    public function test_update_decrease_remove_and_clear_use_current_cart_only(): void
    {
        $this->add($this->product(), 2);
        $id = $this->line();
        foreach ([7, 1] as $quantity) {
            $this->from('/cart')->patch('/cart/items/'.$id, $this->mutation(['quantity' => $quantity]))->assertSessionHasNoErrors();
            $this->assertSame($quantity, $this->cart()['lines'][0]['quantity']);
        }
        $this->delete('/cart/items/'.$id, $this->mutation())->assertSessionHasNoErrors();
        $this->assertSame([], $this->cart()['lines']);
        $this->assertNull($this->cart()['currency']);
        $this->add($this->product());
        $this->add($this->product());
        $this->delete('/cart', $this->mutation())->assertSessionHasNoErrors();
        $this->assertSame(0, $this->cart()['units']);
        $this->assertNull($this->cart()['currency']);
    }

    public function test_backend_recalculates_current_prices_and_integer_totals(): void
    {
        $first = $this->product(['price_minor' => 12345]);
        $second = $this->product(['price_minor' => 987]);
        $this->add($first, 3);
        $this->add($second, 2);
        $cart = $this->cart();
        $this->assertSame(37035, $cart['lines'][0]['subtotal_minor']);
        $this->assertSame(39009, $cart['subtotal_minor']);
        $this->assertSame(39009, $cart['total_minor']);
        $first->update(['price_minor' => 13001]);
        $cart = $this->cart();
        $this->assertSame(13001, $cart['lines'][0]['unit_price_minor']);
        $this->assertSame(40977, $cart['total_minor']);
        $this->assertEqualsCanonicalizing(['product_id', 'quantity'], array_keys(session('shopping_cart.items')[$this->line()]));
    }

    public function test_manipulated_prices_currency_status_and_unknown_fields_are_rejected(): void
    {
        $product = $this->product();
        foreach (['price', 'price_minor', 'previous_price_minor', 'subtotal', 'total', 'currency', 'status', 'user_id', 'cart_id', 'product_id', 'supplier_cost'] as $field) {
            $this->post('/cart/items', $this->mutation(['product_slug' => $product->slug, 'quantity' => 1, $field => 'injected']))->assertSessionHasErrors('cart');
            $this->assertSame([], $this->cart()['lines']);
        }
    }

    public function test_unpublished_archived_or_hidden_taxonomy_products_cannot_be_added(): void
    {
        $draft = $this->product(['status' => 'draft']);
        $archived = $this->product(['status' => 'archived']);
        $categoryHidden = $this->product();
        $categoryHidden->category->forceFill(['status' => 'draft'])->save();
        $brandHidden = $this->product();
        $brandHidden->brand->forceFill(['status' => 'archived'])->save();
        foreach ([$draft->slug, $archived->slug, $categoryHidden->slug, $brandHidden->slug, 'not-a-product'] as $slug) {
            $this->post('/cart/items', $this->mutation(['product_slug' => $slug, 'quantity' => 1]))->assertSessionHasErrors('cart');
        }
        $this->assertSame([], $this->cart()['lines']);
    }

    public function test_invalid_quantities_are_rejected_on_add_and_update(): void
    {
        $product = $this->product();
        $this->add($product);
        $id = $this->line();
        foreach ([-1, 0, 100, 999999999999, 1.5, 'abc', null, [2]] as $quantity) {
            $this->post('/cart/items', $this->mutation(['product_slug' => $product->slug, 'quantity' => $quantity]))->assertSessionHasErrors('quantity');
            $this->patch('/cart/items/'.$id, $this->mutation(['quantity' => $quantity]))->assertSessionHasErrors('quantity');
        }
        $this->assertSame(1, $this->cart()['lines'][0]['quantity']);
    }

    public function test_aggregate_quantity_and_line_limits_are_enforced(): void
    {
        $product = $this->product();
        $this->add($product, 99);
        $this->post('/cart/items', $this->mutation(['product_slug' => $product->slug, 'quantity' => 1]))->assertSessionHasErrors('cart');
        $this->assertSame(99, $this->cart()['units']);
        $items = [];
        foreach (Product::factory()->count(50)->create(['status' => 'published']) as $item) {
            $items[(string) Str::uuid()] = ['product_id' => $item->id, 'quantity' => 1];
        }
        $this->withSession(['shopping_cart' => ['items' => $items, 'currency' => 'CRC', 'revision' => 0, 'mutations' => []]]);
        $this->post('/cart/items', $this->mutation(['product_slug' => $product->slug, 'quantity' => 1]))->assertSessionHasErrors('cart');
        $this->assertCount(50, $this->cart()['lines']);
    }

    public function test_lines_from_another_session_cannot_be_changed_or_removed(): void
    {
        $this->add($this->product());
        $otherLine = $this->line();
        $otherCart = session('shopping_cart');
        $this->app['session.store']->flush();
        $this->app['session.store']->regenerate();
        $this->assertSame([], $this->cart()['lines']);
        $this->patch('/cart/items/'.$otherLine, $this->mutation(['quantity' => 2]))->assertNotFound();
        $this->delete('/cart/items/'.$otherLine, $this->mutation())->assertNotFound();
        $this->patch('/cart/items/123', $this->mutation(['quantity' => 2]))->assertNotFound();
        $this->assertSame([], $this->cart()['lines']);
        $this->withSession(['shopping_cart' => $otherCart]);
        $this->assertSame(1, $this->cart()['units']);
    }

    public function test_cart_is_single_currency_and_resets_currency_after_emptying(): void
    {
        $this->add($this->product(['currency' => 'CRC']));
        $usd = $this->product(['currency' => 'USD', 'price_minor' => 12345]);
        $this->post('/cart/items', $this->mutation(['product_slug' => $usd->slug, 'quantity' => 1]))->assertSessionHasErrors('cart');
        $this->assertSame('CRC', $this->cart()['currency']);
        $this->delete('/cart', $this->mutation());
        $this->add($usd, 2);
        $this->assertSame('USD', $this->cart()['currency']);
        $this->assertSame(24690, $this->cart()['total_minor']);
    }

    public function test_currency_change_blocks_line_and_provisional_total_without_mixing_money(): void
    {
        $product = $this->product(['price_minor' => 100]);
        $this->add($product, 2);
        $id = $this->line();
        $this->add($this->product(['price_minor' => 300]));
        $product->update(['currency' => 'USD']);
        $cart = $this->cart();
        $this->assertTrue($cart['has_unavailable']);
        $this->assertNull($cart['total_minor']);
        $this->assertSame(300, $cart['subtotal_minor']);
        $this->assertSame('currency_changed', $cart['lines'][0]['reason']);
        $this->assertNull($cart['lines'][0]['unit_price_minor']);
        $this->patch('/cart/items/'.$id, $this->mutation(['quantity' => 3]))->assertSessionHasErrors('cart');
        $this->delete('/cart/items/'.$id, $this->mutation())->assertSessionHasNoErrors();
        $this->assertSame(300, $this->cart()['total_minor']);
    }

    public function test_newly_hidden_product_is_explicitly_blocked_without_leaking_its_metadata(): void
    {
        $product = $this->product(['name' => 'Original', 'price_minor' => 1500]);
        ProductImage::factory()->create(['product_id' => $product->id]);
        $this->add($product, 3);
        $id = $this->line();
        $product->forceFill(['status' => 'archived', 'name' => 'PRIVATE NEW NAME'])->save();
        $cart = $this->cart();
        $line = $cart['lines'][0];
        $this->assertFalse($line['available']);
        $this->assertSame('unpublished', $line['reason']);
        $this->assertSame('Producto no disponible', $line['name']);
        $this->assertNull($line['slug']);
        $this->assertNull($line['image']);
        $this->assertNull($line['unit_price_minor']);
        $this->assertNull($line['subtotal_minor']);
        $this->assertNull($cart['total_minor']);
        $this->assertSame(0, $cart['subtotal_minor']);
        $this->get('/cart')->assertDontSee('PRIVATE NEW NAME');
        $this->patch('/cart/items/'.$id, $this->mutation(['quantity' => 1]))->assertSessionHasErrors('cart');
        $this->delete('/cart/items/'.$id, $this->mutation())->assertSessionHasNoErrors();
        $this->assertSame([], $this->cart()['lines']);
    }

    public function test_duplicate_add_is_idempotent_and_reused_key_with_different_data_is_rejected(): void
    {
        $product = $this->product();
        $data = $this->mutation(['product_slug' => $product->slug, 'quantity' => 2]);
        $this->post('/cart/items', $data)->assertSessionHasNoErrors();
        $this->post('/cart/items', $data)->assertSessionHasNoErrors();
        $this->assertSame(2, $this->cart()['units']);
        $this->assertSame(1, $this->cart()['revision']);
        $this->post('/cart/items', [...$data, 'quantity' => 3])->assertSessionHasErrors('cart');
        $this->assertSame(2, $this->cart()['units']);
    }

    public function test_stale_tab_and_old_duplicate_clear_cannot_overwrite_new_content(): void
    {
        $product = $this->product();
        $this->add($product);
        $this->patch('/cart/items/'.$this->line(), $this->mutation(['quantity' => 2, 'revision' => 0]))->assertSessionHasErrors('cart');
        $this->assertSame(1, $this->cart()['units']);
        $clear = $this->mutation();
        $this->delete('/cart', $clear)->assertSessionHasNoErrors();
        $this->add($product, 3);
        $this->delete('/cart', $clear)->assertSessionHasNoErrors();
        $this->assertSame(3, $this->cart()['units']);
        $this->assertTrue(config('session.block'));
    }

    public function test_guest_cart_survives_optional_login_and_logout_clears_it(): void
    {
        $user = User::factory()->create(['password' => 'CartPassword123!']);
        $this->add($this->product(), 2);
        $id = $this->line();
        $this->post('/login', ['email' => $user->email, 'password' => 'CartPassword123!'])->assertRedirect('/account');
        $this->assertAuthenticatedAs($user);
        $this->assertSame($id, $this->cart()['lines'][0]['id']);
        $this->assertSame(2, $this->cart()['units']);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertSame([], $this->cart()['lines']);
    }

    public function test_all_authenticated_roles_can_use_the_same_cart_without_email_verification(): void
    {
        foreach ([Role::Customer, Role::Operator, Role::Admin] as $role) {
            $this->actingAs(User::factory()->unverified()->create(['role' => $role]));
            $this->add($this->product());
            $this->get('/cart')->assertOk();
        }
        $this->assertSame(3, $this->cart()['units']);
    }

    public function test_public_cart_uses_an_explicit_field_allowlist(): void
    {
        $product = $this->product();
        ProductImage::factory()->create(['product_id' => $product->id]);
        $this->add($product);
        $line = $this->cart()['lines'][0];
        $this->assertEqualsCanonicalizing(['id', 'quantity', 'available', 'reason', 'name', 'slug', 'is_demo', 'image', 'unit_price_minor', 'subtotal_minor', 'currency'], array_keys($line));
        $this->assertSame(['url', 'alt'], array_keys($line['image']));
        $this->assertTrue(Str::isUuid($line['id']));
        $this->get('/cart')->assertDontSee('product_id')->assertDontSee('supplier_products')->assertDontSee('mutations')->assertDontSee('shopping_cart');
    }

    public function test_mutations_require_csrf_and_valid_operation_metadata(): void
    {
        $product = $this->product();
        $this->post('/cart/items', ['product_slug' => $product->slug, 'quantity' => 1])->assertSessionHasErrors(['mutation_id', 'revision']);
        $this->app->bind(PreventRequestForgery::class, CartEnforcedCsrf::class);
        $line = (string) Str::uuid();
        $this->post('/cart/items', $this->mutation(['product_slug' => $product->slug, 'quantity' => 1]))->assertStatus(419);
        $this->patch('/cart/items/'.$line, $this->mutation(['quantity' => 1]))->assertStatus(419);
        $this->delete('/cart/items/'.$line, $this->mutation())->assertStatus(419);
        $this->delete('/cart', $this->mutation())->assertStatus(419);
    }
}

class CartEnforcedCsrf extends PreventRequestForgery
{
    protected function runningUnitTests()
    {
        return false;
    }
}

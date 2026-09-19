<?php

namespace Tests\Feature;

use App\Availability\AvailabilityConfig;
use App\Models\Product;
use App\Models\StockHold;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Support\CartHolder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StockHoldTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['commerce.availability.hold_minutes' => 60]);
        $this->freezeSecond();
    }

    private function product(int $stock = 5): Product
    {
        return Product::factory()->sellable($stock)->create(['status' => 'published', 'published_at' => now()]);
    }

    private function mutation(array $extra = []): array
    {
        return ['mutation_id' => (string) Str::uuid(), 'revision' => session('shopping_cart.revision', 0), ...$extra];
    }

    private function add(Product $product, int $quantity = 1)
    {
        return $this->from('/cart')->post('/cart/items', $this->mutation(['product_slug' => $product->slug, 'quantity' => $quantity]));
    }

    private function line(): string
    {
        return array_key_first(session('shopping_cart.items'));
    }

    private function cart(): array
    {
        return $this->get('/cart')->assertOk()->viewData('page')['props']['cart'];
    }

    private function newVisitor(): void
    {
        $this->app['session.store']->flush();
        $this->app['session.store']->regenerate();
    }

    private function hold(Product $product): StockHold
    {
        return StockHold::where('product_id', $product->id)->whereNull('order_id')->sole();
    }

    public function test_adding_creates_a_local_hold_with_the_configured_expiry(): void
    {
        $product = $this->product();
        $this->add($product, 2)->assertSessionHasNoErrors();
        $hold = $this->hold($product);
        $this->assertSame(CartHolder::current(), $hold->holder);
        $this->assertSame(2, $hold->quantity);
        $this->assertTrue($hold->expires_at->equalTo(now()->addMinutes(60)));
        $this->assertSame(now()->addMinutes(60)->toISOString(), $this->cart()['hold_expires_at']);
    }

    public function test_cannot_hold_more_than_the_sellable_quantity(): void
    {
        $product = $this->product(3);
        $this->add($product, 4)->assertSessionHasErrors(['cart' => 'Solo quedan 3 unidades disponibles.']);
        $this->assertDatabaseCount('stock_holds', 0);
        $this->assertSame([], $this->cart()['lines']);
    }

    public function test_two_carts_compete_for_the_last_unit(): void
    {
        $product = $this->product(1);
        $this->add($product)->assertSessionHasNoErrors();
        $this->newVisitor();
        $this->add($product)->assertSessionHasErrors(['cart' => 'Este producto está agotado.']);
        $this->get('/catalog/'.$product->slug)->assertInertia(fn (Assert $page) => $page->where('availability.'.$product->slug, ['state' => 'unavailable', 'quantity' => 0]));
    }

    public function test_other_carts_see_stock_minus_holds_while_the_owner_sees_full_stock(): void
    {
        $product = $this->product(5);
        $this->add($product, 2);
        $this->get('/catalog/'.$product->slug)->assertInertia(fn (Assert $page) => $page->where('availability.'.$product->slug.'.quantity', 5));
        $this->newVisitor();
        $this->get('/catalog/'.$product->slug)->assertInertia(fn (Assert $page) => $page->where('availability.'.$product->slug.'.quantity', 3));
    }

    public function test_expired_hold_blocks_the_line_until_it_is_renewed(): void
    {
        $product = $this->product(1);
        $this->add($product);
        $line = $this->line();
        $this->travel(60)->minutes();
        $cart = $this->cart();
        $this->assertSame('hold_expired', $cart['lines'][0]['reason']);
        $this->assertFalse($cart['lines'][0]['available']);
        $this->assertNull($cart['total_minor']);
        $this->assertNull($cart['hold_expires_at']);
        $this->from('/cart')->patch('/cart/items/'.$line, $this->mutation(['quantity' => 1]))->assertSessionHasNoErrors();
        $this->assertNull($this->cart()['lines'][0]['reason']);
        $this->assertTrue($this->hold($product)->expires_at->equalTo(now()->addMinutes(60)));
    }

    public function test_expired_hold_frees_the_unit_for_other_carts(): void
    {
        $product = $this->product(1);
        $this->add($product);
        $this->newVisitor();
        $this->add($product)->assertSessionHasErrors('cart');
        $this->travel(60)->minutes();
        $this->add($product)->assertSessionHasNoErrors();
    }

    public function test_changing_quantity_or_adding_again_keeps_the_original_expiry(): void
    {
        $product = $this->product(5);
        $this->add($product);
        $expiry = $this->hold($product)->expires_at;
        $this->travel(30)->minutes();
        $this->from('/cart')->patch('/cart/items/'.$this->line(), $this->mutation(['quantity' => 3]))->assertSessionHasNoErrors();
        $this->assertSame(3, $this->hold($product)->quantity);
        $this->assertTrue($this->hold($product)->expires_at->equalTo($expiry));
        $this->add($product)->assertSessionHasNoErrors();
        $this->assertSame(4, $this->hold($product)->quantity);
        $this->assertTrue($this->hold($product)->expires_at->equalTo($expiry));
    }

    public function test_remove_clear_and_logout_release_holds(): void
    {
        $first = $this->product();
        $second = $this->product();
        $this->add($first);
        $this->add($second);
        $this->delete('/cart/items/'.$this->line(), $this->mutation())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('stock_holds', 1);
        $this->delete('/cart', $this->mutation())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('stock_holds', 0);
        $user = User::factory()->create(['password' => 'HoldPassword123!']);
        $this->add($first);
        $this->post('/login', ['email' => $user->email, 'password' => 'HoldPassword123!'])->assertRedirect('/account');
        $this->assertDatabaseCount('stock_holds', 1);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertDatabaseCount('stock_holds', 0);
    }

    public function test_lowered_stock_marks_the_line_out_of_stock_until_the_quantity_fits(): void
    {
        $product = $this->product(5);
        $this->add($product, 3);
        SupplierProduct::where('product_id', $product->id)->update(['stock' => 2]);
        $this->assertSame('out_of_stock', $this->cart()['lines'][0]['reason']);
        $this->from('/cart')->patch('/cart/items/'.$this->line(), $this->mutation(['quantity' => 3]))->assertSessionHasErrors(['cart' => 'Solo quedan 2 unidades disponibles.']);
        $this->from('/cart')->patch('/cart/items/'.$this->line(), $this->mutation(['quantity' => 2]))->assertSessionHasNoErrors();
        $this->assertNull($this->cart()['lines'][0]['reason']);
    }

    public function test_stale_data_hides_a_held_line_without_leaking_it(): void
    {
        $product = $this->product();
        $this->add($product);
        SupplierProduct::where('product_id', $product->id)->update(['observed_at' => now()->subMinutes(AvailabilityConfig::ttlMinutes() + 1)]);
        $line = $this->cart()['lines'][0];
        $this->assertSame('unpublished', $line['reason']);
        $this->assertSame('Producto no disponible', $line['name']);
        $this->assertNull($line['slug']);
    }

    public function test_holds_never_contact_a_supplier(): void
    {
        Http::fake();
        $product = $this->product();
        $this->add($product, 2)->assertSessionHasNoErrors();
        $this->delete('/cart', $this->mutation())->assertSessionHasNoErrors();
        Http::assertNothingSent();
        $this->assertFalse(config('commerce.suppliers.eurocomp.enabled'));
        $this->assertSame([], config('commerce.suppliers.eurocomp.capabilities'));
    }
}

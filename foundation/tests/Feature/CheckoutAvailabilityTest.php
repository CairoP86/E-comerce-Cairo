<?php

namespace Tests\Feature;

use App\Availability\AvailabilityConfig;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockHold;
use App\Models\SupplierProduct;
use Database\Seeders\DeliveryZonesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CheckoutAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DeliveryZonesSeeder::class);
        config(['commerce.availability.hold_minutes' => 60]);
        $this->freezeSecond();
    }

    private function prepare(int $stock = 5, int $quantity = 2): Product
    {
        $product = Product::factory()->sellable($stock)->create(['status' => 'published', 'price_minor' => 1000, 'currency' => 'CRC']);
        $this->post('/cart/items', ['mutation_id' => (string) Str::uuid(), 'revision' => session('shopping_cart.revision', 0), 'product_slug' => $product->slug, 'quantity' => $quantity])->assertSessionHasNoErrors();
        $this->get('/checkout?canton_code=101')->assertOk();

        return $product;
    }

    private function payload(): array
    {
        return ['token' => session('checkout_review.token'), 'first_name' => 'Ana', 'last_name' => 'Prueba', 'email' => 'guest@example.test', 'phone' => '+50688887777', 'province_code' => '1', 'canton_code' => '101', 'district_code' => '10101', 'exact_address' => 'Dirección ficticia para pruebas, casa azul.', 'additional' => ''];
    }

    private function place(): Order
    {
        $response = $this->from('/checkout')->post('/checkout', $this->payload())->assertSessionHasNoErrors()->assertStatus(303);

        return Order::where('number', basename($response->headers->get('Location')))->firstOrFail();
    }

    private function newVisitor(): void
    {
        $this->app['session.store']->flush();
        $this->app['session.store']->regenerate();
    }

    public function test_confirmation_attaches_the_holds_to_the_order_with_the_same_expiry(): void
    {
        $this->prepare();
        $expiry = StockHold::sole()->expires_at;
        $order = $this->place();
        $hold = StockHold::sole();
        $this->assertNull($hold->holder);
        $this->assertSame($order->id, $hold->order_id);
        $this->assertSame(2, $hold->quantity);
        $this->assertTrue($hold->expires_at->equalTo($expiry));
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
    }

    public function test_order_holds_keep_counting_until_expiry_then_release_stock(): void
    {
        $product = $this->prepare(2, 2);
        $order = $this->place();
        $this->newVisitor();
        $this->get('/catalog/'.$product->slug)->assertInertia(fn (Assert $page) => $page->where('availability.'.$product->slug, ['state' => 'unavailable', 'quantity' => 0]));
        $this->travel(60)->minutes();
        $this->get('/catalog/'.$product->slug)->assertInertia(fn (Assert $page) => $page->where('availability.'.$product->slug, ['state' => 'available', 'quantity' => 2]));
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_expired_hold_blocks_review_and_confirmation(): void
    {
        $this->prepare();
        $payload = $this->payload();
        $this->travel(60)->minutes();
        $this->get('/checkout')->assertRedirect('/cart')->assertSessionHasErrors('checkout');
        $this->post('/checkout', $payload)->assertSessionHasErrors('checkout');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_stale_availability_blocks_confirmation(): void
    {
        $product = $this->prepare();
        SupplierProduct::where('product_id', $product->id)->update(['observed_at' => now()->subMinutes(AvailabilityConfig::ttlMinutes() + 1)]);
        $this->post('/checkout', $this->payload())->assertSessionHasErrors('checkout');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_stock_lowered_below_the_held_quantity_blocks_confirmation(): void
    {
        $product = $this->prepare(5, 2);
        SupplierProduct::where('product_id', $product->id)->update(['stock' => 1]);
        $this->post('/checkout', $this->payload())->assertSessionHasErrors('checkout');
        $this->assertDatabaseCount('orders', 0);
        $this->assertNotNull(StockHold::sole()->holder);
    }

    public function test_retried_confirmation_does_not_attach_holds_twice(): void
    {
        $this->prepare();
        $payload = $this->payload();
        $order = $this->place();
        $this->from('/checkout')->post('/checkout', $payload)->assertStatus(303);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('stock_holds', 1);
        $this->assertSame($order->id, StockHold::sole()->order_id);
    }
}

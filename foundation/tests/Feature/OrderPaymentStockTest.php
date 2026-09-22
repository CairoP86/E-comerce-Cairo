<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockHold;
use App\Models\SupplierProduct;
use App\Models\User;
use Database\Seeders\DeliveryZonesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Marking an order paid turns its temporary hold into a permanent sale, or refuses to. */
class OrderPaymentStockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DeliveryZonesSeeder::class);
    }

    private function product(int $stock): Product
    {
        return Product::factory()->sellable($stock)->create(['status' => 'published', 'price_minor' => 50000, 'currency' => 'CRC']);
    }

    private function addToCart(Product $product, int $quantity = 1): void
    {
        $this->post('/cart/items', ['mutation_id' => (string) Str::uuid(), 'revision' => session('shopping_cart.revision', 0), 'product_slug' => $product->slug, 'quantity' => $quantity])->assertSessionHasNoErrors();
    }

    /** Checks out whatever the current visitor has in the cart. */
    private function placeOrder(): Order
    {
        $this->get('/checkout?canton_code=101')->assertOk();
        $response = $this->from('/checkout')->post('/checkout', ['token' => session('checkout_review.token'), 'first_name' => 'Ana', 'last_name' => 'Prueba', 'email' => 'guest@example.test', 'phone' => '+50688887777', 'province_code' => '1', 'canton_code' => '101', 'district_code' => '10101', 'exact_address' => 'Dirección ficticia para pruebas, casa azul.', 'additional' => ''])->assertSessionHasNoErrors()->assertStatus(303);

        return Order::where('number', basename($response->headers->get('Location')))->sole();
    }

    private function orderFor(Product $product, int $quantity = 1): Order
    {
        $this->addToCart($product, $quantity);

        return $this->placeOrder();
    }

    /** A different customer: a fresh session means a different cart holder. */
    private function anotherCustomer(): void
    {
        $this->flushSession();
        auth()->logout();
    }

    private function expireHoldOf(Order $order): void
    {
        StockHold::where('order_id', $order->id)->update(['expires_at' => now()->subMinute()]);
    }

    private function offerOf(Product $product): SupplierProduct
    {
        return SupplierProduct::where('product_id', $product->id)->sole();
    }

    private function pay(Order $order)
    {
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));

        return $this->from('/admin/orders/'.$order->number)->post('/admin/orders/'.$order->number.'/paid');
    }

    private function assertUntouched(Order $order, Product $product, int $stock): void
    {
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
        $this->assertSame($stock, $this->offerOf($product)->fresh()->stock);
    }

    public function test_paying_within_the_hold_turns_it_into_a_permanent_sale(): void
    {
        $product = $this->product(2);
        $order = $this->orderFor($product);
        $this->pay($order)->assertSessionHasNoErrors();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(1, $this->offerOf($product)->fresh()->stock);
        // The hold is retired, not deleted: otherwise the unit would count twice until it expired.
        $hold = StockHold::where('order_id', $order->id)->sole();
        $this->assertTrue($hold->expires_at->lessThanOrEqualTo(now()));
        $audit = AuditLog::where('event', 'order.stock_committed')->sole();
        $this->assertSame(['entity_type' => 'supplier_products', 'entity_id' => $this->offerOf($product)->id, 'quantity' => 1, 'from_stock' => 2, 'to_stock' => 1], $audit->metadata);
    }

    public function test_an_expired_hold_can_still_be_paid_when_nobody_took_the_unit(): void
    {
        $product = $this->product(1);
        $order = $this->orderFor($product);
        $this->expireHoldOf($order);
        $this->pay($order)->assertSessionHasNoErrors();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(0, $this->offerOf($product)->fresh()->stock);
    }

    public function test_a_unit_already_sold_to_another_paid_order_blocks_the_payment(): void
    {
        $product = $this->product(1);
        $first = $this->orderFor($product);
        $this->expireHoldOf($first);
        $this->anotherCustomer();
        $second = $this->orderFor($product);
        $this->pay($second)->assertSessionHasNoErrors();

        $this->pay($first)->assertSessionHasErrors('order');
        $this->assertStringContainsString('no queda stock registrado', session('errors')->first('order'));
        $this->assertStringContainsString('venció', session('errors')->first('order'));
        $this->assertUntouched($first, $product, 0);
    }

    public function test_another_pending_order_inside_its_hold_blocks_the_payment(): void
    {
        $product = $this->product(1);
        $first = $this->orderFor($product);
        $this->expireHoldOf($first);
        $this->anotherCustomer();
        $this->orderFor($product);

        $this->pay($first)->assertSessionHasErrors('order');
        $this->assertStringContainsString('comprometida con otro pedido pendiente', session('errors')->first('order'));
        $this->assertUntouched($first, $product, 1);
    }

    public function test_a_payment_wins_over_another_customers_cart_and_that_checkout_then_fails(): void
    {
        $product = $this->product(1);
        $order = $this->orderFor($product);
        $this->expireHoldOf($order);
        $this->anotherCustomer();
        $this->addToCart($product);
        $cartHold = StockHold::whereNull('order_id')->sole();

        $this->pay($order)->assertSessionHasNoErrors();
        $this->assertSame(0, $this->offerOf($product)->fresh()->stock);
        $this->assertModelMissing($cartHold);
        // The other customer's checkout now refuses instead of selling the same unit twice.
        $this->get('/checkout?canton_code=101')->assertRedirect('/cart')->assertSessionHasErrors('checkout');
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_one_short_product_blocks_the_whole_order_and_changes_nothing(): void
    {
        $plenty = $this->product(5);
        $scarce = $this->product(1);
        $this->addToCart($plenty);
        $this->addToCart($scarce);
        $order = $this->placeOrder();
        $this->expireHoldOf($order);
        $this->offerOf($scarce)->forceFill(['stock' => 0])->save();

        $this->pay($order)->assertSessionHasErrors('order');
        $this->assertUntouched($order, $plenty, 5);
        $this->assertSame(0, $this->offerOf($scarce)->fresh()->stock);
        $this->assertSame(0, AuditLog::where('event', 'order.stock_committed')->count());
    }

    public function test_paying_twice_discounts_the_stock_once(): void
    {
        $product = $this->product(5);
        $order = $this->orderFor($product, 2);
        $this->pay($order)->assertSessionHasNoErrors();
        $this->pay($order)->assertSessionHasNoErrors();

        $this->assertSame(3, $this->offerOf($product)->fresh()->stock);
        $this->assertSame(1, AuditLog::where('event', 'order.stock_committed')->count());
    }

    public function test_an_unknown_stock_blocks_the_payment(): void
    {
        $product = $this->product(1);
        $order = $this->orderFor($product);
        $this->offerOf($product)->forceFill(['stock' => null])->save();

        $this->pay($order)->assertSessionHasErrors('order');
        $this->assertStringContainsString('no tiene stock confirmado', session('errors')->first('order'));
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_a_deactivated_offer_blocks_the_payment(): void
    {
        $product = $this->product(1);
        $order = $this->orderFor($product);
        $this->offerOf($product)->forceFill(['active' => false])->save();

        $this->pay($order)->assertSessionHasErrors('order');
        $this->assertStringContainsString('desactivada', session('errors')->first('order'));
        $this->assertUntouched($order, $product, 1);
    }

    public function test_an_order_placed_before_holds_existed_is_blocked(): void
    {
        $product = $this->product(1);
        $order = $this->orderFor($product);
        // Exactly how an order from before V1-B looks: no hold links it to an offer.
        StockHold::where('order_id', $order->id)->delete();

        $this->pay($order)->assertSessionHasErrors('order');
        $this->assertStringContainsString('anterior a las reservas', session('errors')->first('order'));
        $this->assertUntouched($order, $product, 1);
    }
}

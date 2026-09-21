<?php

namespace Tests\Feature;

use App\Delivery\DeliveryZone;
use App\Models\DeliveryRate;
use App\Models\DeliveryRateSet;
use App\Models\Order;
use App\Models\Product;
use Database\Seeders\DeliveryZonesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DeliveryCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DeliveryZonesSeeder::class);
    }

    /** Puts $quantity units of a $priceMinor product in the cart and opens the checkout. */
    private function prepare(int $priceMinor = 123456, int $quantity = 2, string $currency = 'CRC'): Product
    {
        $product = Product::factory()->sellable()->create(['status' => 'published', 'price_minor' => $priceMinor, 'currency' => $currency]);
        $this->post('/cart/items', ['mutation_id' => (string) Str::uuid(), 'revision' => session('shopping_cart.revision', 0), 'product_slug' => $product->slug, 'quantity' => $quantity])->assertSessionHasNoErrors();

        return $product;
    }

    private function payload(array $extra = []): array
    {
        return ['token' => session('checkout_review.token'), 'first_name' => 'Ana', 'last_name' => 'Prueba', 'email' => 'guest@example.test', 'phone' => '+50688887777', 'province_code' => '1', 'canton_code' => '101', 'district_code' => '10101', 'exact_address' => 'Dirección ficticia para pruebas, casa azul.', 'additional' => '', ...$extra];
    }

    private function review(?string $canton = null): array
    {
        $url = '/checkout'.($canton ? '?canton_code='.$canton : '');

        return $this->get($url)->assertOk()->viewData('page')['props']['review'];
    }

    public function test_the_review_has_no_delivery_until_a_canton_is_chosen(): void
    {
        $this->prepare();
        $review = $this->review();
        $this->assertNull($review['shipping']);
        $this->assertSame(246912, $review['subtotal_minor']);
        $this->assertSame(246912, $review['total_minor']);
    }

    public function test_choosing_a_gam_canton_quotes_the_flat_fee_before_confirming(): void
    {
        $this->prepare();
        $review = $this->review('101');
        $this->assertSame(350000, $review['shipping']['amount_minor']);
        $this->assertFalse($review['shipping']['free']);
        $this->assertSame('Gran Área Metropolitana', $review['shipping']['zone_label']);
        $this->assertSame(596912, $review['total_minor']);
        $this->get('/checkout?canton_code=101')->assertInertia(fn (Assert $page) => $page->where('review.shipping.amount_minor', 350000)->where('review.total_minor', 596912));
    }

    public function test_confirmation_persists_the_delivery_snapshot_and_the_final_total(): void
    {
        $this->prepare();
        $this->review('101');
        $response = $this->from('/checkout')->post('/checkout', $this->payload())->assertSessionHasNoErrors()->assertStatus(303);
        $order = Order::where('number', basename($response->headers->get('Location')))->sole();
        $this->assertSame(246912, $order->subtotal_minor);
        $this->assertSame(350000, $order->shipping_minor);
        $this->assertSame(596912, $order->total_minor);
        $this->assertSame(DeliveryZone::Gam, $order->shipping_zone);
        $this->assertSame(DeliveryRateSet::where('version', DeliveryZonesSeeder::VERSION)->value('id'), $order->delivery_rate_set_id);
        $this->assertSame(['amount_minor' => 350000, 'zone' => 'gam', 'zone_label' => 'Gran Área Metropolitana', 'free' => false], $order->publicSummary()['shipping']);
    }

    public function test_guanacaste_norte_ships_free_and_the_rest_of_the_country_pays_its_own_fee(): void
    {
        $this->prepare();
        $this->review('501');
        $liberia = $this->from('/checkout')->post('/checkout', $this->payload(['province_code' => '5', 'canton_code' => '501', 'district_code' => '50101']))->assertStatus(303);
        $order = Order::where('number', basename($liberia->headers->get('Location')))->sole();
        $this->assertSame(0, $order->shipping_minor);
        $this->assertSame(246912, $order->total_minor);
        $this->assertSame(DeliveryZone::GuanacasteNorte, $order->shipping_zone);
        $this->prepare();
        $this->review('706');
        $guacimo = $this->from('/checkout')->post('/checkout', $this->payload(['province_code' => '7', 'canton_code' => '706', 'district_code' => '70601']))->assertStatus(303);
        $second = Order::where('number', basename($guacimo->headers->get('Location')))->sole();
        $this->assertSame(450000, $second->shipping_minor);
        $this->assertSame(696912, $second->total_minor);
        $this->assertSame(DeliveryZone::Rest, $second->shipping_zone);
    }

    public function test_reaching_the_threshold_makes_delivery_free(): void
    {
        $this->prepare(4500000, 2);
        $review = $this->review('101');
        $this->assertSame(9000000, $review['subtotal_minor']);
        $this->assertSame(0, $review['shipping']['amount_minor']);
        $this->assertTrue($review['shipping']['free']);
        $this->assertSame(9000000, $review['total_minor']);
        $response = $this->from('/checkout')->post('/checkout', $this->payload())->assertStatus(303);
        $this->assertSame(0, Order::where('number', basename($response->headers->get('Location')))->value('shipping_minor'));
    }

    public function test_changing_the_rates_later_never_changes_an_existing_order(): void
    {
        $this->prepare();
        $this->review('101');
        $response = $this->from('/checkout')->post('/checkout', $this->payload())->assertStatus(303);
        $order = Order::where('number', basename($response->headers->get('Location')))->sole();
        $snapshot = $order->publicSummary();
        $newer = DeliveryRateSet::create(['version' => 'v2-test', 'effective_from' => now(), 'notes' => 'QA']);
        DeliveryRate::create(['delivery_rate_set_id' => $newer->id, 'zone' => DeliveryZone::Gam, 'flat_minor' => 900000, 'free_from_minor' => null, 'currency' => 'CRC']);
        $this->assertSame($snapshot, $order->fresh()->publicSummary());
        $this->assertSame(350000, $order->fresh()->shipping_minor);
        $this->assertSame(596912, $order->fresh()->total_minor);
    }

    public function test_rates_that_change_between_review_and_confirmation_require_a_new_review(): void
    {
        $this->prepare();
        $this->review('101');
        $stale = $this->payload();
        $newer = DeliveryRateSet::create(['version' => 'v2-test', 'effective_from' => now(), 'notes' => 'QA']);
        DeliveryRate::create(['delivery_rate_set_id' => $newer->id, 'zone' => DeliveryZone::Gam, 'flat_minor' => 900000, 'free_from_minor' => null, 'currency' => 'CRC']);
        $this->post('/checkout', $stale)->assertSessionHasErrors('checkout');
        $this->assertDatabaseCount('orders', 0);
        $this->review('101');
        $this->from('/checkout')->post('/checkout', $this->payload())->assertSessionHasNoErrors()->assertStatus(303);
        $this->assertSame(900000, Order::sole()->shipping_minor);
    }

    public function test_the_client_cannot_impose_a_shipping_amount_or_a_total(): void
    {
        $this->prepare();
        $this->review('101');
        foreach (['shipping_minor' => 0, 'total_minor' => 1, 'subtotal_minor' => 1, 'shipping_zone' => 'gam', 'delivery_rate_set_id' => 1] as $field => $value) {
            $this->post('/checkout', $this->payload([$field => $value]))->assertSessionHasErrors('checkout');
        }
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_a_cart_in_another_currency_cannot_be_reviewed_or_confirmed(): void
    {
        $this->prepare(1000, 1, 'USD');
        $this->get('/checkout')->assertRedirect('/cart')->assertSessionHasErrors('checkout');
        $this->post('/checkout', $this->payload(['token' => (string) Str::uuid()]))->assertSessionHasErrors('checkout');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_an_unknown_canton_blocks_the_confirmation(): void
    {
        $this->prepare();
        $this->review('101');
        $this->post('/checkout', $this->payload(['province_code' => '9', 'canton_code' => '999', 'district_code' => '99999']))->assertSessionHasErrors();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_an_order_placed_before_v1c_still_opens_and_shows_no_invented_delivery(): void
    {
        $this->prepare();
        $this->review('101');
        $response = $this->from('/checkout')->post('/checkout', $this->payload())->assertStatus(303);
        $order = Order::where('number', basename($response->headers->get('Location')))->sole();
        // Exactly how a row written before the V1-C migration looks.
        DB::table('orders')->where('id', $order->id)->update(['shipping_minor' => null, 'shipping_zone' => null, 'delivery_rate_set_id' => null, 'total_minor' => 246912]);
        $summary = $order->fresh()->publicSummary();
        $this->assertNull($summary['shipping']);
        $this->assertSame(246912, $summary['total_minor']);
        $this->get('/checkout/confirmation/'.$order->number)->assertOk()->assertInertia(fn (Assert $page) => $page->where('order.shipping', null));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\ValueAddedTax;
use Database\Seeders\DeliveryZonesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ValueAddedTaxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DeliveryZonesSeeder::class);
    }

    private function prepare(int $priceMinor = 123456, int $quantity = 2): Product
    {
        $product = Product::factory()->sellable()->create(['status' => 'published', 'price_minor' => $priceMinor, 'currency' => 'CRC']);
        $this->post('/cart/items', ['mutation_id' => (string) Str::uuid(), 'revision' => session('shopping_cart.revision', 0), 'product_slug' => $product->slug, 'quantity' => $quantity])->assertSessionHasNoErrors();

        return $product;
    }

    private function payload(array $extra = []): array
    {
        return ['token' => session('checkout_review.token'), 'first_name' => 'Ana', 'last_name' => 'Prueba', 'email' => 'guest@example.test', 'phone' => '+50688887777', 'province_code' => '1', 'canton_code' => '101', 'district_code' => '10101', 'exact_address' => 'Dirección ficticia para pruebas, casa azul.', 'additional' => '', ...$extra];
    }

    private function place(): Order
    {
        $response = $this->from('/checkout')->post('/checkout', $this->payload())->assertSessionHasNoErrors()->assertStatus(303);

        return Order::where('number', basename($response->headers->get('Location')))->sole();
    }

    public function test_the_included_tax_is_thirteen_over_one_hundred_thirteen_rounded_to_cents(): void
    {
        // 246912 × 13 / 113 = 28405.80…, which rounds up to 28406.
        $this->assertSame(28406, ValueAddedTax::includedIn(246912));
        $this->assertSame(0, ValueAddedTax::includedIn(0));
        $this->assertSame(11504425, ValueAddedTax::includedIn(100000000));
        foreach ([1, 99, 100, 113, 1000, 350000, 9000000] as $amount) {
            $this->assertSame((int) round($amount * 13 / 113), ValueAddedTax::includedIn($amount), 'monto '.$amount);
        }
    }

    public function test_negative_or_empty_amounts_never_produce_a_tax_line(): void
    {
        $this->assertSame(0, ValueAddedTax::includedIn(-1));
        $this->assertSame(['rate_percent' => 13, 'amount_minor' => 0], ValueAddedTax::breakdown(0));
    }

    public function test_the_checkout_review_shows_the_tax_without_changing_the_total(): void
    {
        $this->prepare();
        $review = $this->get('/checkout?canton_code=101')->assertOk()->viewData('page')['props']['review'];
        $this->assertSame(246912, $review['subtotal_minor']);
        $this->assertSame(350000, $review['shipping']['amount_minor']);
        // The total is exactly products plus delivery: the tax line adds nothing to it.
        $this->assertSame(596912, $review['total_minor']);
        $this->assertSame(['rate_percent' => 13, 'amount_minor' => 28406], $review['tax']);
    }

    public function test_delivery_is_excluded_from_the_tax_base(): void
    {
        $this->prepare();
        $withDelivery = $this->get('/checkout?canton_code=101')->viewData('page')['props']['review'];
        $withoutDelivery = $this->get('/checkout')->viewData('page')['props']['review'];
        // Same products, different delivery: the tax figure must not move.
        $this->assertSame($withoutDelivery['tax'], $withDelivery['tax']);
        $this->assertSame(28406, $withDelivery['tax']['amount_minor']);
        // Guanacaste ships free, so its total differs again and the tax still does not.
        $this->assertSame(28406, $this->get('/checkout?canton_code=501')->viewData('page')['props']['review']['tax']['amount_minor']);
        $this->assertNotSame(ValueAddedTax::includedIn(596912), $withDelivery['tax']['amount_minor']);
    }

    public function test_a_confirmed_order_exposes_the_tax_and_keeps_its_stored_total(): void
    {
        $this->prepare();
        $this->get('/checkout?canton_code=101')->assertOk();
        $order = $this->place();
        $this->assertSame(596912, $order->total_minor);
        $summary = $order->publicSummary();
        $this->assertSame(['rate_percent' => 13, 'amount_minor' => 28406], $summary['tax']);
        $this->assertSame(596912, $summary['total_minor']);
        // Nothing was persisted: the breakdown exists only in the projection.
        $this->assertSame(['subtotal_minor' => 246912, 'shipping_minor' => 350000, 'total_minor' => 596912],
            (array) DB::table('orders')->where('id', $order->id)->first(['subtotal_minor', 'shipping_minor', 'total_minor']));
        $this->get('/checkout/confirmation/'.$order->number)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('order.tax.amount_minor', 28406)->where('order.total_minor', 596912));
    }

    public function test_orders_placed_before_v1c_also_show_the_tax_line(): void
    {
        $this->prepare();
        $this->get('/checkout?canton_code=101')->assertOk();
        $order = $this->place();
        // A row as it looked before delivery quoting existed: no shipping at all.
        DB::table('orders')->where('id', $order->id)->update(['shipping_minor' => null, 'shipping_zone' => null, 'delivery_rate_set_id' => null, 'total_minor' => 246912]);
        $summary = $order->fresh()->publicSummary();
        $this->assertNull($summary['shipping']);
        $this->assertSame(246912, $summary['total_minor']);
        $this->assertSame(28406, $summary['tax']['amount_minor']);
    }

    public function test_the_admin_order_detail_also_carries_the_tax(): void
    {
        $this->prepare();
        $this->get('/checkout?canton_code=101')->assertOk();
        $order = $this->place();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]));
        $this->get('/admin/orders/'.$order->number)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('order.tax.amount_minor', 28406)->where('order.total_minor', 596912));
    }
}

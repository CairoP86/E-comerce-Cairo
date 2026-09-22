<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Support\OrderNumber;
use Carbon\CarbonImmutable;
use Database\Seeders\DeliveryZonesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderNumberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DeliveryZonesSeeder::class);
    }

    private function payload(): array
    {
        return ['token' => session('checkout_review.token'), 'first_name' => 'Ana', 'last_name' => 'Prueba', 'email' => 'guest@example.test', 'phone' => '+50688887777', 'province_code' => '1', 'canton_code' => '101', 'district_code' => '10101', 'exact_address' => 'Dirección ficticia para pruebas, casa azul.', 'additional' => ''];
    }

    /** One full guest checkout; returns the order it created. */
    private function checkout(): Order
    {
        $product = Product::factory()->sellable()->create(['status' => 'published', 'price_minor' => 100000, 'currency' => 'CRC']);
        $this->post('/cart/items', ['mutation_id' => (string) Str::uuid(), 'revision' => session('shopping_cart.revision', 0), 'product_slug' => $product->slug, 'quantity' => 1])->assertSessionHasNoErrors();
        $this->get('/checkout?canton_code=101')->assertOk();
        $response = $this->from('/checkout')->post('/checkout', $this->payload())->assertSessionHasNoErrors()->assertStatus(303);

        return Order::where('number', basename($response->headers->get('Location')))->sole();
    }

    public function test_orders_are_numbered_by_day_in_sequence(): void
    {
        $this->travelTo(now('America/Costa_Rica')->setDate(2026, 9, 21)->setTime(10, 0));
        $this->assertSame('TC-260921-001', $this->checkout()->number);
        $this->assertSame('TC-260921-002', $this->checkout()->number);
    }

    public function test_the_day_is_the_costa_rican_one_not_utc(): void
    {
        // 02:00 UTC on the 22nd is still 20:00 on the 21st in Costa Rica.
        $this->travelTo(CarbonImmutable::parse('2026-09-22 02:00:00', 'UTC'));
        $this->assertSame('TC-260921-001', $this->checkout()->number);
    }

    public function test_the_sequence_starts_again_each_day(): void
    {
        $this->travelTo(now('America/Costa_Rica')->setDate(2026, 9, 21)->setTime(23, 50));
        $this->assertSame('TC-260921-001', $this->checkout()->number);
        $this->travelTo(now('America/Costa_Rica')->setDate(2026, 9, 22)->setTime(0, 10));
        $this->assertSame('TC-260922-001', $this->checkout()->number);
    }

    public function test_a_retried_confirmation_does_not_consume_a_number(): void
    {
        $this->travelTo(now('America/Costa_Rica')->setDate(2026, 9, 21)->setTime(10, 0));
        $product = Product::factory()->sellable()->create(['status' => 'published', 'price_minor' => 100000, 'currency' => 'CRC']);
        $this->post('/cart/items', ['mutation_id' => (string) Str::uuid(), 'revision' => 0, 'product_slug' => $product->slug, 'quantity' => 1]);
        $this->get('/checkout?canton_code=101');
        $payload = $this->payload();
        $this->from('/checkout')->post('/checkout', $payload)->assertStatus(303);
        // The same token again, as a double click or a network retry would send it.
        $this->from('/checkout')->post('/checkout', $payload)->assertStatus(303);
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame('TC-260921-002', $this->checkout()->number);
    }

    public function test_a_number_taken_by_a_failed_transaction_is_given_back(): void
    {
        $this->travelTo(now('America/Costa_Rica')->setDate(2026, 9, 21)->setTime(10, 0));
        try {
            DB::transaction(function () {
                $this->assertSame('TC-260921-001', OrderNumber::next());
                throw new \RuntimeException('the order failed after taking its number');
            });
        } catch (\RuntimeException) {
        }
        // Rolled back with the order: no gap in the day's sequence.
        $this->assertSame('TC-260921-001', DB::transaction(fn () => OrderNumber::next()));
    }

    public function test_existing_hexadecimal_numbers_keep_working(): void
    {
        $order = $this->checkout();
        DB::table('orders')->where('id', $order->id)->update(['number' => 'TC-A854124E5346FCA7D394']);
        $this->get('/checkout/confirmation/TC-A854124E5346FCA7D394')->assertOk();
    }
}

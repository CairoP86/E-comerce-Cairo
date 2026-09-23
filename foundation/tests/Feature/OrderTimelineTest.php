<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DeliveryZonesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** What happened to this order, in order: the status history it already keeps, named in Spanish. */
class OrderTimelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function order(): Order
    {
        $this->seed(DeliveryZonesSeeder::class);
        $product = Product::factory()->sellable()->create(['status' => 'published', 'currency' => 'CRC']);
        $this->post('/cart/items', ['mutation_id' => (string) Str::uuid(), 'revision' => session('shopping_cart.revision', 0), 'product_slug' => $product->slug, 'quantity' => 1]);
        $this->get('/checkout?canton_code=101');
        $response = $this->from('/checkout')->post('/checkout', ['token' => session('checkout_review.token'), 'first_name' => 'Ana', 'last_name' => 'Prueba', 'email' => 'guest@example.test', 'phone' => '+50688887777', 'province_code' => '1', 'canton_code' => '101', 'district_code' => '10101', 'exact_address' => 'Dirección ficticia para pruebas, casa azul.', 'additional' => '']);

        return Order::where('number', basename($response->headers->get('Location')))->sole();
    }

    private function timeline(Order $order): array
    {
        return $this->get("/admin/orders/{$order->number}")->assertOk()->viewData('page')['props']['timeline'];
    }

    public function test_a_new_order_has_one_step_and_it_says_who_placed_it(): void
    {
        $order = $this->order();
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));

        $timeline = $this->timeline($order);
        $this->assertCount(1, $timeline);
        $this->assertSame('pending_payment', $timeline[0]['status']);
        // Placed by a guest through the checkout: nobody from the team did it.
        $this->assertNull($timeline[0]['actor']);
    }

    public function test_confirming_the_payment_adds_a_step_with_the_person_who_confirmed_it(): void
    {
        $order = $this->order();
        $admin = User::factory()->create(['role' => Role::Admin, 'name' => 'Quien Confirma']);
        $this->actingAs($admin);
        $this->post("/admin/orders/{$order->number}/paid")->assertRedirect();

        $timeline = $this->timeline($order);
        $this->assertSame(['pending_payment', 'paid'], collect($timeline)->pluck('status')->all());
        $this->assertSame('Quien Confirma', $timeline[1]['actor']['name']);
        $this->assertNotNull($timeline[1]['created_at']);
    }

    public function test_the_timeline_belongs_to_its_own_order(): void
    {
        $first = $this->order();
        $second = $this->order();
        $admin = User::factory()->create(['role' => Role::Admin]);
        $this->actingAs($admin);
        $this->post("/admin/orders/{$second->number}/paid")->assertRedirect();

        $this->assertCount(1, $this->timeline($first));
        $this->assertCount(2, $this->timeline($second));
    }

    public function test_the_steps_are_ordered_oldest_first(): void
    {
        $order = $this->order();
        $admin = User::factory()->create(['role' => Role::Admin]);
        $this->actingAs($admin);
        $this->post("/admin/orders/{$order->number}/paid")->assertRedirect();
        // Both rows share a second in a fast test: the order must not depend on that.
        DB::table('order_status_history')->where('order_id', $order->id)->update(['created_at' => CarbonImmutable::now()]);

        $this->assertSame(['pending_payment', 'paid'], collect($this->timeline($order))->pluck('status')->all());
    }
}

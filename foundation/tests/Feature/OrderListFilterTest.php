<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DeliveryZonesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** The order list's strip: how many are waiting for you, and touching it shows exactly those. */
class OrderListFilterTest extends TestCase
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

    private function props(string $query = ''): array
    {
        return $this->get('/admin/orders?'.$query)->assertOk()->viewData('page')['props'];
    }

    private function seedOrders(): array
    {
        $waiting = [$this->order()->number, $this->order()->number];
        $paid = $this->order();
        DB::table('orders')->where('id', $paid->id)->update(['status' => OrderStatus::Paid->value]);
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));

        return [$waiting, $paid->number];
    }

    public function test_each_segment_lists_exactly_the_orders_it_counts(): void
    {
        [$waiting, $paid] = $this->seedOrders();

        $this->assertSame(['pending' => 2, 'paid' => 1, 'total' => 3], $this->props()['orderCounts']);
        sort($waiting);
        $this->assertSame($waiting, collect($this->props('estado=pendientes')['orders']['data'])->pluck('number')->sort()->values()->all());
        $this->assertSame([$paid], collect($this->props('estado=pagados')['orders']['data'])->pluck('number')->all());
        $this->assertCount(3, $this->props()['orders']['data']);
    }

    public function test_the_counts_do_not_change_while_a_filter_is_on(): void
    {
        $this->seedOrders();
        $filtered = $this->props('estado=pagados');
        $this->assertSame(['pending' => 2, 'paid' => 1, 'total' => 3], $filtered['orderCounts']);
        $this->assertSame('pagados', $filtered['filters']['estado']);
    }

    public function test_the_pending_count_matches_the_panel_and_the_sidebar(): void
    {
        $this->seedOrders();
        $this->assertSame($this->props()['orderCounts']['pending'], $this->get('/admin')->viewData('page')['props']['pendingOrders']);
        $this->assertSame($this->props()['orderCounts']['pending'], $this->props()['operatorTurn']['orders']);
    }

    public function test_an_unknown_state_is_rejected(): void
    {
        $this->seedOrders();
        $this->get('/admin/orders?estado=cualquiera')->assertSessionHasErrors('estado');
    }
}

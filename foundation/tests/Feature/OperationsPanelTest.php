<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
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

/** The operations panel's "your turn" strip: the oldest order awaiting payment leads it. */
class OperationsPanelTest extends TestCase
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

    private function placedAt(Order $order, CarbonImmutable $at, ?OrderStatus $status = null): void
    {
        DB::table('orders')->where('id', $order->id)->update(['created_at' => $at, ...($status ? ['status' => $status->value] : [])]);
    }

    private function oldest(): ?array
    {
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));

        return $this->get('/admin')->assertOk()->viewData('page')['props']['oldestPendingOrder'];
    }

    public function test_the_panel_points_to_the_oldest_order_awaiting_payment(): void
    {
        $now = CarbonImmutable::now()->startOfSecond();
        $waiting = $this->order();
        $older_but_paid = $this->order();
        $recent = $this->order();
        $this->placedAt($waiting, $now->subDays(3));
        // Paid orders are no longer the operator's turn, however old they are.
        $this->placedAt($older_but_paid, $now->subDays(10), OrderStatus::Paid);
        $this->placedAt($recent, $now->subHour());

        $oldest = $this->oldest();
        $this->assertSame($waiting->number, $oldest['number']);
        $this->assertTrue($now->subDays(3)->equalTo(CarbonImmutable::parse($oldest['created_at'])));
    }

    public function test_with_nothing_awaiting_payment_there_is_no_oldest_order(): void
    {
        $this->placedAt($this->order(), CarbonImmutable::now()->subDay(), OrderStatus::Paid);
        $this->assertNull($this->oldest());
    }
}

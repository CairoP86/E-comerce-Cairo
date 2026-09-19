<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockHold;
use App\Models\SupplierProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PruneStockHoldsTest extends TestCase
{
    use RefreshDatabase;

    private function hold(?string $holder, ?string $orderId, int $minutesLeft): StockHold
    {
        $product = Product::factory()->sellable()->create(['status' => 'published']);
        $hold = new StockHold;
        $hold->forceFill(['product_id' => $product->id, 'supplier_product_id' => SupplierProduct::where('product_id', $product->id)->value('id'), 'holder' => $holder, 'order_id' => $orderId, 'quantity' => 1, 'expires_at' => now()->addMinutes($minutesLeft)])->save();

        return $hold;
    }

    private function order(): Order
    {
        $order = new Order;
        $order->forceFill(['id' => (string) Str::uuid(), 'number' => 'TC-PRUNE-TEST', 'checkout_key' => str_repeat('k', 64), 'owner_hash' => str_repeat('o', 64), 'request_hash' => str_repeat('r', 64), 'cart_revision' => 1, 'status' => OrderStatus::PendingPayment, 'first_name' => 'QA', 'last_name' => 'QA', 'email' => 'qa@example.test', 'phone' => '+50688887777', 'currency' => 'CRC', 'subtotal_minor' => 1000, 'total_minor' => 1000])->save();

        return $order;
    }

    public function test_prune_removes_only_expired_cart_holds(): void
    {
        $this->freezeSecond();
        $expired = $this->hold(str_repeat('a', 64), null, -1);
        $active = $this->hold(str_repeat('a', 64), null, 30);
        $orderHold = $this->hold(null, $this->order()->id, -1);
        $this->artisan('holds:prune')->expectsOutputToContain('Expired cart holds removed: 1')->assertSuccessful();
        $this->assertModelMissing($expired);
        $this->assertModelExists($active);
        $this->assertModelExists($orderHold);
    }

    public function test_prune_is_scheduled_hourly(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('holds:prune')->assertSuccessful();
    }
}

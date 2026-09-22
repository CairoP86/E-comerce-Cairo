<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Brand;
use Database\Seeders\DeliveryZonesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminIdentityPanelTest extends TestCase
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

    public function test_the_brand_mark_is_derived_from_the_single_brand_name(): void
    {
        $this->assertSame('TC', Brand::markFor('TECH COMMERCE'));
        $this->assertSame('TC', Brand::markFor('Tech Commerce'));
        $this->assertSame('N', Brand::markFor('Nodal'));
        // Two letters at most, whatever the length of the name.
        $this->assertSame('MT', Brand::markFor('Mi Tienda Tecnológica'));
        $this->assertSame('ÑA', Brand::markFor('ñandú azul'));
        $this->assertSame(Brand::markFor(config('storefront.name')), config('storefront.mark'));
    }

    public function test_the_panel_counts_orders_awaiting_payment(): void
    {
        $first = $this->order();
        $this->order();
        $paid = $this->order();
        DB::table('orders')->where('id', $paid->id)->update(['status' => OrderStatus::Paid->value]);

        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        $this->get('/admin')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('admin/Overview')
            ->where('pendingOrders', 2)
            ->has('freshnessSummary.expired')->has('freshnessSummary.expiring')->has('freshnessSummary.invalid'));
        $this->assertNotNull($first);
    }

    public function test_the_panel_reports_the_same_offer_freshness_as_the_product_list(): void
    {
        $ttl = config('commerce.availability.ttl_minutes');
        Product::factory()->sellable(1, ['observed_at' => now()->subMinutes($ttl + 5)])->create(['status' => 'published', 'is_demo' => false]);
        Product::factory()->sellable(1, ['observed_at' => now()->subMinutes((int) ($ttl * 0.9))])->create(['status' => 'published', 'is_demo' => false]);
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));

        $panel = $this->get('/admin')->viewData('page')['props']['freshnessSummary'];
        $list = $this->get('/admin/catalog/products')->viewData('page')['props']['freshnessSummary'];
        $this->assertSame(['expired' => 1, 'expiring' => 1, 'invalid' => 0], $panel);
        $this->assertSame($list, $panel);
    }

    public function test_operators_see_the_panel_and_customers_do_not(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Operator]));
        $this->get('/admin')->assertOk()->assertInertia(fn (Assert $page) => $page->has('pendingOrders'));
        $this->actingAs(User::factory()->create(['role' => Role::Customer]));
        $this->get('/admin')->assertForbidden();
    }

    public function test_the_order_list_carries_the_status_for_its_badge(): void
    {
        $this->order();
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        $this->get('/admin/orders')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('orders.data.0.status', 'pending_payment')->where('orders.data.0.status_label', 'Pendiente de pago'));
    }
}

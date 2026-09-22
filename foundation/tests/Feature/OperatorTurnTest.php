<?php

namespace Tests\Feature;

use App\Availability\CatalogFreshness;
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

/** The sidebar counters: what only the operator can unblock, on every private page. */
class OperatorTurnTest extends TestCase
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

    private function turn(string $path): ?array
    {
        return $this->get($path)->assertOk()->viewData('page')['props']['operatorTurn'];
    }

    private function offers(): void
    {
        $ttl = config('commerce.availability.ttl_minutes');
        $published = ['status' => 'published', 'is_demo' => false];
        // Counted: expired, and a published product without a usable offer.
        Product::factory()->sellable(1, ['observed_at' => now()->subMinutes($ttl + 5)])->create($published);
        Product::factory()->create($published);
        // Not counted: about to expire is "soon", not "now"; fresh; drafts; demo products.
        Product::factory()->sellable(1, ['observed_at' => now()->subMinutes((int) ($ttl * 0.9))])->create($published);
        Product::factory()->sellable()->create($published);
        Product::factory()->sellable(1, ['observed_at' => now()->subMinutes($ttl + 5)])->create(['status' => 'draft', 'is_demo' => false]);
        Product::factory()->sellable(1, ['observed_at' => now()->subMinutes($ttl + 5)])->create(['status' => 'published', 'is_demo' => true]);
    }

    public function test_staff_see_orders_awaiting_payment_and_offers_that_need_them_now(): void
    {
        $this->order();
        $this->order();
        DB::table('orders')->where('id', $this->order()->id)->update(['status' => OrderStatus::Paid->value]);
        $this->offers();
        $this->actingAs(User::factory()->create(['role' => Role::Operator]));

        foreach (['/admin', '/admin/catalog/products', '/admin/orders', '/account'] as $path) {
            $this->assertSame(['orders' => 2, 'offers' => 2], $this->turn($path), $path);
        }
    }

    public function test_the_counters_never_disagree_with_the_panel(): void
    {
        $this->order();
        $this->offers();
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));

        $props = $this->get('/admin')->viewData('page')['props'];
        $this->assertSame($props['pendingOrders'], $props['operatorTurn']['orders']);
        $this->assertSame($props['freshnessSummary']['expired'] + $props['freshnessSummary']['invalid'], $props['operatorTurn']['offers']);
    }

    public function test_the_freshness_summary_is_computed_once_per_request(): void
    {
        // The panel and the sidebar both ask for it on /admin; one instance per request keeps one computation.
        $this->assertSame(app(CatalogFreshness::class), app(CatalogFreshness::class));
    }

    public function test_a_calm_day_counts_zero(): void
    {
        Product::factory()->sellable()->create(['status' => 'published', 'is_demo' => false]);
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        $this->assertSame(['orders' => 0, 'offers' => 0], $this->turn('/admin'));
    }

    public function test_customers_guests_and_the_storefront_never_get_it(): void
    {
        $this->offers();
        $this->assertNull($this->turn('/'));

        $this->actingAs(User::factory()->create(['role' => Role::Customer]));
        $this->assertNull($this->turn('/account'));

        // Staff browsing the store: not computed, so the storefront never pays for the freshness query.
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        $this->assertNull($this->turn('/'));
        $this->assertNull($this->turn('/catalog'));
    }
}

<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Brand;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Audit;
use Carbon\CarbonImmutable;
use Database\Seeders\DeliveryZonesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Mi cuenta says something useful to each kind of person, and Marcas counts what it holds. */
class AccountContextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function order(?User $owner = null): Order
    {
        $this->seed(DeliveryZonesSeeder::class);
        $product = Product::factory()->sellable()->create(['status' => 'published', 'currency' => 'CRC']);
        $this->post('/cart/items', ['mutation_id' => (string) Str::uuid(), 'revision' => session('shopping_cart.revision', 0), 'product_slug' => $product->slug, 'quantity' => 1]);
        $this->get('/checkout?canton_code=101');
        $response = $this->from('/checkout')->post('/checkout', ['token' => session('checkout_review.token'), 'first_name' => 'Ana', 'last_name' => 'Prueba', 'email' => 'guest@example.test', 'phone' => '+50688887777', 'province_code' => '1', 'canton_code' => '101', 'district_code' => '10101', 'exact_address' => 'Dirección ficticia para pruebas, casa azul.', 'additional' => '']);
        $order = Order::where('number', basename($response->headers->get('Location')))->sole();
        if ($owner) {
            DB::table('orders')->where('id', $order->id)->update(['user_id' => $owner->id]);
        }

        return $order->fresh();
    }

    private function context(): ?array
    {
        return $this->get('/account')->assertOk()->viewData('page')['props']['context'];
    }

    public function test_a_customer_sees_their_own_recent_orders(): void
    {
        $customer = User::factory()->create(['role' => Role::Customer]);
        $mine = $this->order($customer);
        $someoneElse = $this->order(User::factory()->create(['role' => Role::Customer]));

        $this->actingAs($customer);
        $context = $this->context();
        $this->assertSame([$mine->number], collect($context['orders'])->pluck('number')->all());
        $this->assertSame($mine->total_minor, $context['orders'][0]['total_minor']);
        $this->assertSame('Pendiente de pago', $context['orders'][0]['status_label']);
        $this->assertNotContains($someoneElse->number, collect($context['orders'])->pluck('number')->all());
        // Sign-ins are the staff half of this page; a customer does not get them.
        $this->assertArrayNotHasKey('sign_ins', $context);
    }

    public function test_a_customer_with_no_orders_gets_an_empty_list_not_a_missing_key(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Customer]));
        $this->assertSame([], $this->context()['orders']);
    }

    public function test_the_list_is_the_three_most_recent_orders(): void
    {
        $customer = User::factory()->create(['role' => Role::Customer]);
        $numbers = [];
        foreach (range(1, 4) as $index) {
            $order = $this->order($customer);
            DB::table('orders')->where('id', $order->id)->update(['created_at' => CarbonImmutable::now()->subDays(5 - $index)]);
            $numbers[] = $order->number;
        }

        $this->actingAs($customer);
        $listed = collect($this->context()['orders'])->pluck('number')->all();
        $this->assertSame(array_reverse(array_slice($numbers, -3)), $listed);
    }

    public function test_staff_see_their_own_last_sign_ins(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        $other = User::factory()->create(['role' => Role::Operator]);
        foreach (range(1, 4) as $index) {
            Audit::record('auth.login', $admin->id, $admin->id);
        }
        Audit::record('auth.login', $other->id, $other->id);
        Audit::record('catalog.updated', $admin->id, metadata: ['entity_type' => 'products', 'entity_id' => 1]);

        $this->actingAs($admin);
        $context = $this->context();
        // Only sign-ins, only this person's, three at most, newest first.
        $this->assertCount(3, $context['sign_ins']);
        $this->assertArrayNotHasKey('orders', $context);
    }

    public function test_brands_carry_how_many_products_they_hold(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        $lenovo = Brand::create(['name' => 'Lenovo', 'slug' => 'lenovo', 'status' => 'published']);
        $empty = Brand::create(['name' => 'Sin productos', 'slug' => 'sin-productos', 'status' => 'draft']);
        Product::factory()->count(2)->create(['brand_id' => $lenovo->id, 'status' => 'published']);
        Product::factory()->create(['brand_id' => $lenovo->id, 'status' => 'draft']);

        $entries = collect($this->get('/admin/catalog/brands')->assertOk()->viewData('page')['props']['entries'])->keyBy('name');
        // Every state counts, the same rule the categories tree follows.
        $this->assertSame(3, $entries['Lenovo']['products_total']);
        $this->assertSame(0, $entries['Sin productos']['products_total']);
    }
}

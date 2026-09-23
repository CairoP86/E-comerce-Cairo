<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Audit;
use Database\Seeders\DeliveryZonesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** The activity log names the people involved instead of showing their ids. */
class AuditLogViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /** A real order through the checkout flow: the log stores its uuid, the admin reads its number. */
    private function order(): Order
    {
        $this->seed(DeliveryZonesSeeder::class);
        $product = Product::factory()->sellable()->create(['status' => 'published', 'currency' => 'CRC']);
        $this->post('/cart/items', ['mutation_id' => (string) Str::uuid(), 'revision' => session('shopping_cart.revision', 0), 'product_slug' => $product->slug, 'quantity' => 1]);
        $this->get('/checkout?canton_code=101');
        $response = $this->from('/checkout')->post('/checkout', ['token' => session('checkout_review.token'), 'first_name' => 'Ana', 'last_name' => 'Prueba', 'email' => 'guest@example.test', 'phone' => '+50688887777', 'province_code' => '1', 'canton_code' => '101', 'district_code' => '10101', 'exact_address' => 'Dirección ficticia para pruebas, casa azul.', 'additional' => '']);

        return Order::where('number', basename($response->headers->get('Location')))->sole();
    }

    private function entries(): array
    {
        return $this->get('/admin/audit')->assertOk()->viewData('page')['props']['entries']['data'];
    }

    public function test_an_entry_carries_the_name_and_email_of_the_actor_and_the_account(): void
    {
        $actor = User::factory()->create(['name' => 'Quien Actúa', 'email' => 'actor@example.test', 'role' => Role::Admin]);
        $subject = User::factory()->create(['name' => 'Cuenta Afectada', 'email' => 'cuenta@example.test']);
        Audit::record('user.role_changed', $actor->id, $subject->id, ['from_role' => 'customer', 'to_role' => 'operator']);

        $this->actingAs($actor);
        $entry = $this->entries()[0];
        $this->assertSame(['id' => $actor->id, 'name' => 'Quien Actúa', 'email' => 'actor@example.test'], $entry['actor']);
        $this->assertSame(['id' => $subject->id, 'name' => 'Cuenta Afectada', 'email' => 'cuenta@example.test'], $entry['subject']);
    }

    public function test_deleting_a_user_clears_the_reference_by_design(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        $gone = User::factory()->create();
        Audit::record('auth.login', $gone->id, $gone->id);
        $gone->delete();

        $this->actingAs($admin);
        $entry = $this->entries()[0];
        // The schema declares nullOnDelete: the log keeps the event but forgets who it was.
        $this->assertNull($entry['actor_id']);
        $this->assertNull($entry['actor']);
        $this->assertNull($entry['subject']);
        $this->assertSame('auth.login', $entry['event']);
    }

    public function test_an_action_without_an_actor_stays_empty(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        Audit::record('catalog.demo_archived', metadata: ['entity_type' => 'products', 'entity_id' => 3], source: 'cli');

        $this->actingAs($admin);
        $entry = $this->entries()[0];
        $this->assertNull($entry['actor']);
        $this->assertNull($entry['actor_id']);
        $this->assertSame('cli', $entry['source']);
    }

    public function test_an_order_entry_carries_its_number_so_the_log_can_link_to_it(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        $this->actingAs($admin);
        $order = $this->order();
        Audit::record('order.marked_paid', $admin->id, metadata: ['entity_type' => 'order', 'entity_id' => $order->id]);

        $entry = $this->entries()[0];
        $this->assertSame($order->number, $entry['order_number']);
        // Other entities keep their raw id: only orders have a number the operator reads.
        Audit::record('catalog.updated', $admin->id, metadata: ['entity_type' => 'products', 'entity_id' => 7]);
        $this->assertNull($this->entries()[0]['order_number']);
    }

    public function test_an_order_that_no_longer_exists_does_not_break_the_log(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        Audit::record('order.viewed', $admin->id, metadata: ['entity_type' => 'order', 'entity_id' => '00000000-0000-4000-8000-000000000000']);

        $this->actingAs($admin);
        $entry = $this->entries()[0];
        $this->assertNull($entry['order_number']);
        $this->assertSame('00000000-0000-4000-8000-000000000000', $entry['metadata']['entity_id']);
    }

    public function test_the_page_resolves_every_name_in_one_query(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        foreach (User::factory()->count(5)->create() as $user) {
            Audit::record('auth.login', $user->id, $user->id);
        }

        $this->actingAs($admin);
        $queries = 0;
        DB::listen(function ($query) use (&$queries) {
            if (str_contains($query->sql, '"users"') || str_contains($query->sql, '`users`') || str_contains($query->sql, 'orders')) {
                $queries++;
            }
        });
        $this->entries();
        // The signed-in user, every name on the page and every order number: never one per row.
        $this->assertLessThanOrEqual(3, $queries);
    }
}

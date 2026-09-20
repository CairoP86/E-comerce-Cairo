<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrderPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function order(): Order
    {
        $product = Product::factory()->sellable()->create(['status' => 'published', 'price_minor' => 50000, 'currency' => 'CRC']);
        $this->post('/cart/items', ['mutation_id' => (string) Str::uuid(), 'revision' => session('shopping_cart.revision', 0), 'product_slug' => $product->slug, 'quantity' => 1])->assertSessionHasNoErrors();
        $this->get('/checkout')->assertOk();
        $response = $this->from('/checkout')->post('/checkout', ['token' => session('checkout_review.token'), 'first_name' => 'Ana', 'last_name' => 'Prueba', 'email' => 'guest@example.test', 'phone' => '+50688887777', 'province_code' => '1', 'canton_code' => '101', 'district_code' => '10101', 'exact_address' => 'Dirección ficticia para pruebas, casa azul.', 'additional' => ''])->assertStatus(303);

        return Order::where('number', basename($response->headers->get('Location')))->firstOrFail();
    }

    private function actingAsRole(Role $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        $this->actingAs($user);

        return $user;
    }

    public function test_admin_marks_a_pending_order_as_paid_with_history_and_audit(): void
    {
        $order = $this->order();
        $admin = $this->actingAsRole(Role::Admin);
        $this->from('/admin/orders/'.$order->number)->post('/admin/orders/'.$order->number.'/paid')->assertRedirect('/admin/orders/'.$order->number)->assertSessionHas('status');
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertDatabaseHas('order_status_history', ['order_id' => $order->id, 'from_status' => 'pending_payment', 'to_status' => 'paid', 'actor_id' => $admin->id]);
        $this->assertSame(2, DB::table('order_status_history')->where('order_id', $order->id)->count());
        $audit = AuditLog::where('event', 'order.marked_paid')->sole();
        $this->assertSame($admin->id, $audit->actor_id);
        $this->assertSame(['entity_type' => 'order', 'entity_id' => $order->id, 'from_status' => 'pending_payment', 'to_status' => 'paid'], $audit->metadata);
        $this->get('/admin/orders/'.$order->number)->assertOk()->assertInertia(fn (Assert $page) => $page->where('order.status', 'paid')->where('order.status_label', 'Pagado'));
    }

    public function test_operator_guest_and_customer_cannot_mark_an_order_as_paid(): void
    {
        $order = $this->order();
        $this->actingAsRole(Role::Operator);
        $this->post('/admin/orders/'.$order->number.'/paid')->assertForbidden();
        $this->actingAsRole(Role::Customer);
        $this->post('/admin/orders/'.$order->number.'/paid')->assertForbidden();
        auth()->logout();
        $this->post('/admin/orders/'.$order->number.'/paid')->assertRedirect('/login');
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
        $this->assertSame(1, DB::table('order_status_history')->where('order_id', $order->id)->count());
        $this->assertSame(0, AuditLog::where('event', 'order.marked_paid')->count());
    }

    public function test_repeated_clicks_do_not_duplicate_history_or_audit(): void
    {
        $order = $this->order();
        $this->actingAsRole(Role::Admin);
        $url = '/admin/orders/'.$order->number.'/paid';
        $this->from('/admin/orders/'.$order->number)->post($url)->assertSessionHasNoErrors();
        $this->from('/admin/orders/'.$order->number)->post($url)->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(2, DB::table('order_status_history')->where('order_id', $order->id)->count());
        $this->assertSame(1, AuditLog::where('event', 'order.marked_paid')->count());
    }

    public function test_a_paid_order_never_returns_to_pending_payment(): void
    {
        $order = $this->order();
        $this->actingAsRole(Role::Admin);
        $this->post('/admin/orders/'.$order->number.'/paid');
        $this->post('/admin/orders/'.$order->number.'/paid');
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame([null, 'pending_payment'], DB::table('order_status_history')->where('order_id', $order->id)->orderBy('id')->pluck('from_status')->all());
        $this->assertSame(['pending_payment', 'paid'], DB::table('order_status_history')->where('order_id', $order->id)->orderBy('id')->pluck('to_status')->all());
    }

    public function test_the_paid_action_is_offered_only_to_administrators_and_only_while_pending(): void
    {
        $order = $this->order();
        $this->actingAsRole(Role::Operator);
        $this->get('/admin/orders/'.$order->number)->assertOk()->assertInertia(fn (Assert $page) => $page->where('canMarkPaid', false));
        $this->actingAsRole(Role::Admin);
        $this->get('/admin/orders/'.$order->number)->assertOk()->assertInertia(fn (Assert $page) => $page->where('canMarkPaid', true)->where('order.status', 'pending_payment'));
        $this->post('/admin/orders/'.$order->number.'/paid');
        $this->get('/admin/orders/'.$order->number)->assertOk()->assertInertia(fn (Assert $page) => $page->where('order.status', 'paid'));
    }
}

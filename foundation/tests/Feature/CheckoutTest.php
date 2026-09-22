<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CheckoutService;
use App\Support\CostaRicaTerritories;
use Database\Seeders\DeliveryZonesSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DeliveryZonesSeeder::class);
    }

    private function prepare(array $attributes = []): Product
    {
        $product = Product::factory()->sellable()->create(['status' => 'published', 'price_minor' => 123456, 'currency' => 'CRC', ...$attributes]);
        $this->post('/cart/items', ['mutation_id' => (string) Str::uuid(), 'revision' => session('shopping_cart.revision', 0), 'product_slug' => $product->slug, 'quantity' => 2])->assertSessionHasNoErrors();
        $this->get('/checkout?canton_code=101')->assertOk();

        return $product;
    }

    private function payload(array $extra = []): array
    {
        return ['token' => session('checkout_review.token'), 'first_name' => 'Ana María', 'last_name' => 'Prueba López', 'email' => 'guest@example.test', 'phone' => '+50688887777', 'province_code' => '1', 'canton_code' => '101', 'district_code' => '10101', 'exact_address' => 'Dirección ficticia para pruebas, casa azul.', 'additional' => '', ...$extra];
    }

    private function place(): Order
    {
        $response = $this->from('/checkout')->post('/checkout', $this->payload())->assertSessionHasNoErrors()->assertStatus(303);

        return Order::where('number', basename($response->headers->get('Location')))->firstOrFail();
    }

    public function test_guest_checkout_is_public_private_and_does_not_require_login(): void
    {
        $this->prepare();
        $this->get('/checkout')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertInertia(fn (Assert $p) => $p->component('checkout/Index')->where('auth.user', null)->where('review.total_minor', 246912)->missing('review.items.0.product_id'));
        $this->assertStringContainsString('no-store', $this->get('/checkout')->headers->get('Cache-Control'));
        $this->assertGuest();
    }

    public function test_empty_checkout_redirects_to_cart(): void
    {
        $this->get('/checkout')->assertRedirect('/cart')->assertSessionHasErrors('checkout');
        $this->post('/checkout', $this->payload(['token' => (string) Str::uuid()]))->assertSessionHasErrors('checkout');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_blocked_cart_cannot_open_or_confirm_checkout(): void
    {
        $p = $this->prepare();
        $payload = $this->payload();
        $p->forceFill(['status' => 'archived'])->save();
        $this->get('/checkout')->assertRedirect('/cart');
        $this->post('/checkout', $payload)->assertSessionHasErrors('checkout');
        $this->assertDatabaseCount('orders', 0);
        $this->assertCount(1, session('shopping_cart.items'));
    }

    public function test_guest_order_has_snapshots_initial_status_history_and_no_user(): void
    {
        $p = $this->prepare();
        $order = $this->place();
        $this->assertNull($order->user_id);
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertSame(596912, $order->total_minor);
        $this->assertSame(246912, $order->subtotal_minor);
        $this->assertSame(350000, $order->shipping_minor);
        $this->assertSame('CRC', $order->currency);
        $this->assertSame($p->sku, $order->items->first()->sku);
        $this->assertSame('Carmen', $order->address->district);
        $this->assertSame('10101', $order->address->district_code);
        $this->assertDatabaseHas('order_status_history', ['order_id' => $order->id, 'from_status' => null, 'to_status' => 'pending_payment']);
        $this->assertDatabaseCount('users', 0);
        $this->assertSame([], session('shopping_cart.items'));
        $this->assertTrue(Str::isUuid($order->id));
        $this->assertMatchesRegularExpression('/^TC-\d{6}-\d{3,}$/', $order->number);
        $this->assertNotSame($order->id, $order->number);
    }

    public function test_product_changes_do_not_change_historical_order(): void
    {
        $p = $this->prepare();
        $order = $this->place();
        $before = $order->publicSummary();
        $p->forceFill(['name' => 'Nuevo nombre', 'sku' => 'CHANGED', 'price_minor' => 1, 'currency' => 'USD', 'status' => 'archived'])->save();
        $this->assertSame($before, $order->fresh()->publicSummary());
        $this->get('/checkout/confirmation/'.$order->number)->assertOk()->assertInertia(fn (Assert $page) => $page->where('order.items.0.name', $before['items'][0]['name'])->where('order.total_minor', 596912));
    }

    public function test_address_is_an_independent_snapshot(): void
    {
        $this->prepare();
        $order = $this->place();
        session()->put('some_future_saved_address', 'Otra dirección');
        $this->assertSame('Dirección ficticia para pruebas, casa azul.', $order->fresh()->address->exact_address);
        $this->assertSame('IGN-2026', $order->address->territory_version);
        $this->assertSame('San José', $order->address->province);
        $this->assertSame('San José', $order->address->canton);
    }

    public function test_buyer_fields_are_required_and_invalid_values_rejected(): void
    {
        $this->prepare();
        foreach (['first_name' => '', 'last_name' => '', 'email' => 'invalid', 'phone' => '+15555555555', 'exact_address' => 'short'] as $field => $bad) {
            $this->post('/checkout', $this->payload([$field => $bad]))->assertSessionHasErrors($field);
        }
        $this->assertDatabaseCount('orders', 0);
        $this->assertCount(1, session('shopping_cart.items'));
    }

    public function test_normalization_preserves_names_and_address_and_formats_local_phone(): void
    {
        $this->prepare();
        $this->post('/checkout', $this->payload(['first_name' => '  Ana María  ', 'last_name' => "D'Ávila de la Cruz", 'phone' => '8888-7777', 'exact_address' => "  Casa Azul\nFrente al parque  "]))->assertSessionHasNoErrors();
        $order = Order::firstOrFail();
        $this->assertSame('Ana María', $order->first_name);
        $this->assertSame("D'Ávila de la Cruz", $order->last_name);
        $this->assertSame('+50688887777', $order->phone);
        $this->assertSame("Casa Azul\nFrente al parque", $order->address->exact_address);
    }

    public function test_territorial_hierarchy_and_full_catalog(): void
    {
        $rows = CostaRicaTerritories::all();
        $this->assertCount(494, $rows);
        $this->assertCount(494, array_unique(array_column($rows, 'code')));
        $this->assertCount(84, array_unique(array_column($rows, 'canton_code')));
        $this->assertCount(7, array_unique(array_column($rows, 'province_code')));
        foreach ($rows as $row) {
            $this->assertStringStartsWith($row['province_code'], $row['canton_code']);
            $this->assertStringStartsWith($row['canton_code'], $row['code']);
        }
        $this->assertSame('Duacarí', CostaRicaTerritories::find('7', '706', '70605')['name']);
        $this->prepare();
        foreach ([['province_code' => '2'], ['canton_code' => '201'], ['district_code' => '20101'], ['district_code' => '99999']] as $invalid) {
            $this->post('/checkout', $this->payload($invalid))->assertSessionHasErrors('district_code');
        }
        // The destination is part of the quote now: review the canton that will be confirmed.
        $this->get('/checkout?canton_code=706')->assertOk();
        $this->post('/checkout', $this->payload(['province_code' => '7', 'canton_code' => '706', 'district_code' => '70605']))->assertSessionHasNoErrors();
        $this->assertSame('Duacarí', Order::first()->address->district);
    }

    public function test_changed_price_requires_explicit_review_before_confirmation(): void
    {
        $p = $this->prepare();
        $old = $this->payload();
        $p->update(['price_minor' => 200000]);
        $this->post('/checkout', $old)->assertSessionHasErrors('checkout');
        $this->assertDatabaseCount('orders', 0);
        $this->get('/checkout?canton_code=101')->assertInertia(fn (Assert $page) => $page->where('review.subtotal_minor', 400000)->where('review.total_minor', 750000));
        $this->assertNotSame($old['token'], session('checkout_review.token'));
        $this->assertSame(750000, $this->place()->total_minor);
    }

    public function test_name_sku_and_quantity_changes_also_require_review(): void
    {
        $p = $this->prepare();
        $p->update(['name' => 'Nombre cambiado', 'sku' => 'NEW-SKU']);
        $this->post('/checkout', $this->payload())->assertSessionHasErrors('checkout');
        $this->get('/checkout');
        $line = array_key_first(session('shopping_cart.items'));
        $this->patch('/cart/items/'.$line, ['quantity' => 3, 'revision' => session('shopping_cart.revision'), 'mutation_id' => (string) Str::uuid()]);
        $this->post('/checkout', $this->payload())->assertSessionHasErrors('checkout');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_currency_change_is_blocked_and_no_usd_order_is_created(): void
    {
        $p = $this->prepare();
        // The product changes currency after the review was taken.
        $p->update(['currency' => 'USD']);
        $this->post('/checkout', $this->payload())->assertSessionHasErrors('checkout');
        // Reviewing again does not rescue it, so no order in another currency is created.
        $this->get('/checkout')->assertRedirect('/cart')->assertSessionHasErrors('checkout');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_client_cannot_set_amounts_status_user_or_order_number(): void
    {
        $this->prepare();
        foreach (['total_minor' => 1, 'unit_price_minor' => 1, 'currency' => 'USD', 'user_id' => 1, 'status' => 'paid', 'number' => 'FAKE', 'items' => []] as $key => $value) {
            $this->post('/checkout', $this->payload([$key => $value]))->assertSessionHasErrors('checkout');
        }
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_only_authenticated_customer_is_associated_without_email_linking(): void
    {
        $user = User::factory()->create(['email' => 'guest@example.test']);
        $this->prepare();
        $guestOrder = $this->place();
        $this->assertNull($guestOrder->user_id);
        $this->actingAs($user);
        $this->prepare();
        $this->get('/checkout?canton_code=101')->assertInertia(fn (Assert $p) => $p->where('prefill.email', $user->email));
        $order = $this->place();
        $this->assertSame($user->id, $order->user_id);
        $this->assertNull($guestOrder->fresh()->user_id);
        $this->assertNotSame($order->number, $guestOrder->number);
    }

    public function test_operator_and_admin_checkout_do_not_associate_customer_ownership(): void
    {
        foreach ([Role::Operator, Role::Admin] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->prepare();
            $this->assertNull($this->place()->user_id);
        }
    }

    public function test_double_confirmation_is_idempotent_and_cannot_clear_new_cart(): void
    {
        $this->prepare();
        $payload = $this->payload();
        $order = $this->place();
        $this->post('/checkout', $payload)->assertRedirect('/checkout/confirmation/'.$order->number);
        $this->get('/checkout/confirmation/'.$order->number)->assertOk();
        $this->assertDatabaseCount('orders', 1);
        $this->prepare();
        $this->post('/checkout', $payload)->assertRedirect('/checkout/confirmation/'.$order->number);
        $this->assertCount(1, session('shopping_cart.items'));
        $this->assertDatabaseCount('order_status_history', 1);
    }

    public function test_reused_confirmation_with_other_buyer_is_rejected(): void
    {
        $this->prepare();
        $payload = $this->payload();
        $this->place();
        $this->post('/checkout', [...$payload, 'email' => 'other@example.test'])->assertSessionHasErrors('checkout');
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_other_session_cannot_read_confirmation_or_replay_known_token(): void
    {
        $this->prepare();
        $payload = $this->payload();
        $order = $this->place();
        $this->flushSession();
        $response = $this->get('/checkout/confirmation/'.$order->number)->assertNotFound();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->post('/checkout', $payload)->assertNotFound();
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_confirmation_exposes_only_allowlisted_snapshot_and_is_private(): void
    {
        $this->prepare();
        $order = $this->place();
        $response = $this->get('/checkout/confirmation/'.$order->number)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $public = $response->viewData('page')['props']['order'];
        $this->assertSame(['number', 'created_at', 'buyer', 'status', 'status_label', 'currency', 'subtotal_minor', 'shipping', 'total_minor', 'tax', 'items', 'address'], array_keys($public));
        $this->assertSame(['name', 'sku', 'quantity', 'unit_price_minor', 'subtotal_minor', 'currency', 'is_demo'], array_keys($public['items'][0]));
        $this->assertTrue($response->viewData('page')['encryptHistory']);
    }

    public function test_audit_contains_no_contact_or_address_data(): void
    {
        $this->prepare();
        $order = $this->place();
        $log = AuditLog::where('event', 'order.created')->sole();
        $this->assertSame($order->id, $log->metadata['entity_id']);
        $this->assertSame(['entity_type', 'entity_id', 'to_status'], array_keys($log->metadata));
        $this->assertStringNotContainsString('guest@example.test', $log->toJson());
        $this->assertStringNotContainsString('ficticia', $log->toJson());
    }

    public function test_transaction_failure_rolls_back_order_and_preserves_cart(): void
    {
        $this->prepare();
        AuditLog::creating(function ($log) {
            if ($log->event === 'order.created') {
                throw new \RuntimeException('Synthetic failure');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->post('/checkout', $this->payload());
            $this->fail('Expected transaction failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic failure', $exception->getMessage());
        } finally {
            AuditLog::flushEventListeners();
        }
        foreach (['orders', 'order_items', 'order_addresses', 'order_status_history'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertCount(1, session('shopping_cart.items'));
    }

    public function test_admin_and_operator_can_read_but_customers_and_guests_cannot(): void
    {
        $this->prepare();
        $order = $this->place();
        $this->get('/admin/orders')->assertRedirect('/login');
        $this->actingAs(User::factory()->create());
        $this->get('/admin/orders')->assertForbidden();
        $this->get('/admin/orders/'.$order->number)->assertForbidden();
        foreach ([Role::Admin, Role::Operator] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get('/admin/orders')->assertOk()->assertInertia(fn (Assert $p) => $p->where('orders.data.0.number', $order->number));
            $this->get('/admin/orders/'.$order->number)->assertOk();
            $this->patch('/admin/orders/'.$order->number, ['status' => 'paid'])->assertStatus(405);
            $this->delete('/admin/orders/'.$order->number)->assertStatus(405);
        }
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_failed_validation_does_not_flash_personal_data(): void
    {
        $this->prepare();
        $this->post('/checkout', $this->payload(['phone' => 'bad']))->assertSessionHasErrors('phone');
        foreach (['first_name', 'last_name', 'email', 'phone', 'exact_address', 'additional', 'token'] as $key) {
            $this->assertArrayNotHasKey($key, session('_old_input', []));
        }
    }

    public function test_customer_without_verified_email_can_order_and_logout_revokes_confirmation(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);
        $this->prepare();
        $order = $this->place();
        $this->assertSame($user->id, $order->user_id);
        $this->post('/logout')->assertRedirect('/login');
        $this->get('/checkout/confirmation/'.$order->number)->assertNotFound();
    }

    public function test_hidden_brand_and_category_block_confirmation(): void
    {
        $product = $this->prepare();
        $product->brand->forceFill(['status' => 'archived'])->save();
        $this->post('/checkout', $this->payload())->assertSessionHasErrors('checkout');
        $product->brand->forceFill(['status' => 'published'])->save();
        $product->category->forceFill(['status' => 'draft'])->save();
        $this->post('/checkout', $this->payload())->assertSessionHasErrors('checkout');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_database_exception_does_not_expose_sql_or_personal_data(): void
    {
        $this->prepare();
        Log::spy();
        $this->mock(CheckoutService::class)->shouldReceive('confirm')->once()->andThrow(new QueryException('sqlite', 'insert into orders (email) values (?)', ['private@example.test'], new \PDOException('Synthetic SQL error')));
        $response = $this->post('/checkout', $this->payload())->assertStatus(503);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $response->assertDontSee('private@example.test')->assertDontSee('insert into orders');
        Log::shouldHaveReceived('error')->once()->with('Order database operation failed.', ['sqlstate' => null]);
        $this->assertCount(1, session('shopping_cart.items'));
    }
}

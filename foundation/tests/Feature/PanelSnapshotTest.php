<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Audit;
use Carbon\CarbonImmutable;
use Database\Seeders\DeliveryZonesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The panel's context: what the catalogue looks like, how orders moved day by day and what happened
 * recently. Days are Costa Rica days, because that is the day the operator is living.
 */
class PanelSnapshotTest extends TestCase
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

    /** Moves an order in time: both the order itself and the history row the panel reads. */
    private function createdAt(Order $order, CarbonImmutable $at): void
    {
        DB::table('orders')->where('id', $order->id)->update(['created_at' => $at]);
        DB::table('order_status_history')->where('order_id', $order->id)->where('to_status', OrderStatus::PendingPayment->value)->update(['created_at' => $at]);
    }

    private function paidAt(Order $order, CarbonImmutable $at): void
    {
        DB::table('orders')->where('id', $order->id)->update(['status' => OrderStatus::Paid->value]);
        DB::table('order_status_history')->insert(['order_id' => $order->id, 'from_status' => OrderStatus::PendingPayment->value, 'to_status' => OrderStatus::Paid->value, 'actor_id' => null, 'created_at' => $at]);
    }

    private function panel(string $query = ''): array
    {
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));

        return $this->get('/admin?'.$query)->assertOk()->viewData('page')['props'];
    }

    public function test_the_catalogue_counts_say_what_the_three_catalogue_screens_say(): void
    {
        Product::factory()->count(2)->create(['status' => 'published', 'is_demo' => false]);
        Product::factory()->create(['status' => 'draft', 'is_demo' => false]);
        Product::factory()->create(['status' => 'archived', 'is_demo' => false]);
        Product::factory()->count(3)->create(['status' => 'published', 'is_demo' => true]);
        Supplier::create(['code' => 'uno', 'name' => 'Uno', 'active' => true]);
        Supplier::create(['code' => 'dos', 'name' => 'Dos', 'active' => false]);

        $snapshot = $this->panel()['snapshot'];
        $this->assertSame(['published' => 2, 'draft' => 1, 'archived' => 1, 'demo' => 3], $snapshot['catalogue']);
        $this->assertSame(['total' => 2, 'active' => 1], $snapshot['suppliers']);
    }

    public function test_the_series_covers_every_day_of_the_range_including_empty_ones(): void
    {
        $series = $this->panel('dias=14')['snapshot']['series'];
        $this->assertCount(14, $series);
        $this->assertSame(0, collect($series)->sum('created'));
        $this->assertSame(CarbonImmutable::now('America/Costa_Rica')->toDateString(), end($series)['date']);
        // Thirty days is the other range the panel offers.
        $this->assertCount(30, $this->panel('dias=30')['snapshot']['series']);
    }

    public function test_a_day_is_a_costa_rica_day_not_a_utc_one(): void
    {
        // 23:30 in Costa Rica is already the next day in UTC: the panel must still count it today.
        $lateNight = CarbonImmutable::now('America/Costa_Rica')->subDays(2)->setTime(23, 30);
        $this->createdAt($this->order(), $lateNight->utc());

        $series = collect($this->panel()['snapshot']['series'])->keyBy('date');
        $this->assertSame(1, $series[$lateNight->toDateString()]['created']);
        $this->assertSame(0, $series[$lateNight->addDay()->toDateString()]['created']);
    }

    public function test_orders_are_counted_on_the_day_they_were_created_and_the_day_they_were_paid(): void
    {
        $now = CarbonImmutable::now('America/Costa_Rica');
        $order = $this->order();
        $this->createdAt($order, $now->subDays(5)->setTime(10, 0)->utc());
        $this->paidAt($order, $now->subDay()->setTime(9, 0)->utc());

        $series = collect($this->panel()['snapshot']['series'])->keyBy('date');
        $this->assertSame([1, 0], [$series[$now->subDays(5)->toDateString()]['created'], $series[$now->subDays(5)->toDateString()]['paid']]);
        $this->assertSame([0, 1], [$series[$now->subDay()->toDateString()]['created'], $series[$now->subDay()->toDateString()]['paid']]);
    }

    public function test_what_is_older_than_the_range_stays_out(): void
    {
        $this->createdAt($this->order(), CarbonImmutable::now('America/Costa_Rica')->subDays(20)->utc());
        $this->assertSame(0, collect($this->panel('dias=14')['snapshot']['series'])->sum('created'));
        $this->assertSame(1, collect($this->panel('dias=30')['snapshot']['series'])->sum('created'));
    }

    public function test_the_confirmed_total_is_reported_by_currency(): void
    {
        $now = CarbonImmutable::now('America/Costa_Rica');
        $first = $this->order();
        $second = $this->order();
        $this->paidAt($first, $now->subDay()->utc());
        $this->paidAt($second, $now->subDays(2)->utc());
        // Paid before the range: counted nowhere.
        $old = $this->order();
        $this->paidAt($old, $now->subDays(20)->utc());

        $paid = $this->panel('dias=14')['snapshot']['paid'];
        $this->assertSame(2, $paid['orders']);
        $this->assertSame([['currency' => 'CRC', 'total_minor' => $first->total_minor + $second->total_minor]], $paid['totals']);
    }

    public function test_the_panel_carries_the_last_six_things_that_happened(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin, 'name' => 'Quien Actúa']);
        foreach (range(1, 8) as $index) {
            Audit::record('catalog.updated', $admin->id, metadata: ['entity_type' => 'products', 'entity_id' => $index]);
        }

        $activity = $this->panel()['snapshot']['activity'];
        $this->assertCount(6, $activity);
        // Newest first, named like the audit screen does.
        $this->assertSame(8, $activity[0]['metadata']['entity_id']);
        $this->assertSame('Quien Actúa', $activity[0]['actor']['name']);
    }

    public function test_reading_a_page_is_not_activity_worth_a_summary_line(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        // Opening orders is logged for the audit trail, but it would crowd out six lines of real work.
        foreach (range(1, 8) as $index) {
            Audit::record('order.viewed', $admin->id, metadata: ['entity_type' => 'order', 'entity_id' => (string) $index]);
        }
        foreach (range(1, 6) as $index) {
            Audit::record('catalog.updated', $admin->id, metadata: ['entity_type' => 'products', 'entity_id' => $index]);
        }
        foreach (range(1, 3) as $index) {
            Audit::record('order.viewed', $admin->id, metadata: ['entity_type' => 'order', 'entity_id' => 'x'.$index]);
        }

        $activity = $this->panel()['snapshot']['activity'];
        // Filtered before the limit, so six real lines survive a browsing spree.
        $this->assertCount(6, $activity);
        $this->assertSame(['catalog.updated'], collect($activity)->pluck('event')->unique()->values()->all());
    }

    public function test_an_unknown_range_is_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        $this->get('/admin?dias=7')->assertSessionHasErrors('dias');
    }
}

<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Support\DatabaseFailure;
use Database\Seeders\DeliveryZonesSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PDOException;
use Tests\TestCase;

class DatabaseFailureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /** A QueryException shaped the way PDO raises it: SQLSTATE, driver code and driver message. */
    private function failure(string $sqlstate, ?int $driverCode, string $message): QueryException
    {
        $pdo = new PDOException($message);
        if ($driverCode !== null) {
            $pdo->errorInfo = [$sqlstate, $driverCode, $message];
        }

        return new QueryException('mysql', 'select 1', [], $pdo);
    }

    public function test_a_missing_table_is_permanent(): void
    {
        $f = DatabaseFailure::of($this->failure('42S02', 1146, "Table 'tech_commerce.delivery_zone_cantons' doesn't exist"));
        $this->assertFalse($f->transient);
        $this->assertSame('42S02', $f->sqlstate);
        $this->assertSame(1146, $f->driverCode);
    }

    public function test_a_refused_connection_is_transient_even_when_windows_says_it_in_spanish(): void
    {
        // The exact message the local log recorded when MySQL was down; Laravel matches English text only.
        $message = 'SQLSTATE[HY000] [2002] No se puede establecer una conexión ya que el equipo de destino denegó expresamente dicha conexión';
        $f = DatabaseFailure::of($this->failure('HY000', null, $message));
        $this->assertTrue($f->transient);
        $this->assertSame(2002, $f->driverCode);
    }

    public function test_locks_deadlocks_and_lost_connections_are_transient(): void
    {
        foreach ([
            ['HY000', 1205, 'Lock wait timeout exceeded; try restarting transaction'],
            ['40001', 1213, 'Deadlock found when trying to get lock; try restarting transaction'],
            ['HY000', 2006, 'MySQL server has gone away'],
            ['HY000', 2013, 'Lost connection to MySQL server during query'],
            ['08S01', 1053, 'Server shutdown in progress'],
        ] as [$state, $code, $message]) {
            $this->assertTrue(DatabaseFailure::of($this->failure($state, $code, $message))->transient, $message);
        }
    }

    public function test_anything_unrecognised_is_treated_as_permanent(): void
    {
        foreach ([['42S22', 1054, "Unknown column 'x'"], ['23000', 1062, 'Duplicate entry'], ['HY000', 1, 'no such table: orders']] as [$state, $code, $message]) {
            $this->assertFalse(DatabaseFailure::of($this->failure($state, $code, $message))->transient, $message);
        }
    }

    public function test_a_permanent_checkout_failure_answers_500_and_says_retrying_will_not_help(): void
    {
        Log::spy();
        $this->seed(DeliveryZonesSeeder::class);
        $product = Product::factory()->sellable()->create(['status' => 'published', 'currency' => 'CRC']);
        $this->post('/cart/items', ['mutation_id' => (string) Str::uuid(), 'revision' => 0, 'product_slug' => $product->slug, 'quantity' => 1]);
        // The real bug of 2026-09-21: the delivery tables were never migrated.
        Schema::drop('delivery_zone_cantons');

        $response = $this->get('/checkout?canton_code=101');
        $response->assertStatus(500);
        $this->assertStringContainsString('Reintentar no lo va a resolver', $response->getContent());
        $this->assertStringContainsString('Tu carrito se conserva', $response->getContent());
        Log::shouldHaveReceived('error')->withArgs(fn ($message, $context) => $message === 'Order database operation failed.' && $context['transient'] === false && array_key_exists('driver_code', $context));
    }

    public function test_a_transient_checkout_failure_answers_503_and_invites_a_retry(): void
    {
        Log::spy();
        Route::middleware('web')->get('/checkout/fallo-simulado', fn () => throw $this->failure('HY000', 2002, 'SQLSTATE[HY000] [2002] Connection refused'));

        $response = $this->get('/checkout/fallo-simulado');
        $response->assertStatus(503);
        $this->assertStringContainsString('probá de nuevo en un minuto', $response->getContent());
        Log::shouldHaveReceived('error')->withArgs(fn ($message, $context) => $context === ['sqlstate' => 'HY000', 'driver_code' => 2002, 'transient' => true]);
    }

    public function test_the_operator_sees_the_driver_code_and_no_mention_of_a_cart(): void
    {
        // Two segments, so it cannot be taken by the real /admin/orders/{number} route.
        Route::middleware('web')->get('/admin/orders/fallo/simulado', fn () => throw $this->failure('42S02', 1146, "Table 'x' doesn't exist"));

        $response = $this->get('/admin/orders/fallo/simulado');
        $response->assertStatus(500);
        $this->assertStringContainsString('1146', $response->getContent());
        $this->assertStringNotContainsString('carrito', $response->getContent());
    }

    public function test_nothing_sensitive_reaches_the_log(): void
    {
        Log::spy();
        Route::middleware('web')->get('/checkout/fallo-sensible', fn () => throw new QueryException('mysql', 'insert into orders (email) values (?)', ['ana@example.test'], tap(new PDOException('boom'), fn ($e) => $e->errorInfo = ['23000', 1062, 'Duplicate entry ana@example.test'])));

        $this->get('/checkout/fallo-sensible')->assertStatus(500);
        Log::shouldHaveReceived('error')->withArgs(fn ($message, $context) => ! str_contains(json_encode($context), 'ana@example.test') && ! str_contains(json_encode($context), 'insert into'));
    }
}

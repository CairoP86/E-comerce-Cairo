# V1-C · Cotización de entrega y total final — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Que el checkout cotice el envío según el cantón de destino, lo muestre antes de confirmar y persista envío y total final como snapshot del pedido, calculados siempre por el servidor.

**Architecture:** Tres tablas aditivas guardan la pertenencia de cada cantón a una zona y las tarifas por zona, versionadas por conjunto. `DeliveryQuoter` resuelve zona, tarifa y envío gratis a partir del cantón y del subtotal, y es la única fuente del monto. `CheckoutService` incorpora esa cotización a la revisión (que el cliente refresca al elegir cantón) y la recalcula bajo bloqueo al confirmar, guardando envío, zona y versión de tarifas en el pedido.

**Tech Stack:** Laravel 13 / PHP 8.4, Eloquent, PHPUnit 12 (SQLite en memoria), Inertia 3 + Vue 3 + TypeScript.

**Fuentes de verdad:** decisiones del propietario del 2026-09-19 (abajo), [ADR-007](../../adr/007-delivery.md), [ADR-008](../../adr/008-final-total.md), [V1-ROADMAP.md](../../V1-ROADMAP.md).

## Global Constraints

- Todo el trabajo ocurre dentro de `foundation/`. Las rutas de este plan son relativas a `foundation/` salvo que digan lo contrario.
- **No hacer commit ni push.** Cada tarea cierra con un punto de control (`git status --short`, `git diff --stat`); el propietario revisa por lotes.
- Migraciones **solo aditivas**. No se modifica ninguna fila ni columna existente; los pedidos anteriores no se recalculan (ADR-008).
- **Tarifas aprobadas** (tarifa plana más envío gratis por monto mínimo, en colones y en unidades menores):
  - **GAM:** ₡3,500 (`350000`), gratis desde ₡90,000 (`9000000`).
  - **Guanacaste norte** (Liberia 501, Bagaces 504, Cañas 506, La Cruz 510): **gratis siempre** (`0`, sin umbral).
  - **Resto del país:** ₡4,500 (`450000`), gratis desde ₡110,000 (`11000000`).
- **Cantones GAM (31), verificados uno a uno contra `database/data/cr-territories-2026.json`:** 101, 102, 103, 106, 107, 108, 109, 110, 111, 113, 114, 115, 118 (San José); 201, 205, 208 (Alajuela); 301, 302, 303, 306, 307, 308 (Cartago); 401, 402, 403, 404, 405, 406, 407, 408, 409 (Heredia).
- **Cobertura exhaustiva:** los 84 cantones del catálogo reciben zona explícita; 31 GAM, 4 Guanacaste norte, 49 resto. Un cantón fuera del catálogo **bloquea** la cotización, nunca se asume una zona.
- **El umbral de envío gratis se mide contra el subtotal de productos**, antes de sumar el envío.
- **Solo CRC.** La confirmación de un carrito en otra moneda se bloquea con un mensaje claro; las ventas corporativas en dólares se atienden fuera de la plataforma.
- **Sin impuestos.** ADR-008 deja el IVA expresamente pendiente: el total es subtotal más envío, sin línea fiscal inventada.
- **Sin integración con transportistas.** Las tarifas son fijas y locales; no hay clientes HTTP, ni APIs, ni cotización dinámica externa.
- **Fuera de alcance:** V1-D (núcleo de órdenes), V1-E (pagos, incluida la captura y los pagos tardíos) y V1-F (fulfillment real, modos `direct_supplier` / `via_operation`). Tampoco se toca el vencimiento de las reservas de V1-B.
- El envío se calcula y se muestra **antes** de confirmar el pedido.
- Comandos de verificación: `php artisan test`, `npm run check`, `php vendor/bin/pint --test` desde `foundation/`. El `.env` local ya tiene `COMMERCE_AVAILABILITY_TTL_MINUTES`.

## Cambios de comportamiento que tocan assertions existentes

A diferencia de V1-B, aquí **el total del pedido cambia por diseño**, así que algunas assertions existentes dejan de ser correctas. Se actualizan con su valor nuevo, ninguna se elimina, y la Tarea 3 las lista una por una con el cálculo. Todas usan el cantón 101 (GAM) y subtotales por debajo de ₡90,000, así que suman ₡3,500 (`350000`):

| Archivo | Assertion | Antes | Después |
| --- | --- | --- | --- |
| `CheckoutTest.php` | `$order->total_minor` en `test_guest_order_has_snapshots_initial_status_history_and_no_user` | 246912 | 596912 |
| `CheckoutTest.php` | `order.total_minor` en `test_product_changes_do_not_change_historical_order` | 246912 | 596912 |
| `CheckoutTest.php` | `$this->place()->total_minor` en `test_changed_price_requires_explicit_review_before_confirmation` | 400000 | 750000 |
| `CheckoutTest.php` | claves de `publicSummary` en `test_confirmation_exposes_only_allowlisted_snapshot_and_is_private` | sin envío | con `shipping` |
| `CheckoutTest.php` | `test_currency_change_is_blocked_and_usd_order_remains_usd` | el pedido en USD se crea | el carrito en USD **no se puede confirmar** (decisión b) |
| `CommercialCatalogTest.php` | `$order->total_minor` en `test_public_catalog_cart_checkout_and_order_never_expose_offers_or_use_cost` | 1000000 | 1350000 |

## Mapa de archivos

| Archivo | Responsabilidad | Tarea |
| --- | --- | --- |
| `database/migrations/2026_09_20_000001_create_delivery_rates.php` | Tablas `delivery_rate_sets`, `delivery_rates`, `delivery_zone_cantons` | 1 |
| `app/Delivery/DeliveryZone.php` | Enum de zonas con etiqueta pública | 1 |
| `app/Models/DeliveryRateSet.php`, `DeliveryRate.php`, `DeliveryZoneCanton.php` | Acceso a tarifas y zonas | 1 |
| `database/seeders/DeliveryZonesSeeder.php` | Zonas de los 84 cantones y tarifas aprobadas, idempotente | 1 |
| `app/Delivery/DeliveryQuote.php`, `DeliveryUnavailable.php`, `DeliveryQuoter.php` | Cotización autoritativa | 2 |
| `app/Services/CheckoutService.php` | Revisión con destino, confirmación bajo bloqueo, snapshot | 3 |
| `app/Http/Controllers/CheckoutController.php`, `app/Http/Requests/ConfirmCheckoutRequest.php` | Cantón opcional en la revisión | 3 |
| `database/migrations/2026_09_20_000002_add_shipping_to_orders.php`, `app/Models/Order.php` | `shipping_minor`, `shipping_zone`, `delivery_rate_set_id` y `publicSummary` | 3 |
| `resources/js/pages/checkout/Index.vue`, `components/storefront/OrderSummary.vue`, `OrderDetails.vue`, `types/order.ts` | Envío visible antes de confirmar y en el detalle | 4 |
| `docs/V1-C-REPORT.md` | Reporte de fase | 5 |

## Lotes de revisión

| Lote | Tareas | Resultado revisable |
| --- | --- | --- |
| 1 | 1–2 | Zonas y tarifas versionadas; cotizador con todas sus reglas |
| 2 | 3 | Checkout cotiza, confirma y guarda envío y total |
| 3 | 4–5 | UI del envío antes de confirmar, reporte y verificación final |

---

### Task 1: Zonas y tarifas versionadas

**Files:**
- Create: `database/migrations/2026_09_20_000001_create_delivery_rates.php`
- Create: `app/Delivery/DeliveryZone.php`
- Create: `app/Models/DeliveryRateSet.php`, `app/Models/DeliveryRate.php`, `app/Models/DeliveryZoneCanton.php`
- Create: `database/seeders/DeliveryZonesSeeder.php`
- Test: `tests/Feature/DeliveryZonesSeederTest.php`

**Interfaces:**
- Produces:
  - `enum App\Delivery\DeliveryZone: string { Gam = 'gam', GuanacasteNorte = 'guanacaste_norte', Rest = 'rest' }` con `label(): string`.
  - Modelos `DeliveryRateSet` (`version`, `effective_from`, `notes`, relación `rates()`), `DeliveryRate` (`delivery_rate_set_id`, `zone` casteado a `DeliveryZone`, `flat_minor`, `free_from_minor`, `currency`), `DeliveryZoneCanton` (clave `canton_code`, `zone`).
  - `DeliveryZonesSeeder::VERSION = 'v1-2026-09'`, `DeliveryZonesSeeder::GAM_CANTONS` y `::GUANACASTE_NORTE_CANTONS` (arrays de `canton_code`).

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Feature/DeliveryZonesSeederTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Delivery\DeliveryZone;
use App\Models\DeliveryRate;
use App\Models\DeliveryRateSet;
use App\Models\DeliveryZoneCanton;
use App\Support\CostaRicaTerritories;
use Database\Seeders\DeliveryZonesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryZonesSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DeliveryZonesSeeder::class);
    }

    private function cantonCodes(): array
    {
        return array_values(array_unique(array_column(CostaRicaTerritories::all(), 'canton_code')));
    }

    public function test_every_canton_of_the_official_catalogue_has_exactly_one_zone(): void
    {
        $codes = $this->cantonCodes();
        $this->assertCount(84, $codes);
        $this->assertSame(count($codes), DeliveryZoneCanton::count());
        foreach ($codes as $code) {
            $this->assertNotNull(DeliveryZoneCanton::find($code), 'Cantón sin zona: '.$code);
        }
    }

    public function test_the_approved_canton_lists_are_seeded_exactly(): void
    {
        $inZone = fn (DeliveryZone $zone) => DeliveryZoneCanton::where('zone', $zone)->orderBy('canton_code')->pluck('canton_code')->all();
        $gam = ['101', '102', '103', '106', '107', '108', '109', '110', '111', '113', '114', '115', '118',
            '201', '205', '208', '301', '302', '303', '306', '307', '308',
            '401', '402', '403', '404', '405', '406', '407', '408', '409'];
        sort($gam);
        $this->assertSame($gam, $inZone(DeliveryZone::Gam));
        $this->assertCount(31, $gam);
        $this->assertSame(['501', '504', '506', '510'], $inZone(DeliveryZone::GuanacasteNorte));
        $this->assertCount(49, $inZone(DeliveryZone::Rest));
    }

    public function test_the_approved_rates_are_seeded_for_the_active_version(): void
    {
        $set = DeliveryRateSet::where('version', DeliveryZonesSeeder::VERSION)->sole();
        $rate = fn (DeliveryZone $zone) => DeliveryRate::where('delivery_rate_set_id', $set->id)->where('zone', $zone)->sole();
        $this->assertSame([350000, 9000000, 'CRC'], [$rate(DeliveryZone::Gam)->flat_minor, $rate(DeliveryZone::Gam)->free_from_minor, $rate(DeliveryZone::Gam)->currency]);
        $this->assertSame([0, null, 'CRC'], [$rate(DeliveryZone::GuanacasteNorte)->flat_minor, $rate(DeliveryZone::GuanacasteNorte)->free_from_minor, $rate(DeliveryZone::GuanacasteNorte)->currency]);
        $this->assertSame([450000, 11000000, 'CRC'], [$rate(DeliveryZone::Rest)->flat_minor, $rate(DeliveryZone::Rest)->free_from_minor, $rate(DeliveryZone::Rest)->currency]);
        $this->assertSame(3, DeliveryRate::count());
    }

    public function test_seeding_twice_changes_nothing(): void
    {
        $this->seed(DeliveryZonesSeeder::class);
        $this->assertSame(1, DeliveryRateSet::count());
        $this->assertSame(3, DeliveryRate::count());
        $this->assertSame(84, DeliveryZoneCanton::count());
    }

    public function test_zone_labels_are_public_facing_spanish(): void
    {
        $this->assertSame('Gran Área Metropolitana', DeliveryZone::Gam->label());
        $this->assertSame('Guanacaste norte', DeliveryZone::GuanacasteNorte->label());
        $this->assertSame('Resto del país', DeliveryZone::Rest->label());
    }
}
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `php artisan test --filter=DeliveryZonesSeederTest`
Expected: FAIL (`Class "Database\Seeders\DeliveryZonesSeeder" not found`).

- [ ] **Step 3: Migración**

Crear `database/migrations/2026_09_20_000001_create_delivery_rates.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Additive: no existing table or row is modified.
        Schema::create('delivery_rate_sets', function (Blueprint $table) {
            $table->id();
            $table->string('version', 40)->unique();
            $table->timestamp('effective_from')->index();
            $table->string('notes', 255)->nullable();
            $table->timestamps();
        });
        Schema::create('delivery_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_rate_set_id')->constrained()->restrictOnDelete();
            $table->string('zone', 32);
            // Flat fee charged to the buyer; the real logistics cost is a different concept (ADR-007).
            $table->unsignedBigInteger('flat_minor');
            // Subtotal from which delivery is free. Null means the flat fee always applies.
            $table->unsignedBigInteger('free_from_minor')->nullable();
            $table->char('currency', 3);
            $table->timestamps();
            $table->unique(['delivery_rate_set_id', 'zone']);
        });
        Schema::create('delivery_zone_cantons', function (Blueprint $table) {
            $table->char('canton_code', 3)->primary();
            $table->string('zone', 32)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_zone_cantons');
        Schema::dropIfExists('delivery_rates');
        Schema::dropIfExists('delivery_rate_sets');
    }
};
```

- [ ] **Step 4: Enum y modelos**

Crear `app/Delivery/DeliveryZone.php`:

```php
<?php

namespace App\Delivery;

enum DeliveryZone: string
{
    case Gam = 'gam';
    case GuanacasteNorte = 'guanacaste_norte';
    case Rest = 'rest';

    public function label(): string
    {
        return match ($this) {
            self::Gam => 'Gran Área Metropolitana',
            self::GuanacasteNorte => 'Guanacaste norte',
            self::Rest => 'Resto del país',
        };
    }
}
```

Crear `app/Models/DeliveryRateSet.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A versioned set of delivery rates. Orders keep the set they were quoted with (ADR-008). */
class DeliveryRateSet extends Model
{
    protected $fillable = ['version', 'effective_from', 'notes'];

    protected function casts(): array
    {
        return ['effective_from' => 'datetime'];
    }

    public function rates()
    {
        return $this->hasMany(DeliveryRate::class);
    }
}
```

Crear `app/Models/DeliveryRate.php`:

```php
<?php

namespace App\Models;

use App\Delivery\DeliveryZone;
use Illuminate\Database\Eloquent\Model;

class DeliveryRate extends Model
{
    protected $fillable = ['delivery_rate_set_id', 'zone', 'flat_minor', 'free_from_minor', 'currency'];

    protected function casts(): array
    {
        return ['zone' => DeliveryZone::class, 'flat_minor' => 'integer', 'free_from_minor' => 'integer'];
    }
}
```

Crear `app/Models/DeliveryZoneCanton.php`:

```php
<?php

namespace App\Models;

use App\Delivery\DeliveryZone;
use Illuminate\Database\Eloquent\Model;

/** Which delivery zone a canton belongs to. Every canton of the official catalogue has a row. */
class DeliveryZoneCanton extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $primaryKey = 'canton_code';

    protected $fillable = ['canton_code', 'zone'];

    protected function casts(): array
    {
        return ['zone' => DeliveryZone::class];
    }
}
```

- [ ] **Step 5: Seeder idempotente**

Crear `database/seeders/DeliveryZonesSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Delivery\DeliveryZone;
use App\Models\DeliveryRate;
use App\Models\DeliveryRateSet;
use App\Models\DeliveryZoneCanton;
use App\Support\CostaRicaTerritories;
use Illuminate\Database\Seeder;

/**
 * Approved delivery zones and rates (owner decision, 2026-09-19). Idempotent and additive:
 * it never deletes rows and never touches orders. Rates are versioned so past orders keep theirs.
 */
class DeliveryZonesSeeder extends Seeder
{
    public const VERSION = 'v1-2026-09';

    /** The 31 GAM cantons, verified against database/data/cr-territories-2026.json. */
    public const GAM_CANTONS = [
        '101', '102', '103', '106', '107', '108', '109', '110', '111', '113', '114', '115', '118',
        '201', '205', '208',
        '301', '302', '303', '306', '307', '308',
        '401', '402', '403', '404', '405', '406', '407', '408', '409',
    ];

    /** Liberia, Bagaces, Cañas and La Cruz: delivery is always free. */
    public const GUANACASTE_NORTE_CANTONS = ['501', '504', '506', '510'];

    public function run(): void
    {
        foreach (array_unique(array_column(CostaRicaTerritories::all(), 'canton_code')) as $canton) {
            $zone = match (true) {
                in_array($canton, self::GAM_CANTONS, true) => DeliveryZone::Gam,
                in_array($canton, self::GUANACASTE_NORTE_CANTONS, true) => DeliveryZone::GuanacasteNorte,
                default => DeliveryZone::Rest,
            };
            DeliveryZoneCanton::updateOrCreate(['canton_code' => $canton], ['zone' => $zone]);
        }
        $set = DeliveryRateSet::firstOrCreate(
            ['version' => self::VERSION],
            ['effective_from' => '2026-09-19 00:00:00', 'notes' => 'Tarifa plana con envío gratis por monto mínimo; estudio de mercado 2026-09.']
        );
        foreach ([
            [DeliveryZone::Gam, 350000, 9000000],
            [DeliveryZone::GuanacasteNorte, 0, null],
            [DeliveryZone::Rest, 450000, 11000000],
        ] as [$zone, $flat, $freeFrom]) {
            DeliveryRate::updateOrCreate(
                ['delivery_rate_set_id' => $set->id, 'zone' => $zone],
                ['flat_minor' => $flat, 'free_from_minor' => $freeFrom, 'currency' => 'CRC']
            );
        }
    }
}
```

- [ ] **Step 6: Correr el test y verificar que pasa**

Run: `php artisan test --filter=DeliveryZonesSeederTest`
Expected: PASS, 5 tests.

- [ ] **Step 7: Suite completa y estilo**

Run: `php artisan test && php vendor/bin/pint --test`
Expected: 167 tests (162 + 5), 0 failures; Pint passed. Nada visible cambia todavía.

- [ ] **Step 8: Punto de control (sin commit)**

Run: `git status --short && git diff --stat`
Expected: solo los archivos de esta tarea. `DatabaseSeeder` no se toca: el seeder se ejecuta explícitamente, nunca en el flujo de release.

### Task 2: Cotizador autoritativo

**Files:**
- Create: `app/Delivery/DeliveryQuote.php`, `app/Delivery/DeliveryUnavailable.php`, `app/Delivery/DeliveryQuoter.php`
- Test: `tests/Feature/DeliveryQuoterTest.php`

**Interfaces:**
- Consumes: `DeliveryZone`, `DeliveryZoneCanton`, `DeliveryRate`, `DeliveryRateSet`, `DeliveryZonesSeeder` (Task 1).
- Produces:
  - `final readonly class App\Delivery\DeliveryQuote(DeliveryZone $zone, int $amountMinor, bool $free, ?int $freeFromMinor, ?int $missingForFreeMinor, string $currency, int $rateSetId, string $rateSetVersion)` con `toPublic(): array` de claves `zone, zone_label, amount_minor, free, free_from_minor, missing_for_free_minor, currency`.
  - `App\Delivery\DeliveryUnavailable extends RuntimeException` (mensaje apto para el comprador).
  - `DeliveryQuoter::quote(string $cantonCode, int $subtotalMinor, string $currency): DeliveryQuote` y `DeliveryQuoter::activeSet(): DeliveryRateSet`.

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Feature/DeliveryQuoterTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Delivery\DeliveryQuoter;
use App\Delivery\DeliveryUnavailable;
use App\Delivery\DeliveryZone;
use App\Models\DeliveryRate;
use App\Models\DeliveryRateSet;
use Database\Seeders\DeliveryZonesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryQuoterTest extends TestCase
{
    use RefreshDatabase;

    private function quoter(): DeliveryQuoter
    {
        return app(DeliveryQuoter::class);
    }

    private function seedRates(): void
    {
        $this->seed(DeliveryZonesSeeder::class);
    }

    public function test_gam_charges_the_flat_fee_below_the_threshold(): void
    {
        $this->seedRates();
        $quote = $this->quoter()->quote('101', 8999999, 'CRC');
        $this->assertSame(DeliveryZone::Gam, $quote->zone);
        $this->assertSame(350000, $quote->amountMinor);
        $this->assertFalse($quote->free);
        $this->assertSame(9000000, $quote->freeFromMinor);
        $this->assertSame(1, $quote->missingForFreeMinor);
        $this->assertSame('CRC', $quote->currency);
    }

    public function test_gam_is_free_exactly_at_the_threshold_and_above(): void
    {
        $this->seedRates();
        foreach ([9000000, 9000001, 50000000] as $subtotal) {
            $quote = $this->quoter()->quote('102', $subtotal, 'CRC');
            $this->assertSame(0, $quote->amountMinor, 'subtotal '.$subtotal);
            $this->assertTrue($quote->free);
            $this->assertNull($quote->missingForFreeMinor);
        }
    }

    public function test_guanacaste_norte_is_always_free_even_for_a_tiny_order(): void
    {
        $this->seedRates();
        foreach (['501', '504', '506', '510'] as $canton) {
            $quote = $this->quoter()->quote($canton, 1, 'CRC');
            $this->assertSame(DeliveryZone::GuanacasteNorte, $quote->zone, $canton);
            $this->assertSame(0, $quote->amountMinor);
            $this->assertTrue($quote->free);
            $this->assertNull($quote->freeFromMinor);
            $this->assertNull($quote->missingForFreeMinor);
        }
    }

    public function test_the_rest_of_the_country_uses_its_own_fee_and_threshold(): void
    {
        $this->seedRates();
        $below = $this->quoter()->quote('706', 10999999, 'CRC');
        $this->assertSame(DeliveryZone::Rest, $below->zone);
        $this->assertSame(450000, $below->amountMinor);
        $this->assertSame(1, $below->missingForFreeMinor);
        $this->assertSame(0, $this->quoter()->quote('706', 11000000, 'CRC')->amountMinor);
        // Guanacaste outside the four approved cantons is not free.
        $this->assertSame(450000, $this->quoter()->quote('502', 100, 'CRC')->amountMinor);
    }

    public function test_an_unknown_canton_blocks_instead_of_guessing(): void
    {
        $this->seedRates();
        $this->expectException(DeliveryUnavailable::class);
        $this->quoter()->quote('999', 100000, 'CRC');
    }

    public function test_only_colones_are_quoted(): void
    {
        $this->seedRates();
        $this->expectException(DeliveryUnavailable::class);
        $this->expectExceptionMessage('colones');
        $this->quoter()->quote('101', 100000, 'USD');
    }

    public function test_without_configured_rates_it_blocks(): void
    {
        $this->expectException(DeliveryUnavailable::class);
        $this->quoter()->quote('101', 100000, 'CRC');
    }

    public function test_the_newest_effective_rate_set_wins(): void
    {
        $this->seedRates();
        $newer = DeliveryRateSet::create(['version' => 'v2-test', 'effective_from' => now()->subMinute(), 'notes' => 'QA']);
        DeliveryRate::create(['delivery_rate_set_id' => $newer->id, 'zone' => DeliveryZone::Gam, 'flat_minor' => 500000, 'free_from_minor' => null, 'currency' => 'CRC']);
        $quote = $this->quoter()->quote('101', 100000, 'CRC');
        $this->assertSame(500000, $quote->amountMinor);
        $this->assertSame($newer->id, $quote->rateSetId);
        $this->assertSame('v2-test', $quote->rateSetVersion);
    }

    public function test_a_future_rate_set_is_ignored_until_it_starts(): void
    {
        $this->seedRates();
        $future = DeliveryRateSet::create(['version' => 'v3-test', 'effective_from' => now()->addDay(), 'notes' => 'QA']);
        DeliveryRate::create(['delivery_rate_set_id' => $future->id, 'zone' => DeliveryZone::Gam, 'flat_minor' => 999999, 'free_from_minor' => null, 'currency' => 'CRC']);
        $this->assertSame(350000, $this->quoter()->quote('101', 100000, 'CRC')->amountMinor);
        $this->assertSame(DeliveryZonesSeeder::VERSION, $this->quoter()->activeSet()->version);
    }

    public function test_the_public_projection_exposes_only_buyer_facing_fields(): void
    {
        $this->seedRates();
        $public = $this->quoter()->quote('101', 100000, 'CRC')->toPublic();
        $this->assertSame(['zone', 'zone_label', 'amount_minor', 'free', 'free_from_minor', 'missing_for_free_minor', 'currency'], array_keys($public));
        $this->assertSame('Gran Área Metropolitana', $public['zone_label']);
    }
}
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `php artisan test --filter=DeliveryQuoterTest`
Expected: FAIL (`Class "App\Delivery\DeliveryQuoter" does not exist`).

- [ ] **Step 3: Valor, excepción y cotizador**

Crear `app/Delivery/DeliveryUnavailable.php`:

```php
<?php

namespace App\Delivery;

use RuntimeException;

/** Delivery cannot be quoted for this destination, currency or configuration. Message is buyer-safe. */
class DeliveryUnavailable extends RuntimeException {}
```

Crear `app/Delivery/DeliveryQuote.php`:

```php
<?php

namespace App\Delivery;

/**
 * One delivery quote: zone, amount charged to the buyer and the rate version it came from.
 * The amount is always decided by the server; the client never supplies it (ADR-008).
 */
final readonly class DeliveryQuote
{
    public function __construct(
        public DeliveryZone $zone,
        public int $amountMinor,
        public bool $free,
        public ?int $freeFromMinor,
        public ?int $missingForFreeMinor,
        public string $currency,
        public int $rateSetId,
        public string $rateSetVersion,
    ) {}

    /** Buyer-facing projection. Never exposes the rate set or internal identifiers. */
    public function toPublic(): array
    {
        return [
            'zone' => $this->zone->value,
            'zone_label' => $this->zone->label(),
            'amount_minor' => $this->amountMinor,
            'free' => $this->free,
            'free_from_minor' => $this->freeFromMinor,
            'missing_for_free_minor' => $this->missingForFreeMinor,
            'currency' => $this->currency,
        ];
    }
}
```

Crear `app/Delivery/DeliveryQuoter.php`:

```php
<?php

namespace App\Delivery;

use App\Models\DeliveryRate;
use App\Models\DeliveryRateSet;
use App\Models\DeliveryZoneCanton;

/**
 * Flat rates per zone with a free-delivery threshold (owner decision, 2026-09-19). No courier or
 * external API is called. The free threshold is measured against the product subtotal, before delivery.
 */
class DeliveryQuoter
{
    public function quote(string $cantonCode, int $subtotalMinor, string $currency): DeliveryQuote
    {
        if ($currency !== 'CRC') {
            throw new DeliveryUnavailable('Por ahora solo calculamos envíos para pedidos en colones. Escríbenos para coordinar una compra en otra moneda.');
        }
        $zone = DeliveryZoneCanton::find($cantonCode)?->zone;
        if (! $zone) {
            throw new DeliveryUnavailable('Todavía no tenemos una tarifa de envío para ese cantón. Escríbenos y la coordinamos contigo.');
        }
        $set = $this->activeSet();
        $rate = DeliveryRate::where('delivery_rate_set_id', $set->id)->where('zone', $zone)->first();
        if (! $rate || $rate->currency !== 'CRC') {
            throw new DeliveryUnavailable('No pudimos calcular el envío para tu destino. Inténtalo de nuevo más tarde.');
        }
        // Free when the zone has no fee at all, or when the product subtotal reaches its threshold.
        $free = $rate->flat_minor === 0 || ($rate->free_from_minor !== null && $subtotalMinor >= $rate->free_from_minor);

        return new DeliveryQuote(
            zone: $zone,
            amountMinor: $free ? 0 : $rate->flat_minor,
            free: $free,
            freeFromMinor: $rate->free_from_minor,
            missingForFreeMinor: $free || $rate->free_from_minor === null ? null : $rate->free_from_minor - $subtotalMinor,
            currency: 'CRC',
            rateSetId: $set->id,
            rateSetVersion: $set->version,
        );
    }

    /** The newest rate set already in force. Scheduled future sets do not apply yet. */
    public function activeSet(): DeliveryRateSet
    {
        $set = DeliveryRateSet::where('effective_from', '<=', now())->orderByDesc('effective_from')->orderByDesc('id')->first();
        if (! $set) {
            throw new DeliveryUnavailable('El cálculo de envío no está configurado. Avísanos antes de continuar con tu pedido.');
        }

        return $set;
    }
}
```

- [ ] **Step 4: Correr el test y verificar que pasa**

Run: `php artisan test --filter=DeliveryQuoterTest`
Expected: PASS, 10 tests.

- [ ] **Step 5: Suite completa y estilo**

Run: `php artisan test && php vendor/bin/pint --test`
Expected: 177 tests (167 + 10), 0 failures; Pint passed.

- [ ] **Step 6: Punto de control del Lote 1 (sin commit)**

Run: `git status --short && git diff --stat`
Expected: archivos de Tareas 1–2. Detenerse para revisión del propietario.

### Task 3: Checkout cotiza, confirma y guarda el total final

**Files:**
- Create: `database/migrations/2026_09_20_000002_add_shipping_to_orders.php`
- Modify: `app/Models/Order.php` (casts y `publicSummary`)
- Modify: `app/Services/CheckoutService.php` (`quote`, `review`, `confirm`)
- Modify: `app/Http/Controllers/CheckoutController.php` (`show` acepta cantón)
- Modify (assertions afectadas por el cambio de total): `tests/Feature/CheckoutTest.php`, `tests/Feature/CommercialCatalogTest.php`
- Test: `tests/Feature/DeliveryCheckoutTest.php`

**Interfaces:**
- Consumes: `DeliveryQuoter::quote(...)`, `DeliveryQuote::toPublic()`, `DeliveryUnavailable` (Task 2).
- Produces:
  - `CheckoutService::quote(bool $lock = false, ?string $cantonCode = null): array` con claves `items, currency, subtotal_minor, shipping (array|null), total_minor, revision`.
  - `CheckoutService::review(?string $cantonCode = null): array` (sin `revision`, sin `product_id`, con `token`).
  - Pedido: columnas `shipping_minor`, `shipping_zone`, `delivery_rate_set_id`; `Order::publicSummary()` con la clave `shipping` entre `subtotal_minor` y `total_minor`.

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Feature/DeliveryCheckoutTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Delivery\DeliveryZone;
use App\Models\DeliveryRate;
use App\Models\DeliveryRateSet;
use App\Models\Order;
use App\Models\Product;
use Database\Seeders\DeliveryZonesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DeliveryCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DeliveryZonesSeeder::class);
    }

    /** Puts $quantity units of a $priceMinor product in the cart and opens the checkout. */
    private function prepare(int $priceMinor = 123456, int $quantity = 2, string $currency = 'CRC'): Product
    {
        $product = Product::factory()->sellable()->create(['status' => 'published', 'price_minor' => $priceMinor, 'currency' => $currency]);
        $this->post('/cart/items', ['mutation_id' => (string) Str::uuid(), 'revision' => session('shopping_cart.revision', 0), 'product_slug' => $product->slug, 'quantity' => $quantity])->assertSessionHasNoErrors();

        return $product;
    }

    private function payload(array $extra = []): array
    {
        return ['token' => session('checkout_review.token'), 'first_name' => 'Ana', 'last_name' => 'Prueba', 'email' => 'guest@example.test', 'phone' => '+50688887777', 'province_code' => '1', 'canton_code' => '101', 'district_code' => '10101', 'exact_address' => 'Dirección ficticia para pruebas, casa azul.', 'additional' => '', ...$extra];
    }

    private function review(?string $canton = null): array
    {
        $url = '/checkout'.($canton ? '?canton_code='.$canton : '');

        return $this->get($url)->assertOk()->viewData('page')['props']['review'];
    }

    public function test_the_review_has_no_delivery_until_a_canton_is_chosen(): void
    {
        $this->prepare();
        $review = $this->review();
        $this->assertNull($review['shipping']);
        $this->assertSame(246912, $review['subtotal_minor']);
        $this->assertSame(246912, $review['total_minor']);
    }

    public function test_choosing_a_gam_canton_quotes_the_flat_fee_before_confirming(): void
    {
        $this->prepare();
        $review = $this->review('101');
        $this->assertSame(350000, $review['shipping']['amount_minor']);
        $this->assertFalse($review['shipping']['free']);
        $this->assertSame('Gran Área Metropolitana', $review['shipping']['zone_label']);
        $this->assertSame(596912, $review['total_minor']);
        $this->get('/checkout?canton_code=101')->assertInertia(fn (Assert $page) => $page->where('review.shipping.amount_minor', 350000)->where('review.total_minor', 596912));
    }

    public function test_confirmation_persists_the_delivery_snapshot_and_the_final_total(): void
    {
        $this->prepare();
        $this->review('101');
        $response = $this->from('/checkout')->post('/checkout', $this->payload())->assertSessionHasNoErrors()->assertStatus(303);
        $order = Order::where('number', basename($response->headers->get('Location')))->sole();
        $this->assertSame(246912, $order->subtotal_minor);
        $this->assertSame(350000, $order->shipping_minor);
        $this->assertSame(596912, $order->total_minor);
        $this->assertSame(DeliveryZone::Gam, $order->shipping_zone);
        $this->assertSame(DeliveryRateSet::where('version', DeliveryZonesSeeder::VERSION)->value('id'), $order->delivery_rate_set_id);
        $this->assertSame(['amount_minor' => 350000, 'zone' => 'gam', 'zone_label' => 'Gran Área Metropolitana', 'free' => false], $order->publicSummary()['shipping']);
    }

    public function test_guanacaste_norte_ships_free_and_the_rest_of_the_country_pays_its_own_fee(): void
    {
        $this->prepare();
        $this->review('501');
        $liberia = $this->from('/checkout')->post('/checkout', $this->payload(['province_code' => '5', 'canton_code' => '501', 'district_code' => '50101']))->assertStatus(303);
        $order = Order::where('number', basename($liberia->headers->get('Location')))->sole();
        $this->assertSame(0, $order->shipping_minor);
        $this->assertSame(246912, $order->total_minor);
        $this->assertSame(DeliveryZone::GuanacasteNorte, $order->shipping_zone);
        $this->prepare();
        $this->review('706');
        $guacimo = $this->from('/checkout')->post('/checkout', $this->payload(['province_code' => '7', 'canton_code' => '706', 'district_code' => '70601']))->assertStatus(303);
        $second = Order::where('number', basename($guacimo->headers->get('Location')))->sole();
        $this->assertSame(450000, $second->shipping_minor);
        $this->assertSame(696912, $second->total_minor);
        $this->assertSame(DeliveryZone::Rest, $second->shipping_zone);
    }

    public function test_reaching_the_threshold_makes_delivery_free(): void
    {
        $this->prepare(4500000, 2);
        $review = $this->review('101');
        $this->assertSame(9000000, $review['subtotal_minor']);
        $this->assertSame(0, $review['shipping']['amount_minor']);
        $this->assertTrue($review['shipping']['free']);
        $this->assertSame(9000000, $review['total_minor']);
        $response = $this->from('/checkout')->post('/checkout', $this->payload())->assertStatus(303);
        $this->assertSame(0, Order::where('number', basename($response->headers->get('Location')))->value('shipping_minor'));
    }

    public function test_changing_the_rates_later_never_changes_an_existing_order(): void
    {
        $this->prepare();
        $this->review('101');
        $response = $this->from('/checkout')->post('/checkout', $this->payload())->assertStatus(303);
        $order = Order::where('number', basename($response->headers->get('Location')))->sole();
        $snapshot = $order->publicSummary();
        $newer = DeliveryRateSet::create(['version' => 'v2-test', 'effective_from' => now(), 'notes' => 'QA']);
        DeliveryRate::create(['delivery_rate_set_id' => $newer->id, 'zone' => DeliveryZone::Gam, 'flat_minor' => 900000, 'free_from_minor' => null, 'currency' => 'CRC']);
        $this->assertSame($snapshot, $order->fresh()->publicSummary());
        $this->assertSame(350000, $order->fresh()->shipping_minor);
        $this->assertSame(596912, $order->fresh()->total_minor);
    }

    public function test_rates_that_change_between_review_and_confirmation_require_a_new_review(): void
    {
        $this->prepare();
        $this->review('101');
        $stale = $this->payload();
        $newer = DeliveryRateSet::create(['version' => 'v2-test', 'effective_from' => now(), 'notes' => 'QA']);
        DeliveryRate::create(['delivery_rate_set_id' => $newer->id, 'zone' => DeliveryZone::Gam, 'flat_minor' => 900000, 'free_from_minor' => null, 'currency' => 'CRC']);
        $this->post('/checkout', $stale)->assertSessionHasErrors('checkout');
        $this->assertDatabaseCount('orders', 0);
        $this->review('101');
        $this->from('/checkout')->post('/checkout', $this->payload())->assertSessionHasNoErrors()->assertStatus(303);
        $this->assertSame(900000, Order::sole()->shipping_minor);
    }

    public function test_the_client_cannot_impose_a_shipping_amount_or_a_total(): void
    {
        $this->prepare();
        $this->review('101');
        foreach (['shipping_minor' => 0, 'total_minor' => 1, 'subtotal_minor' => 1, 'shipping_zone' => 'gam', 'delivery_rate_set_id' => 1] as $field => $value) {
            $this->post('/checkout', $this->payload([$field => $value]))->assertSessionHasErrors('checkout');
        }
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_a_cart_in_another_currency_cannot_be_reviewed_or_confirmed(): void
    {
        $this->prepare(1000, 1, 'USD');
        $this->get('/checkout')->assertRedirect('/cart')->assertSessionHasErrors('checkout');
        $this->post('/checkout', $this->payload(['token' => (string) Str::uuid()]))->assertSessionHasErrors('checkout');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_an_unknown_canton_blocks_the_confirmation(): void
    {
        $this->prepare();
        $this->review('101');
        $this->post('/checkout', $this->payload(['province_code' => '9', 'canton_code' => '999', 'district_code' => '99999']))->assertSessionHasErrors();
        $this->assertDatabaseCount('orders', 0);
    }
}
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `php artisan test --filter=DeliveryCheckoutTest`
Expected: FAIL (la revisión no tiene `shipping`; el pedido no tiene columnas de envío).

- [ ] **Step 3: Migración aditiva del pedido**

Crear `database/migrations/2026_09_20_000002_add_shipping_to_orders.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Additive and nullable on purpose: orders created before V1-C were never quoted for
        // delivery and must not be reinterpreted as if they were (ADR-008).
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('shipping_minor')->nullable()->after('subtotal_minor');
            $table->string('shipping_zone', 32)->nullable()->after('shipping_minor');
            $table->foreignId('delivery_rate_set_id')->nullable()->after('shipping_zone')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_rate_set_id');
            $table->dropColumn(['shipping_minor', 'shipping_zone']);
        });
    }
};
```

- [ ] **Step 4: Modelo del pedido**

En `app/Models/Order.php` agregar `use App\Delivery\DeliveryZone;` y reemplazar `casts()` y `publicSummary()` por:

```php
    protected function casts(): array
    {
        return ['status' => OrderStatus::class, 'shipping_zone' => DeliveryZone::class, 'total_minor' => 'integer', 'subtotal_minor' => 'integer', 'shipping_minor' => 'integer', 'cart_revision' => 'integer'];
    }

    public function publicSummary(): array
    {
        return [
            'number' => $this->number, 'created_at' => $this->created_at->toIso8601String(),
            'buyer' => $this->only(['first_name', 'last_name', 'email', 'phone']),
            'status' => $this->status->value, 'status_label' => $this->status->label(),
            'currency' => $this->currency, 'subtotal_minor' => $this->subtotal_minor,
            // Null for orders placed before V1-C: they were never quoted for delivery.
            'shipping' => $this->shipping_minor === null ? null : [
                'amount_minor' => $this->shipping_minor,
                'zone' => $this->shipping_zone?->value,
                'zone_label' => $this->shipping_zone?->label(),
                'free' => $this->shipping_minor === 0,
            ],
            'total_minor' => $this->total_minor,
            'items' => $this->items->map(fn ($item) => $item->only(['name', 'sku', 'quantity', 'unit_price_minor', 'subtotal_minor', 'currency', 'is_demo']))->all(),
            'address' => $this->address->only(['country_code', 'province', 'canton', 'district', 'exact_address', 'additional']),
        ];
    }
```

- [ ] **Step 5: Servicio de checkout**

En `app/Services/CheckoutService.php` agregar los imports `use App\Delivery\DeliveryQuoter;`, `use App\Delivery\DeliveryUnavailable;` y ampliar el constructor:

```php
    public function __construct(private CartStore $store, private ProductAvailability $availability, private StockHolds $holds, private DeliveryQuoter $delivery) {}
```

En `quote()`, reemplazar el `return` final por:

```php
        $subtotal = array_sum(array_column($items, 'subtotal_minor'));
        // Currency gate: delivery rates are in colones and no implicit conversion exists (ADR-002).
        if ($cart['currency'] !== 'CRC') {
            $this->fail('Por ahora solo procesamos pedidos en colones. Escríbenos para coordinar una compra en otra moneda.');
        }
        $shipping = null;
        if ($cantonCode !== null) {
            try {
                $shipping = $this->delivery->quote($cantonCode, $subtotal, $cart['currency']);
            } catch (DeliveryUnavailable $exception) {
                $this->fail($exception->getMessage());
            }
        }

        return [
            'items' => $items, 'currency' => $cart['currency'], 'subtotal_minor' => $subtotal,
            'shipping' => $shipping?->toPublic(),
            'shipping_rate_set_id' => $shipping?->rateSetId, 'shipping_zone' => $shipping?->zone->value,
            'total_minor' => $subtotal + ($shipping?->amountMinor ?? 0),
            'revision' => $cart['revision'],
        ];
```

y cambiar la firma a:

```php
    public function quote(bool $lock = false, ?string $cantonCode = null): array
```

En `review()`, reemplazar el método por:

```php
    public function review(?string $cantonCode = null): array
    {
        $quote = $this->quote(cantonCode: $cantonCode);
        $hash = $this->digest($quote);
        $review = session('checkout_review');
        if (! $review || $review['hash'] !== $hash) {
            $review = ['token' => (string) Str::uuid(), 'hash' => $hash];
            session()->put('checkout_review', $review);
        }
        $this->ownerHash();
        unset($quote['revision'], $quote['shipping_rate_set_id'], $quote['shipping_zone']);
        $quote['items'] = array_map(function ($item) {
            unset($item['product_id']);

            return $item;
        }, $quote['items']);

        return [...$quote, 'token' => $review['token']];
    }
```

En `confirm()`, reemplazar la línea `$quote = $this->quote(true);` por:

```php
            $quote = $this->quote(true, $data['canton_code']);
```

y dentro del `forceFill` del pedido, reemplazar la línea de importes por:

```php
                'currency' => $quote['currency'], 'subtotal_minor' => $quote['subtotal_minor'],
                'shipping_minor' => $quote['shipping']['amount_minor'], 'shipping_zone' => $quote['shipping_zone'],
                'delivery_rate_set_id' => $quote['shipping_rate_set_id'], 'total_minor' => $quote['total_minor'],
```

El hash de la revisión ya incluye el envío y la versión de tarifas, porque `digest()` cubre todo el arreglo de la cotización: si cambia la tarifa entre la revisión y la confirmación, la comparación falla y se pide revisar de nuevo.

- [ ] **Step 6: Controlador**

En `app/Http/Controllers/CheckoutController.php`, reemplazar `show()` por:

```php
    public function show(Request $request, CheckoutService $checkout)
    {
        $canton = $request->query('canton_code');
        $canton = is_string($canton) && preg_match('/^\d{3}$/', $canton) === 1 ? $canton : null;
        try {
            $review = $checkout->review($canton);
        } catch (ValidationException $exception) {
            return redirect('/cart')->withErrors($exception->errors());
        }

        return Inertia::render('checkout/Index', [
            'review' => $review, 'territories' => CostaRicaTerritories::all(),
            // Do not guess how a full account name splits into given/family names.
            'prefill' => ['email' => $request->user()?->role === Role::Customer ? $request->user()->email : ''],
        ]);
    }
```

- [ ] **Step 7: Correr el test nuevo y verificar que pasa**

Run: `php artisan test --filter=DeliveryCheckoutTest`
Expected: PASS, 10 tests.

- [ ] **Step 8: Actualizar las assertions que cambian por diseño**

En `tests/Feature/CheckoutTest.php`, el helper `prepare()` debe dejar cotizado el envío antes de confirmar: reemplazar su línea `$this->get('/checkout')->assertOk();` por `$this->get('/checkout?canton_code=101')->assertOk();`

Luego, con el envío GAM de `350000`:

- `test_guest_order_has_snapshots_initial_status_history_and_no_user`: `$this->assertSame(246912, $order->total_minor);` pasa a `$this->assertSame(596912, $order->total_minor);` y se agrega debajo `$this->assertSame(246912, $order->subtotal_minor);` y `$this->assertSame(350000, $order->shipping_minor);`
- `test_product_changes_do_not_change_historical_order`: `->where('order.total_minor', 246912)` pasa a `->where('order.total_minor', 596912)`
- `test_changed_price_requires_explicit_review_before_confirmation`: `$this->assertSame(400000, $this->place()->total_minor);` pasa a `$this->assertSame(750000, $this->place()->total_minor);` (400000 de productos más 350000 de envío); la assertion `->where('review.total_minor', 400000)` **no cambia**, porque esa revisión se pide sin cantón
- `test_confirmation_exposes_only_allowlisted_snapshot_and_is_private`: la lista de claves pasa a `['number', 'created_at', 'buyer', 'status', 'status_label', 'currency', 'subtotal_minor', 'shipping', 'total_minor', 'items', 'address']`
- `test_currency_change_is_blocked_and_usd_order_remains_usd`: se reemplaza completo por su equivalente bajo la regla nueva, con nombre nuevo:

```php
    public function test_currency_change_is_blocked_and_a_usd_cart_cannot_be_confirmed(): void
    {
        $p = $this->prepare();
        $this->post('/checkout', $this->payload())->assertSessionHasErrors('checkout');
        $p->update(['currency' => 'USD']);
        $this->get('/checkout')->assertRedirect('/cart')->assertSessionHasErrors('checkout');
        $this->post('/checkout', $this->payload())->assertSessionHasErrors('checkout');
        $this->assertDatabaseCount('orders', 0);
    }
```

En `tests/Feature/CommercialCatalogTest.php`, dentro de `test_public_catalog_cart_checkout_and_order_never_expose_offers_or_use_cost`: la línea `$this->get('/checkout')->assertInertia(fn (Assert $a) => $a->where('review.total_minor', 1000000));` pasa a `$this->get('/checkout?canton_code=101')->assertInertia(fn (Assert $a) => $a->where('review.total_minor', 1350000));`, y `$this->assertSame(1000000, $order->total_minor);` pasa a `$this->assertSame(1350000, $order->total_minor);`

Los demás tests de checkout que llaman a `prepare()` y solo verifican errores, dirección o auditoría no cambian.

- [ ] **Step 9: Sembrar las tarifas en los tests que confirman pedidos**

`CheckoutTest`, `CommercialCatalogTest`, `CheckoutAvailabilityTest` y `OrderPaymentTest` confirman pedidos, así que necesitan tarifas. En el `setUp()` de cada uno, después de `$this->withoutVite();`, agregar:

```php
        $this->seed(\Database\Seeders\DeliveryZonesSeeder::class);
```

- [ ] **Step 10: Suite completa y estilo**

Run: `php artisan test && php vendor/bin/pint --test`
Expected: 187 tests (177 + 10), 0 failures; Pint passed.

- [ ] **Step 11: Punto de control del Lote 2 (sin commit)**

Run: `git status --short && git diff --stat && git --no-pager diff -U0 -- tests/ | grep -E "^[+-][^+-]"`
Expected: en tests existentes, solo las líneas listadas en el Step 8 y la siembra del Step 9. Detenerse para revisión.

### Task 4: La tienda muestra el envío antes de confirmar

**Files:**
- Modify: `resources/js/types/order.ts`
- Modify: `resources/js/components/storefront/OrderSummary.vue`
- Modify: `resources/js/components/storefront/OrderDetails.vue`
- Modify: `resources/js/pages/checkout/Index.vue`
- Modify: `resources/js/pages/checkout/Confirmation.vue`
- Modify: `resources/css/storefront.css`

**Interfaces:**
- Consumes: `review.subtotal_minor`, `review.shipping` y `order.shipping` de la Tarea 3.
- Produces: `OrderSummary` con props `items, currency, subtotal, shipping, total`. `OrderDetails` y la página de confirmación no cambian su firma pública.

Tres lugares muestran el mismo resumen: la revisión del checkout, la confirmación del comprador y el detalle de administración. Los tres pasan por `OrderSummary`, así que el desglose se escribe una vez. La confirmación y el detalle de administración leen `order.shipping`, que es `null` en los pedidos previos a V1-C: esos siguen mostrándose como antes, sin línea de envío y sin afirmar que el transporte estaba incluido.

- [ ] **Step 1: Tipos**

En `resources/js/types/order.ts`, agregar los tipos de envío y ampliar `Review` y `Order`:

```ts
export interface ShippingQuote { zone: string; zone_label: string; amount_minor: number; free: boolean; free_from_minor: number | null; missing_for_free_minor: number | null; currency: string }
export interface OrderShipping { amount_minor: number; zone: string | null; zone_label: string | null; free: boolean }
export interface Review { items: OrderItem[]; currency: string; subtotal_minor: number; shipping: ShippingQuote | null; total_minor: number; token: string }
```

y en `Order`, reemplazar `subtotal_minor: number; total_minor: number` por `subtotal_minor: number; shipping: OrderShipping | null; total_minor: number`.

- [ ] **Step 2: Resumen con desglose**

Reemplazar `resources/js/components/storefront/OrderSummary.vue` por:

```vue
<script setup lang="ts">
import { money } from '../../types/catalog';
import type { OrderItem, OrderShipping, ShippingQuote } from '../../types/order';
// `shipping` is null for orders placed before delivery quoting existed, and in the checkout
// review until a canton is chosen. Both cases show the product amount without claiming more.
defineProps<{ items: OrderItem[]; currency: string; subtotal: number; shipping: ShippingQuote | OrderShipping | null; total: number }>();
</script>
<template><section class="st-order-summary" aria-label="Resumen del pedido"><h2>Tu pedido, en claro.</h2><ul><li v-for="item in items" :key="item.sku"><div><strong>{{ item.name }}</strong><small>{{ item.sku }} · {{ item.quantity }} × {{ money(item.unit_price_minor, currency) }}</small><small v-if="item.is_demo">Producto de demostración</small></div><strong>{{ money(item.subtotal_minor, currency) }}</strong></li></ul><dl>
    <div><dt>Productos</dt><dd>{{ money(subtotal, currency) }}</dd></div>
    <div v-if="shipping"><dt>Envío<span v-if="shipping.zone_label"> · {{ shipping.zone_label }}</span></dt><dd>{{ shipping.free ? 'Gratis' : money(shipping.amount_minor, currency) }}</dd></div>
    <div v-else><dt>Envío</dt><dd>Sin calcular</dd></div>
    <div class="st-summary-total"><dt>Total</dt><dd>{{ money(total, currency) }}</dd></div>
</dl><p class="st-cart-help">{{ currency }} · Total final del pedido. No incluye impuestos, que aún no se aplican. No se ha procesado ningún pago.</p></section></template>
```

- [ ] **Step 3: Detalle del pedido**

En `resources/js/components/storefront/OrderDetails.vue`, reemplazar la línea del resumen por:

```vue
<OrderSummary :items="order.items" :currency="order.currency" :subtotal="order.subtotal_minor" :shipping="order.shipping" :total="order.total_minor"/>
```

- [ ] **Step 4: Cotización en vivo en el checkout**

En `resources/js/pages/checkout/Index.vue`:

Agregar `router` al import de Inertia:

```ts
import { Head, Link, router, useForm } from '@inertiajs/vue3';
```

Debajo del watcher que limpia el distrito, agregar la recotización y su mensaje:

```ts
const quoting = ref(false);
// The buyer picks a canton; the server decides the amount. Only the review prop is refetched,
// so the form the buyer is filling in stays exactly as it is.
watch(() => form.canton_code, canton => {
    form.district_code = '';
    if (!canton) return;
    quoting.value = true;
    router.get('/checkout', { canton_code: canton }, { only: ['review'], preserveState: true, preserveScroll: true, replace: true, onFinish: () => { quoting.value = false; } });
});
const shippingNote = computed(() => {
    if (quoting.value) return 'Calculando el envío…';
    const shipping = props.review.shipping;
    if (!shipping) return 'Elige tu cantón para ver el costo de envío y el total.';
    if (shipping.free) return `Envío gratis a ${shipping.zone_label}.`;
    if (shipping.missing_for_free_minor) return `${money(shipping.amount_minor, props.review.currency)} de envío a ${shipping.zone_label}. Te faltan ${money(shipping.missing_for_free_minor, props.review.currency)} en productos para que sea gratis.`;
    return `${money(shipping.amount_minor, props.review.currency)} de envío a ${shipping.zone_label}.`;
});
```

y borrar el watcher anterior de `form.canton_code` (el que solo limpiaba el distrito), que este reemplaza.

En la plantilla:

- Cambiar el resumen móvil (línea 40) para que nombre el total real:

```vue
<div class="st-checkout-mobile-summary"><span>Total <strong>{{ money(review.total_minor, review.currency) }}</strong></span><a href="#checkout-review">Ver resumen ↓</a></div>
```

- Reemplazar la ayuda al pie del bloque de entrega (línea 50, `El transporte aún no se calcula…`) por el estado de la cotización:

```vue
</div><p class="st-cart-help" role="status">{{ shippingNote }} Este pedido no establece una fecha de entrega.</p></fieldset>
```

- Pasar el desglose al resumen (línea 52):

```vue
<OrderSummary :items="review.items" :currency="review.currency" :subtotal="review.subtotal_minor" :shipping="review.shipping" :total="review.total_minor"/>
```

- En el mismo `aside`, deshabilitar la confirmación mientras no haya envío cotizado, para que nadie confirme un total que todavía no vio:

```vue
<button class="st-button" type="submit" :disabled="form.processing || quoting || !review.shipping">{{ form.processing ? 'Creando pedido…' : 'Confirmar pedido' }}</button>
```

- Cambiar el aviso final del `aside` para que deje de negar el cálculo del envío:

```vue
<p class="st-cart-help" role="status">El pedido quedará pendiente de pago. El total incluye el envío y no cambiará después de confirmarlo.</p>
```

- [ ] **Step 5: Confirmación**

En `resources/js/pages/checkout/Confirmation.vue`, reemplazar la ayuda final por una que no contradiga el pedido ya cotizado:

```vue
<p class="st-cart-help">Este total es el que pagarás; el envío ya está incluido. Aún no se ha procesado el pago ni se ha confirmado una fecha de entrega.</p>
```

- [ ] **Step 6: Estilo del total**

En `resources/css/storefront.css`, junto a las reglas de `.st-order-summary dl`, agregar:

```css
/* The final total is the line the buyer checks; the breakdown above it is supporting detail. */
.st-order-summary .st-summary-total { border-top: 1px solid var(--st-line); margin-top: .5rem; padding-top: .5rem; font-size: 1.05rem; }
.st-order-summary .st-summary-total dt, .st-order-summary .st-summary-total dd { font-weight: 600; }
```

Si los nombres de las variables o el selector del bloque `dl` no coinciden con los actuales, adaptarlos a los que ya existen en el archivo en vez de introducir tokens nuevos.

- [ ] **Step 7: Verificar tipos y build**

Run: `npm run check`
Expected: TypeScript sin errores y build de Vite completo.

- [ ] **Step 8: Suite completa**

Run: `php artisan test`
Expected: 187 tests, 0 failures. Los tests de Inertia leen props, no plantillas, así que este paso no debería cambiar nada; si algo falla aquí, es una firma de prop mal escrita, no un cambio de contrato.

- [ ] **Step 9: Revisión visual manual**

Run: `php artisan serve --port=8086` y recorrer `/checkout` con un carrito real.
Expected: sin cantón, el botón de confirmar está deshabilitado y el resumen dice «Sin calcular»; al elegir San José, aparece el envío y el total sube; al elegir Liberia, el envío queda en «Gratis». Comprobar en el móvil (ancho 390 px) que el resumen superior muestra el total actualizado.

### Task 5: Documentación y cierre de V1-C

**Files:**
- Create: `docs/V1-C-REPORT.md`
- Modify: `docs/adr/007-delivery.md`, `docs/adr/008-final-total.md`
- Modify: `docs/ENVIRONMENTS-RELEASE.md`

- [ ] **Step 1: Reporte**

Crear `docs/V1-C-REPORT.md` con, como mínimo:

1. **Qué se implementó**: zonas, tarifas planas, umbrales de envío gratis, cotización autoritativa en el servidor, envío visible antes de confirmar, instantánea en el pedido.
2. **Las tarifas vigentes**, con su versión (`v1-2026-09`) y los importes exactos de cada zona.
3. **Los 31 cantones GAM y los 4 de Guanacaste Norte**, tal como quedaron sembrados.
4. **Qué cambió de comportamiento visible**, copiando la tabla de la sección «Assertions que cambian por diseño» con el resultado real.
5. **Qué quedó fuera**: pantalla de administración de tarifas (ADR-007 la pide y no existe: hoy cambiar una tarifa exige un `rate set` nuevo en la base), peso y dimensiones, cobertura por distrito, impuestos, transportistas reales, seguimiento. Decir explícitamente que `direct_supplier` y `via_operation` (ADR-007) siguen sin implementarse: el cotizador no distingue el origen del envío.
6. **Resultado de la verificación**: salida real de `php artisan test`, `php vendor/bin/pint --test` y `npm run check`. No escribir números que no se hayan visto en la terminal.

- [ ] **Step 2: ADR-007**

Cambiar su línea de estado a que el motor de cotización existe desde V1-C, enlazando este plan, y dejar anotado que la administración de tarifas sigue pendiente. No reescribir el resto del ADR.

- [ ] **Step 3: ADR-008**

Reemplazar el párrafo «Estado actual» por uno que describa lo real: el servidor calcula y persiste subtotal, envío y total final; impuestos siguen sin aplicarse; los pedidos anteriores a V1-C conservan `shipping_minor` nulo y no se recalculan. Mantener intacta la puerta fiscal pendiente.

- [ ] **Step 4: Pendientes del servidor**

En `docs/ENVIRONMENTS-RELEASE.md`, agregar una fila a la tabla «Pendientes de configuración en el servidor real»:

| **Tarifas de envío** | Ejecutar una vez `php artisan db:seed --class=DeliveryZonesSeeder --force`; es idempotente y no toca catálogo ni pedidos | El checkout bloquea la confirmación: sin tarifas no hay total final |

Esta es la única excepción autorizada a la regla «no seeders en el release normal» de ese documento, y conviene decirlo ahí mismo en una frase: siembra tarifas de configuración, no datos comerciales.

- [ ] **Step 5: Verificación final completa**

Run: `php artisan test && php vendor/bin/pint --test && npm run check && git status --short`
Expected: 187 tests / 0 failures, Pint limpio, build correcto, y en `git status` solo los archivos del mapa de esta tarea. Sin commit ni push.

- [ ] **Step 6: Punto de control del Lote 3**

Detenerse y presentar al propietario: resultado de los tres comandos, `git status`/`git diff --stat`, el diff completo de las assertions modificadas y las decisiones tomadas sobre la marcha.

## Self-review

Antes de dar V1-C por terminada, verificar cada punto contra el código, no contra el plan:

- [ ] Ninguna tarifa vive en PHP: `grep -rn "350000\|450000\|9000000\|11000000" app/` no devuelve nada fuera de comentarios.
- [ ] El cliente no puede fijar importes: el `validated()` del checkout no acepta `shipping_minor`, `total_minor`, `subtotal_minor`, `shipping_zone` ni `delivery_rate_set_id`, y el test correspondiente lo comprueba campo por campo.
- [ ] El envío se recalcula bajo bloqueo en `confirm()`, no se copia de la sesión.
- [ ] Un pedido confirmado no cambia si después cambian las tarifas.
- [ ] Los pedidos anteriores a V1-C siguen abriéndose sin error y no muestran una línea de envío inventada.
- [ ] Las migraciones son aditivas y todas las columnas nuevas son nulables.
- [ ] Ninguna assertion existente se eliminó: las seis listadas se actualizaron con valores nuevos y verificables, y `git diff -- tests/` no muestra ninguna otra línea eliminada.
- [ ] El umbral de envío gratis se mide sobre el subtotal de productos, nunca sobre el total con envío.
- [ ] Ningún cantón se cotiza por adivinanza: un cantón desconocido bloquea.
- [ ] No se agregó ninguna llamada a una API de transportistas ni a Eurocomp.
- [ ] `docs/V1-C-REPORT.md` no afirma nada que no se haya ejecutado en la terminal.


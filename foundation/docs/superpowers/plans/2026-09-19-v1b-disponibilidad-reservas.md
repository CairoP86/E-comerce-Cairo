# V1-B · Disponibilidad comercial y reserva local — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Que el storefront solo muestre y venda productos con disponibilidad vigente de su oferta preferida, con cantidad exacta, y que agregar al carrito cree una reserva local de 1 hora que el checkout convierte en asignación del pedido.

**Architecture:** Un resolvedor (`PreferredOfferAvailability`) calcula el estado de cada producto a partir de la oferta preferida, el TTL configurable y las reservas activas de otros carritos; el mismo criterio existe en SQL (`constrainVisible`) para filtrar listados paginados. `StockHolds` crea/ajusta/libera/convierte reservas en la tabla nueva `stock_holds`, serializando por bloqueo de la fila de la oferta. Catálogo, carrito y checkout consumen esos dos componentes; la UI recibe solo `state` y `quantity`.

**Tech Stack:** Laravel 13 / PHP 8.5, Eloquent, PHPUnit 12 (SQLite en memoria), Inertia 3 + Vue 3 + TypeScript, CSS propio `st-*`.

**Fuentes de verdad:** `foundation/docs/V1-B-SCOPE.md`, `foundation/docs/adr/005-availability.md`, decisiones D1–D11 confirmadas por el propietario (resumen en Global Constraints).

## Global Constraints

- Todo el trabajo ocurre dentro de `foundation/`. Rutas de este plan son relativas a `foundation/` salvo que digan lo contrario.
- **No hacer commit ni push.** Cada tarea termina en un punto de control con `git status --short` y `git diff --stat`; el propietario revisa por lotes.
- Migraciones **solo aditivas**. No modificar ni reescribir tablas ni filas de Phase 2C.
- Sin integración con Eurocomp/Dataformas/CQ: ni clientes HTTP, ni adapters, ni respuestas simuladas. `config('commerce.suppliers')` no cambia.
- TTL de disponibilidad: variable `COMMERCE_AVAILABILITY_TTL_MINUTES`, **sin valor por defecto en código**. Aplica igual a `source=manual` y a datos de proveedor. Sin excepción para ningún producto (incluido `TEST-ROUTER-001`).
- TTL ausente o inválido ⇒ **la aplicación falla al arrancar** (validación en `AppServiceProvider::boot`). Única excepción: `artisan package:discover`, que Composer ejecuta antes de que exista `.env`.
- Reserva local: `COMMERCE_CART_HOLD_MINUTES`, por defecto 60 (V1-B-SCOPE §5). Nunca se comunica al proveedor.
- Estados (D1–D4): `available` con stock > 0 ⇒ disponible; `available` sin stock ⇒ desconocido; `available` con 0 o `unavailable` ⇒ no disponible; oferta/proveedor inactivo, sin oferta preferida, observación futura o `unknown` ⇒ desconocido; fuera de TTL ⇒ vencido. Fresco mientras `observed_at > ahora − TTL` (a los TTL minutos exactos ya vence).
- Visibilidad (D9, D10): vencido y desconocido se ocultan como un producto no publicado (listados, búsqueda, relacionados, detalle 404, imagen 404, línea de carrito bloqueada). No disponible se muestra como agotado y no se puede agregar.
- Cantidad pública (D6): stock menos reservas activas de **otros** carritos. Si llega a 0 se muestra agotado.
- Reserva (D7, D8): vencimiento fijo desde su creación; cambiar cantidad no lo extiende; volver a agregar tras vencer crea una nueva. Al confirmar, la reserva pasa al pedido `pending_payment` con el mismo vencimiento; al vencer libera la unidad aunque el pedido siga pendiente (pagos tardíos: V1-E).
- Concurrencia real con MySQL: **pendiente para V1-I**. No agregar servicios MySQL al CI.
- No eliminar ni reescribir assertions existentes. En tests existentes solo cambian fixtures (productos que deben seguir siendo públicos reciben una oferta vigente). `CommercialTaxonomyTest` no requiere cambios.
- Las claves públicas existentes no cambian: `PublicProductResource` y las líneas del carrito conservan exactamente sus claves. La disponibilidad pública viaja como prop aparte `availability` (slug ⇒ `{state, quantity}`); los estados nuevos de línea usan valores nuevos de `reason`.
- Copy en español con tuteo, como el resto del storefront.
- Comandos locales: el `.env` local todavía no tiene la variable. Prefijar cada comando de artisan con `COMMERCE_AVAILABILITY_TTL_MINUTES=10080` hasta que el propietario la agregue a su `.env`.

## Estado previo al plan

Ya existen (escritos antes de convertir el trabajo en plan) y la Tarea 1/2 los verifica en lugar de crearlos: `config/commerce.php` (sección `availability`), `app/Availability/{InvalidAvailabilityConfiguration,HoldUnavailable,AvailabilityConfig,AvailabilityState,Availability}.php`, `app/Contracts/ProductAvailability.php`. `docs/V1-B-SCOPE.md` renombrado y enlazado desde ADR-005.

## Mapa de archivos

| Archivo | Responsabilidad | Tarea |
| --- | --- | --- |
| `config/commerce.php` | Sección `availability` (ttl, hold) | 1 |
| `app/Availability/AvailabilityConfig.php` | Parseo/validación de minutos, excepción de `package:discover` | 1 |
| `app/Availability/InvalidAvailabilityConfiguration.php` | Excepción de arranque | 1 |
| `app/Providers/AppServiceProvider.php` | Validación al arrancar; binding del contrato | 1, 2 |
| `phpunit.xml`, `.env.example`, `docs/ENVIRONMENTS-RELEASE.md` | Variable por entorno | 1 |
| `app/Availability/AvailabilityState.php`, `Availability.php` | Estado y valor con procedencia | 2 |
| `app/Contracts/ProductAvailability.php` | Contrato de consulta por lote | 2 |
| `app/Availability/PreferredOfferAvailability.php` | Reglas PHP + SQL equivalentes | 2 |
| `database/migrations/2026_09_19_000001_create_stock_holds_table.php`, `app/Models/StockHold.php` | Reservas locales | 2 |
| `database/factories/ProductFactory.php` | Estado `sellable()` para fixtures | 2 |
| `app/Models/Product.php` | Scope `storefrontVisible` | 3 |
| `app/Http/Controllers/PublicCatalogController.php`, `CatalogImageController.php` | Filtrado y prop `availability` | 3 |
| `app/Support/CartHolder.php` | Identidad hash del carrito | 3 |
| `app/Services/StockHolds.php` | Reservar, liberar, convertir, limpiar | 4 |
| `app/Services/CartService.php`, `app/Http/Controllers/AuthController.php` | Carrito con reservas; logout libera | 4 |
| `app/Services/CheckoutService.php` | Verificación bajo bloqueo y conversión | 5 |
| `app/Console/Commands/PruneStockHolds.php`, `routes/console.php` | Limpieza programada | 6 |
| `resources/css/*`, `resources/js/**` | UI de stock y reserva | 7 |
| `docs/V1-B-REPORT.md` | Reporte de fase | 8 |

## Lotes de revisión

| Lote | Tareas | Resultado revisable |
| --- | --- | --- |
| 1 | 1–2 | Arranque falla sin TTL; resolución de estados PHP = SQL; tabla de reservas |
| 2 | 3 | Catálogo público filtra por disponibilidad y muestra cantidad |
| 3 | 4–5 | Reservas en carrito y conversión en checkout |
| 4 | 6–8 | Limpieza programada, UI y reporte final |

---

### Task 1: TTL configurable y fallo al arrancar

**Files:**
- Verify: `config/commerce.php`, `app/Availability/AvailabilityConfig.php`, `app/Availability/InvalidAvailabilityConfiguration.php`
- Modify: `app/Providers/AppServiceProvider.php` (inicio de `boot()`)
- Modify: `phpunit.xml`, `.env.example`, `docs/ENVIRONMENTS-RELEASE.md` (matriz de configuración)
- Test: `tests/Feature/AvailabilityConfigTest.php`

**Interfaces:**
- Produces: `AvailabilityConfig::assertValid(mixed $ttl, mixed $hold): void`, `AvailabilityConfig::ttlMinutes(): int`, `AvailabilityConfig::holdMinutes(): int`, `AvailabilityConfig::mustValidate(bool $runningInConsole, array $argv): bool`, excepción `App\Availability\InvalidAvailabilityConfiguration`.

- [ ] **Step 1: Verificar los archivos ya escritos**

`config/commerce.php` debe contener exactamente esta sección dentro del array raíz (después de `suppliers`):

```php
    // V1-B-SCOPE.md. Validated at boot by App\Availability\AvailabilityConfig.
    'availability' => [
        // Minutes an offer observation stays fresh, for manual and supplier data alike.
        // Required, no default: the value differs per environment and the application refuses to boot without it.
        'ttl_minutes' => env('COMMERCE_AVAILABILITY_TTL_MINUTES'),
        // Local cart hold duration (V1-B-SCOPE §5: one hour). Internal only, never a supplier reservation.
        'hold_minutes' => env('COMMERCE_CART_HOLD_MINUTES', 60),
    ],
```

`app/Availability/AvailabilityConfig.php` debe ser:

```php
<?php

namespace App\Availability;

final class AvailabilityConfig
{
    public const TTL_ENV = 'COMMERCE_AVAILABILITY_TTL_MINUTES';

    public const HOLD_ENV = 'COMMERCE_CART_HOLD_MINUTES';

    public static function assertValid(mixed $ttl, mixed $hold): void
    {
        self::minutes($ttl, self::TTL_ENV);
        self::minutes($hold, self::HOLD_ENV);
    }

    public static function ttlMinutes(): int
    {
        return self::minutes(config('commerce.availability.ttl_minutes'), self::TTL_ENV);
    }

    public static function holdMinutes(): int
    {
        return self::minutes(config('commerce.availability.hold_minutes'), self::HOLD_ENV);
    }

    /**
     * Composer runs package:discover before any environment file exists (fresh clone, CI).
     * That build step is the only command exempt from the boot-time check.
     */
    public static function mustValidate(bool $runningInConsole, array $argv): bool
    {
        return ! ($runningInConsole && ($argv[1] ?? null) === 'package:discover');
    }

    private static function minutes(mixed $value, string $env): int
    {
        $minutes = match (true) {
            is_int($value) => $value,
            is_string($value) && preg_match('/^\d{1,9}$/', trim($value)) === 1 => (int) trim($value),
            default => 0,
        };
        if ($minutes < 1) {
            throw new InvalidAvailabilityConfiguration($env.' must be a positive whole number of minutes. Configure it for this environment; there is no default.');
        }

        return $minutes;
    }
}
```

`app/Availability/InvalidAvailabilityConfiguration.php` debe ser:

```php
<?php

namespace App\Availability;

use RuntimeException;

class InvalidAvailabilityConfiguration extends RuntimeException {}
```

Run: `git diff -- config/commerce.php && cat app/Availability/AvailabilityConfig.php`
Expected: coincide con lo anterior.

- [ ] **Step 2: Escribir el test que falla**

Crear `tests/Feature/AvailabilityConfigTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Availability\AvailabilityConfig;
use App\Availability\InvalidAvailabilityConfiguration;
use App\Providers\AppServiceProvider;
use Tests\TestCase;

class AvailabilityConfigTest extends TestCase
{
    public function test_valid_minutes_are_accepted_from_env_strings_and_integers(): void
    {
        AvailabilityConfig::assertValid('30', 60);
        config(['commerce.availability.ttl_minutes' => ' 45 ', 'commerce.availability.hold_minutes' => '90']);
        $this->assertSame(45, AvailabilityConfig::ttlMinutes());
        $this->assertSame(90, AvailabilityConfig::holdMinutes());
    }

    public function test_missing_or_invalid_ttl_is_rejected(): void
    {
        foreach ([null, '', '0', '-5', '1.5', 'abc', 0, -1, false, []] as $value) {
            try {
                AvailabilityConfig::assertValid($value, 60);
                $this->fail('Accepted invalid TTL: '.var_export($value, true));
            } catch (InvalidAvailabilityConfiguration $exception) {
                $this->assertStringContainsString('COMMERCE_AVAILABILITY_TTL_MINUTES', $exception->getMessage());
            }
        }
    }

    public function test_invalid_hold_minutes_are_rejected(): void
    {
        $this->expectException(InvalidAvailabilityConfiguration::class);
        $this->expectExceptionMessage('COMMERCE_CART_HOLD_MINUTES');
        AvailabilityConfig::assertValid('30', '0');
    }

    public function test_application_boot_fails_without_ttl(): void
    {
        config(['commerce.availability.ttl_minutes' => null]);
        $this->expectException(InvalidAvailabilityConfiguration::class);
        (new AppServiceProvider($this->app))->boot();
    }

    public function test_only_composer_package_discovery_skips_validation(): void
    {
        $this->assertFalse(AvailabilityConfig::mustValidate(true, ['artisan', 'package:discover']));
        foreach ([['artisan', 'serve'], ['artisan', 'migrate'], ['artisan', 'test'], ['artisan', 'key:generate'], ['artisan']] as $argv) {
            $this->assertTrue(AvailabilityConfig::mustValidate(true, $argv));
        }
        $this->assertTrue(AvailabilityConfig::mustValidate(false, ['index.php', 'package:discover']));
    }
}
```

- [ ] **Step 3: Correr el test y verificar que falla**

Run: `COMMERCE_AVAILABILITY_TTL_MINUTES=10080 php artisan test --filter=AvailabilityConfigTest`
Expected: FAIL solo en `test_application_boot_fails_without_ttl` (el provider todavía no valida).

- [ ] **Step 4: Validar en el arranque**

En `app/Providers/AppServiceProvider.php` agregar `use App\Availability\AvailabilityConfig;` y convertir estas líneas en las primeras de `boot()`:

```php
        // V1-B: a missing or invalid availability TTL stops the application at boot, never hides the catalog at runtime.
        if (AvailabilityConfig::mustValidate($this->app->runningInConsole(), $_SERVER['argv'] ?? [])) {
            AvailabilityConfig::assertValid(config('commerce.availability.ttl_minutes'), config('commerce.availability.hold_minutes'));
        }
```

- [ ] **Step 5: Configurar la variable por entorno**

En `phpunit.xml`, dentro de `<php>`, después de `<env name="NIGHTWATCH_ENABLED" value="false"/>`:

```xml
        <env name="COMMERCE_AVAILABILITY_TTL_MINUTES" value="10080"/>
```

En `.env.example`, antes del bloque final `# No supplier or payment credentials…`:

```dotenv
# V1-B availability (docs/V1-B-SCOPE.md). Required: the application refuses to boot without it.
# Minutes an offer observation stays fresh, for manual and supplier data alike. The expected value
# differs per environment: local/testing use a long window so manual test data does not need to be
# re-observed during the day; production must use a short window matching the supplier polling
# cadence (still to be defined). Never copy this local value to staging or production.
COMMERCE_AVAILABILITY_TTL_MINUTES=10080
# Local cart hold (V1-B-SCOPE §5). Defaults to 60 when unset.
# COMMERCE_CART_HOLD_MINUTES=60
```

En `docs/ENVIRONMENTS-RELEASE.md`, en la tabla de "Matriz de configuración", agregar esta fila inmediatamente después de la fila `APP_DEBUG` (mismas cinco columnas):

```markdown
| COMMERCE_AVAILABILITY_TTL_MINUTES | Obligatoria; ventana larga para datos manuales de prueba | Obligatoria; ventana larga (`phpunit.xml`, `.env.example`) | Obligatoria; según cadencia de polling a definir | Obligatoria y corta; sin ella la aplicación no arranca |
```

- [ ] **Step 6: Correr el test y verificar que pasa**

Run: `COMMERCE_AVAILABILITY_TTL_MINUTES=10080 php artisan test --filter=AvailabilityConfigTest`
Expected: PASS, 5 tests.

- [ ] **Step 7: Verificar que el arranque falla de verdad sin la variable**

Run: `php artisan about --only=environment; echo "exit=$?"`
Expected: exit distinto de 0 con el mensaje `COMMERCE_AVAILABILITY_TTL_MINUTES must be a positive whole number of minutes` (el `.env` local todavía no la tiene).

Run: `php artisan package:discover --ansi; echo "exit=$?"`
Expected: exit 0.

- [ ] **Step 8: Suite completa sin regresiones**

Run: `COMMERCE_AVAILABILITY_TTL_MINUTES=10080 php artisan test`
Expected: 121 tests (116 + 5), 0 failures.

- [ ] **Step 9: Punto de control (sin commit)**

Run: `git status --short && git diff --stat`
Expected: cambios solo en los archivos de esta tarea.

### Task 2: Resolución de disponibilidad (PHP = SQL) y tabla de reservas

**Files:**
- Verify: `app/Availability/AvailabilityState.php`, `app/Availability/Availability.php`, `app/Contracts/ProductAvailability.php`
- Create: `app/Availability/PreferredOfferAvailability.php`
- Create: `database/migrations/2026_09_19_000001_create_stock_holds_table.php`, `app/Models/StockHold.php`
- Modify: `database/factories/ProductFactory.php` (estado `sellable`)
- Modify: `app/Providers/AppServiceProvider.php` (`register()`)
- Test: `tests/Feature/AvailabilityResolutionTest.php`

**Interfaces:**
- Consumes: `AvailabilityConfig::ttlMinutes()` (Task 1).
- Produces:
  - `enum AvailabilityState: string { Available, Unavailable, Stale, Unknown }` con `isPubliclyVisible(): bool`, `label(): string`.
  - `final readonly class Availability(AvailabilityState $state, ?int $quantity, ?CarbonImmutable $observedAt, ?string $source, ?int $offerId)` con `static unknown(): self`, `toPublic(): array{state:string,quantity:int}`.
  - `interface ProductAvailability { forProducts(array $productIds, ?string $holder = null, bool $lock = false): array<int, Availability> }`.
  - `PreferredOfferAvailability::now(): CarbonImmutable` (segundo truncado), `::resolve(SupplierProduct, bool $supplierActive, int $heldByOthers, CarbonImmutable $now, int $ttl): Availability`, `::constrainVisible(Query\Builder, CarbonImmutable $now, int $ttl): Query\Builder`.
  - Modelo `App\Models\StockHold` (tabla `stock_holds`: `product_id`, `supplier_product_id`, `holder` char(64) nullable, `order_id` uuid nullable, `quantity`, `expires_at`).
  - `ProductFactory::sellable(int $stock = 1000, array $offer = []): static`.

- [ ] **Step 1: Verificar archivos ya escritos**

`app/Availability/AvailabilityState.php`:

```php
<?php

namespace App\Availability;

enum AvailabilityState: string
{
    case Available = 'available';
    case Unavailable = 'unavailable';
    case Stale = 'stale';
    case Unknown = 'unknown';

    /** Stale and unknown products are hidden from the public catalog (V1-B-SCOPE §2-§3). */
    public function isPubliclyVisible(): bool
    {
        return $this === self::Available || $this === self::Unavailable;
    }

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Disponible',
            self::Unavailable => 'No disponible',
            self::Stale => 'Vencido',
            self::Unknown => 'Desconocido',
        };
    }
}
```

`app/Availability/Availability.php`:

```php
<?php

namespace App\Availability;

use Carbon\CarbonImmutable;

/**
 * Availability of one product with provenance. `quantity` is the sellable amount for the
 * viewer: offer stock minus active holds of other carts. Offer data is internal only.
 */
final readonly class Availability
{
    public function __construct(
        public AvailabilityState $state,
        public ?int $quantity,
        public ?CarbonImmutable $observedAt,
        public ?string $source,
        public ?int $offerId,
    ) {}

    public static function unknown(): self
    {
        return new self(AvailabilityState::Unknown, null, null, null, null);
    }

    /** Public projection: state and exact quantity only (V1-B-SCOPE §4). */
    public function toPublic(): array
    {
        return $this->state === AvailabilityState::Available
            ? ['state' => 'available', 'quantity' => $this->quantity]
            : ['state' => 'unavailable', 'quantity' => 0];
    }
}
```

`app/Contracts/ProductAvailability.php`:

```php
<?php

namespace App\Contracts;

use App\Availability\Availability;

interface ProductAvailability
{
    /**
     * Resolve availability for products. Holds owned by $holder do not reduce the quantity it sees.
     * With $lock, the offer rows are locked for update; call inside a transaction.
     *
     * @param  array<int>  $productIds
     * @return array<int, Availability> keyed by product id
     */
    public function forProducts(array $productIds, ?string $holder = null, bool $lock = false): array;
}
```

- [ ] **Step 2: Escribir el test que falla**

Crear `tests/Feature/AvailabilityResolutionTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Availability\Availability;
use App\Availability\AvailabilityState;
use App\Availability\PreferredOfferAvailability;
use App\Contracts\ProductAvailability;
use App\Models\Product;
use App\Models\ProductCommercialSetting;
use App\Models\StockHold;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AvailabilityResolutionTest extends TestCase
{
    use RefreshDatabase;

    private const TTL = 60;

    protected function setUp(): void
    {
        parent::setUp();
        config(['commerce.availability.ttl_minutes' => self::TTL]);
        $this->freezeSecond();
    }

    private function offer(array $attributes = [], bool $supplierActive = true, string $source = 'manual'): SupplierProduct
    {
        $product = Product::factory()->create(['status' => 'published']);
        $supplier = Supplier::create(['code' => 'qa-'.Str::lower(Str::random(10)), 'name' => 'QA', 'active' => $supplierActive]);
        $offer = new SupplierProduct(['supplier_id' => $supplier->id, 'supplier_sku' => 'QA-'.Str::random(8), 'currency' => 'CRC', 'stock' => 5, 'availability' => 'available', 'observed_at' => now()->subMinutes(10), 'active' => true, ...$attributes]);
        $offer->product_id = $product->id;
        $offer->source = $source;
        $offer->save();
        $setting = new ProductCommercialSetting;
        $setting->product_id = $product->id;
        $setting->preferred_offer_id = $offer->id;
        $setting->save();

        return $offer;
    }

    private function resolve(int $productId, ?string $holder = null): Availability
    {
        return app(ProductAvailability::class)->forProducts([$productId], $holder)[$productId];
    }

    private function visibleInSql(int $productId): bool
    {
        return Product::whereKey($productId)->whereExists(fn ($query) => PreferredOfferAvailability::constrainVisible($query, PreferredOfferAvailability::now(), self::TTL))->exists();
    }

    private function hold(SupplierProduct $offer, int $quantity, ?string $holder, int $minutesLeft = 30): void
    {
        (new StockHold)->forceFill(['product_id' => $offer->product_id, 'supplier_product_id' => $offer->id, 'holder' => $holder, 'quantity' => $quantity, 'expires_at' => now()->addMinutes($minutesLeft)])->save();
    }

    public function test_state_matrix_and_sql_visibility_agree(): void
    {
        $cases = [
            'available with stock' => [[], true, AvailabilityState::Available, 5],
            'available without stock (D1)' => [['stock' => null], true, AvailabilityState::Unknown, null],
            'available with zero stock' => [['stock' => 0], true, AvailabilityState::Unavailable, 0],
            'unavailable without stock (D2)' => [['availability' => 'unavailable', 'stock' => null], true, AvailabilityState::Unavailable, 0],
            'unavailable with zero stock' => [['availability' => 'unavailable', 'stock' => 0], true, AvailabilityState::Unavailable, 0],
            'unknown' => [['availability' => 'unknown', 'stock' => null], true, AvailabilityState::Unknown, null],
            'inactive offer (D4)' => [['active' => false], true, AvailabilityState::Unknown, null],
            'inactive supplier (D4)' => [[], false, AvailabilityState::Unknown, null],
            'future observation' => [['observed_at' => now()->addMinute()], true, AvailabilityState::Unknown, null],
            'stale' => [['observed_at' => now()->subMinutes(self::TTL + 1)], true, AvailabilityState::Stale, null],
        ];
        foreach ($cases as $name => [$attributes, $supplierActive, $state, $quantity]) {
            $offer = $this->offer($attributes, $supplierActive);
            $result = $this->resolve($offer->product_id);
            $this->assertSame($state, $result->state, $name);
            $this->assertSame($quantity, $result->quantity, $name);
            $this->assertSame($state->isPubliclyVisible(), $this->visibleInSql($offer->product_id), $name.' (SQL)');
        }
    }

    public function test_ttl_boundary_is_exclusive(): void
    {
        $fresh = $this->offer(['observed_at' => now()->subMinutes(self::TTL)->addSecond()]);
        $expired = $this->offer(['observed_at' => now()->subMinutes(self::TTL)]);
        $this->assertSame(AvailabilityState::Available, $this->resolve($fresh->product_id)->state);
        $this->assertTrue($this->visibleInSql($fresh->product_id));
        $this->assertSame(AvailabilityState::Stale, $this->resolve($expired->product_id)->state);
        $this->assertFalse($this->visibleInSql($expired->product_id));
    }

    public function test_ttl_applies_to_manual_and_supplier_data_alike(): void
    {
        foreach (['manual', 'eurocomp'] as $source) {
            $offer = $this->offer(['observed_at' => now()->subMinutes(self::TTL + 5)], true, $source);
            $result = $this->resolve($offer->product_id);
            $this->assertSame(AvailabilityState::Stale, $result->state, $source);
            $this->assertSame($source, $result->source);
        }
    }

    public function test_missing_or_foreign_preferred_offer_is_unknown(): void
    {
        $withoutOffer = Product::factory()->create(['status' => 'published']);
        $this->assertSame(AvailabilityState::Unknown, $this->resolve($withoutOffer->id)->state);
        $this->assertFalse($this->visibleInSql($withoutOffer->id));
        $foreign = $this->offer();
        $other = Product::factory()->create(['status' => 'published']);
        $setting = new ProductCommercialSetting;
        $setting->product_id = $other->id;
        $setting->preferred_offer_id = $foreign->id;
        $setting->save();
        $this->assertSame(AvailabilityState::Unknown, $this->resolve($other->id)->state);
        $this->assertFalse($this->visibleInSql($other->id));
    }

    public function test_active_holds_of_other_carts_reduce_the_quantity_seen(): void
    {
        $offer = $this->offer(['stock' => 5]);
        $this->hold($offer, 2, str_repeat('a', 64));
        $this->hold($offer, 1, null);
        $this->hold($offer, 4, str_repeat('c', 64), -1);
        $this->assertSame(2, $this->resolve($offer->product_id)->quantity);
        $this->assertSame(4, $this->resolve($offer->product_id, str_repeat('a', 64))->quantity);
        $this->hold($offer, 2, str_repeat('d', 64));
        $result = $this->resolve($offer->product_id);
        $this->assertSame(AvailabilityState::Unavailable, $result->state);
        $this->assertSame(0, $result->quantity);
        $this->assertTrue($this->visibleInSql($offer->product_id));
    }

    public function test_public_projection_exposes_only_state_and_quantity(): void
    {
        $this->assertSame(['state' => 'available', 'quantity' => 3], $this->resolve($this->offer(['stock' => 3])->product_id)->toPublic());
        $this->assertSame(['state' => 'unavailable', 'quantity' => 0], $this->resolve($this->offer(['availability' => 'unavailable', 'stock' => 0])->product_id)->toPublic());
    }

    public function test_sellable_factory_state_creates_a_fresh_preferred_offer(): void
    {
        $product = Product::factory()->sellable(7)->create(['status' => 'published']);
        $result = $this->resolve($product->id);
        $this->assertSame(AvailabilityState::Available, $result->state);
        $this->assertSame(7, $result->quantity);
        $this->assertSame('manual', $result->source);
    }
}
```

- [ ] **Step 3: Correr el test y verificar que falla**

Run: `COMMERCE_AVAILABILITY_TTL_MINUTES=10080 php artisan test --filter=AvailabilityResolutionTest`
Expected: FAIL (`Target [App\Contracts\ProductAvailability] is not instantiable` / tabla `stock_holds` inexistente / método `sellable` inexistente).

- [ ] **Step 4: Migración y modelo de reservas**

Crear `database/migrations/2026_09_19_000001_create_stock_holds_table.php`:

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
        Schema::create('stock_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_product_id')->constrained()->restrictOnDelete();
            // Hash of the cart holder token; null once the hold is attached to an order.
            $table->char('holder', 64)->nullable();
            $table->foreignUuid('order_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            // One cart hold per holder and product.
            $table->unique(['holder', 'product_id']);
            $table->index(['supplier_product_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_holds');
    }
};
```

Crear `app/Models/StockHold.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Local soft hold (V1-B-SCOPE §5). Never a reservation in the supplier's system.
 * Active while expires_at is in the future. A null holder means the hold belongs to an order.
 */
class StockHold extends Model
{
    protected $fillable = [];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'expires_at' => 'datetime'];
    }

    public function offer()
    {
        return $this->belongsTo(SupplierProduct::class, 'supplier_product_id');
    }
}
```

- [ ] **Step 5: Resolvedor PHP + SQL**

Crear `app/Availability/PreferredOfferAvailability.php`:

```php
<?php

namespace App\Availability;

use App\Contracts\ProductAvailability;
use App\Models\ProductCommercialSetting;
use App\Models\StockHold;
use App\Models\SupplierProduct;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;

/**
 * Availability from the preferred offer's manual data (V1-B-SCOPE §1-§2). No supplier API is
 * called. resolve() and constrainVisible() express the same rules in PHP and SQL; keep them equal.
 */
class PreferredOfferAvailability implements ProductAvailability
{
    public static function now(): CarbonImmutable
    {
        return now()->toImmutable()->startOfSecond();
    }

    public function forProducts(array $productIds, ?string $holder = null, bool $lock = false): array
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        if (! $productIds) {
            return [];
        }
        $result = array_fill_keys($productIds, Availability::unknown());
        $preferred = ProductCommercialSetting::whereIn('product_id', $productIds)->whereNotNull('preferred_offer_id')->pluck('preferred_offer_id', 'product_id');
        if ($preferred->isEmpty()) {
            return $result;
        }
        $now = self::now();
        $ttl = AvailabilityConfig::ttlMinutes();
        $query = SupplierProduct::whereIn('id', $preferred->values())->with('supplier:id,active')->orderBy('id');
        // Locking the offer row serializes every hold writer for that offer.
        $offers = ($lock ? $query->lockForUpdate() : $query)->get()->keyBy('id');
        $held = StockHold::query()->whereIn('supplier_product_id', $offers->keys())->where('expires_at', '>', $now)
            ->when($holder !== null, fn ($q) => $q->where(fn ($q) => $q->whereNull('holder')->orWhere('holder', '!=', $holder)))
            ->groupBy('supplier_product_id')->selectRaw('supplier_product_id, SUM(quantity) as held')->pluck('held', 'supplier_product_id');
        foreach ($preferred as $productId => $offerId) {
            $offer = $offers->get($offerId);
            $result[(int) $productId] = $offer && $offer->product_id === (int) $productId
                ? self::resolve($offer, (bool) $offer->supplier?->active, (int) ($held[$offerId] ?? 0), $now, $ttl)
                : Availability::unknown();
        }

        return $result;
    }

    public static function resolve(SupplierProduct $offer, bool $supplierActive, int $heldByOthers, CarbonImmutable $now, int $ttlMinutes): Availability
    {
        $observed = $offer->observed_at ? CarbonImmutable::instance($offer->observed_at) : null;
        $make = fn (AvailabilityState $state, ?int $quantity = null) => new Availability($state, $quantity, $observed, $offer->source, $offer->id);
        // Inactive offer or supplier, or an observation in the future: never inferred as available.
        if (! $offer->active || ! $supplierActive || $observed === null || $observed->greaterThan($now)) {
            return $make(AvailabilityState::Unknown);
        }
        // Fresh while strictly younger than the TTL; at exactly TTL minutes it is stale.
        if (! $observed->greaterThan($now->subMinutes($ttlMinutes))) {
            return $make(AvailabilityState::Stale);
        }

        return match ($offer->availability) {
            // Available without a confirmed quantity cannot show an exact number: unknown (D1).
            'available' => match (true) {
                $offer->stock === null => $make(AvailabilityState::Unknown),
                $offer->stock - $heldByOthers > 0 => $make(AvailabilityState::Available, $offer->stock - $heldByOthers),
                default => $make(AvailabilityState::Unavailable, 0),
            },
            // Unavailable with or without an explicit zero: shown as sold out (D2).
            'unavailable' => $make(AvailabilityState::Unavailable, 0),
            default => $make(AvailabilityState::Unknown),
        };
    }

    /** SQL counterpart of resolve(): the product has a fresh preferred offer that is publicly visible. */
    public static function constrainVisible(Builder $query, CarbonImmutable $now, int $ttlMinutes): Builder
    {
        return $query->selectRaw('1')->from('product_commercial_settings as availability_settings')
            ->join('supplier_products as availability_offers', 'availability_offers.id', '=', 'availability_settings.preferred_offer_id')
            ->join('suppliers as availability_suppliers', 'availability_suppliers.id', '=', 'availability_offers.supplier_id')
            ->whereColumn('availability_settings.product_id', 'products.id')
            ->whereColumn('availability_offers.product_id', 'products.id')
            ->where('availability_offers.active', true)
            ->where('availability_suppliers.active', true)
            ->where('availability_offers.observed_at', '>', $now->subMinutes($ttlMinutes))
            ->where('availability_offers.observed_at', '<=', $now)
            ->where(fn ($q) => $q->where(fn ($q) => $q->where('availability_offers.availability', 'available')->whereNotNull('availability_offers.stock'))
                ->orWhere('availability_offers.availability', 'unavailable'));
    }
}
```

- [ ] **Step 6: Binding y estado de factory**

En `app/Providers/AppServiceProvider.php` agregar `use App\Availability\PreferredOfferAvailability;` y `use App\Contracts\ProductAvailability;`; en `register()`, después del binding de `CartStore`:

```php
        $this->app->bind(ProductAvailability::class, PreferredOfferAvailability::class);
```

En `database/factories/ProductFactory.php` agregar los imports `use App\Models\Product;`, `use App\Models\ProductCommercialSetting;`, `use App\Models\Supplier;`, `use App\Models\SupplierProduct;` y este método después de `definition()`:

```php
    /** Fresh preferred manual offer, so the product stays publicly sellable under V1-B rules. */
    public function sellable(int $stock = 1000, array $offer = []): static
    {
        return $this->afterCreating(function (Product $product) use ($stock, $offer) {
            $supplier = Supplier::create(['code' => 'test-'.Str::lower(Str::random(12)), 'name' => 'Proveedor de prueba', 'active' => true]);
            $row = new SupplierProduct(['supplier_id' => $supplier->id, 'supplier_sku' => 'TEST-'.Str::upper(Str::random(10)), 'currency' => $product->currency, 'stock' => $stock, 'availability' => 'available', 'observed_at' => now(), 'active' => true, ...$offer]);
            $row->product_id = $product->id;
            $row->source = 'manual';
            $row->save();
            $setting = new ProductCommercialSetting;
            $setting->product_id = $product->id;
            $setting->preferred_offer_id = $row->id;
            $setting->save();
        });
    }
```

- [ ] **Step 7: Correr el test y verificar que pasa**

Run: `COMMERCE_AVAILABILITY_TTL_MINUTES=10080 php artisan test --filter=AvailabilityResolutionTest`
Expected: PASS, 7 tests.

- [ ] **Step 8: Suite completa y estilo**

Run: `COMMERCE_AVAILABILITY_TTL_MINUTES=10080 php artisan test && php vendor/bin/pint --test`
Expected: 128 tests, 0 failures; Pint passed. (Nada público cambia todavía.)

- [ ] **Step 9: Punto de control del Lote 1 (sin commit)**

Run: `git status --short && git diff --stat`
Expected: archivos de Tareas 1–2. Detenerse para revisión del propietario.

### Task 3: Catálogo público filtrado por disponibilidad

**Files:**
- Modify: `app/Models/Product.php` (scope `storefrontVisible`)
- Modify: `app/Http/Controllers/PublicCatalogController.php` (constructor, helpers, `home`, final de `index`, `show`)
- Modify: `app/Http/Controllers/CatalogImageController.php:18`
- Modify (solo fixtures): `tests/Feature/StorefrontTest.php:26,111`, `tests/Feature/CatalogTest.php:174,213`, `tests/Feature/CommercialCatalogTest.php:311`
- Test: `tests/Feature/StorefrontAvailabilityTest.php`

**Interfaces:**
- Consumes: `ProductAvailability::forProducts`, `Availability::toPublic`, `PreferredOfferAvailability::constrainVisible/now`, `AvailabilityConfig::ttlMinutes`, `ProductFactory::sellable`, `CartHolder::current()` (creado aquí si todavía no existe; ver Step 3).
- Produces: `Product::scopeStorefrontVisible(Builder): Builder`; prop Inertia `availability: Record<slug, {state: 'available'|'unavailable', quantity: int}>` en `Home`, `catalog/Index` y `catalog/Show`.

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Feature/StorefrontAvailabilityTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Availability\AvailabilityConfig;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\SupplierProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StorefrontAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function product(int $stock = 5, array $offer = [], array $attributes = []): Product
    {
        return Product::factory()->sellable($stock, $offer)->create(['status' => 'published', 'published_at' => now(), 'featured' => true, ...$attributes]);
    }

    private function offerOf(Product $product): SupplierProduct
    {
        return SupplierProduct::where('product_id', $product->id)->firstOrFail();
    }

    private function staleObservation(): array
    {
        return ['observed_at' => now()->subMinutes(AvailabilityConfig::ttlMinutes() + 1)];
    }

    public function test_available_product_shows_its_exact_quantity_on_every_public_page(): void
    {
        $product = $this->product(3);
        foreach (['/', '/catalog', '/catalog/'.$product->slug] as $url) {
            $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('availability.'.$product->slug.'.state', 'available')
                ->where('availability.'.$product->slug.'.quantity', 3));
        }
    }

    public function test_sold_out_product_stays_visible_as_unavailable(): void
    {
        $product = $this->product(0, ['availability' => 'unavailable']);
        $this->get('/catalog')->assertInertia(fn (Assert $page) => $page->where('products.total', 1)
            ->where('availability.'.$product->slug, ['state' => 'unavailable', 'quantity' => 0]));
        $this->get('/catalog/'.$product->slug)->assertOk();
    }

    public function test_stale_unknown_and_unconfigured_products_are_hidden_everywhere(): void
    {
        $hidden = [
            $this->product(5, $this->staleObservation()),
            $this->product(5, ['availability' => 'unknown', 'stock' => null]),
            $this->product(5, ['stock' => null]),
            Product::factory()->create(['status' => 'published', 'published_at' => now(), 'featured' => true]),
        ];
        $visible = $this->product();
        foreach ($hidden as $product) {
            $image = ProductImage::factory()->create(['product_id' => $product->id]);
            $this->get('/catalog/'.$product->slug)->assertNotFound();
            $this->get('/catalog-images/'.$image->id)->assertNotFound();
            $this->get('/catalog?'.http_build_query(['q' => $product->sku]))->assertInertia(fn (Assert $page) => $page->where('products.total', 0));
        }
        $this->get('/catalog')->assertInertia(fn (Assert $page) => $page->where('products.total', 1)->where('products.data.0.slug', $visible->slug));
        $this->get('/')->assertInertia(fn (Assert $page) => $page->has('featured', 1)->has('recent', 1)->where('featured.0.slug', $visible->slug));
    }

    public function test_hidden_products_are_excluded_from_related_items(): void
    {
        $product = $this->product();
        $related = $this->product(5, [], ['category_id' => $product->category_id]);
        $this->product(5, $this->staleObservation(), ['category_id' => $product->category_id]);
        $this->get('/catalog/'.$product->slug)->assertInertia(fn (Assert $page) => $page->has('related', 1)->where('related.0.slug', $related->slug));
    }

    public function test_supplier_outage_empties_its_category_without_failing(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $first = $this->product(5, [], ['category_id' => $category->id]);
        $second = $this->product(5, [], ['category_id' => $category->id]);
        $this->get('/catalog?category='.$category->slug)->assertInertia(fn (Assert $page) => $page->where('products.total', 2));
        SupplierProduct::whereIn('product_id', [$first->id, $second->id])->update(['observed_at' => now()->subMinutes(AvailabilityConfig::ttlMinutes() + 30)]);
        $this->get('/catalog?category='.$category->slug)->assertOk()->assertInertia(fn (Assert $page) => $page->where('products.total', 0)->where('activeCategory.name', $category->name));
    }

    public function test_inactive_offer_or_supplier_hides_the_product(): void
    {
        $offerOff = $this->product();
        $this->offerOf($offerOff)->update(['active' => false]);
        $supplierOff = $this->product();
        $this->offerOf($supplierOff)->supplier->update(['active' => false]);
        foreach ([$offerOff, $supplierOff] as $product) {
            $this->get('/catalog/'.$product->slug)->assertNotFound();
        }
    }

    public function test_public_availability_exposes_only_state_and_quantity(): void
    {
        $product = $this->product(4);
        $response = $this->get('/catalog/'.$product->slug)->assertOk();
        $this->assertSame(['state', 'quantity'], array_keys($response->viewData('page')['props']['availability'][$product->slug]));
        foreach (['observed_at', 'offerId', 'supplier_sku', 'supplier_product_id', 'Proveedor de prueba'] as $private) {
            $response->assertDontSee($private, false);
        }
    }
}
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `COMMERCE_AVAILABILITY_TTL_MINUTES=10080 php artisan test --filter=StorefrontAvailabilityTest`
Expected: FAIL (prop `availability` inexistente; productos ocultos todavía visibles).

- [ ] **Step 3: Crear `CartHolder` (identidad de carrito, usada desde aquí para excluir reservas propias)**

Crear `app/Support/CartHolder.php`:

```php
<?php

namespace App\Support;

use Illuminate\Support\Str;

/** Identifies the session cart that owns local holds. Only a hash reaches the database. */
final class CartHolder
{
    private const KEY = 'cart_holder';

    public static function current(): ?string
    {
        $token = session(self::KEY);

        return is_string($token) ? hash('sha256', $token) : null;
    }

    public static function ensure(): string
    {
        if (! is_string(session(self::KEY))) {
            session()->put(self::KEY, Str::random(64));
        }

        return self::current();
    }
}
```

- [ ] **Step 4: Scope de visibilidad comercial**

En `app/Models/Product.php` agregar `use App\Availability\AvailabilityConfig;` y `use App\Availability\PreferredOfferAvailability;`, y este método después de `scopePubliclyVisible`:

```php
    /** Editorially public and with a fresh, publicly visible preferred offer (V1-B-SCOPE §2, D9). */
    public function scopeStorefrontVisible(Builder $query): Builder
    {
        return $query->publiclyVisible()->whereExists(fn ($offers) => PreferredOfferAvailability::constrainVisible($offers, PreferredOfferAvailability::now(), AvailabilityConfig::ttlMinutes()));
    }
```

- [ ] **Step 5: Controlador del catálogo**

En `app/Http/Controllers/PublicCatalogController.php` agregar imports `use App\Availability\Availability;`, `use App\Contracts\ProductAvailability;`, `use App\Support\CartHolder;`, `use Illuminate\Support\Collection;`.

Reemplazar los métodos `products()` y `cards()` por:

```php
    public function __construct(private ProductAvailability $availability) {}

    private function products()
    {
        return Product::storefrontVisible()->with(['category', 'brand', 'images']);
    }

    private function cards(Collection $products): array
    {
        return $products->map(fn ($product) => (new PublicProductResource($product))->resolve())->values()->all();
    }

    /** Public availability keyed by slug: state and exact quantity only (V1-B-SCOPE §4). */
    private function availabilityFor(Collection $products): array
    {
        if ($products->isEmpty()) {
            return [];
        }
        $resolved = $this->availability->forProducts($products->pluck('id')->all(), CartHolder::current());

        return $products->mapWithKeys(fn ($product) => [$product->slug => ($resolved[$product->id] ?? Availability::unknown())->toPublic()])->all();
    }
```

Reemplazar `home()` por:

```php
    public function home()
    {
        $featured = $this->products()->where('featured', true)->orderBy('id')->limit(4)->get();
        $recent = $this->products()->orderByDesc('published_at')->orderByDesc('id')->limit(4)->get();
        $offers = $this->products()->whereColumn('previous_price_minor', '>', 'price_minor')->orderBy('id')->limit(4)->get();

        return $this->render('Home', [
            'featured' => $this->cards($featured), 'recent' => $this->cards($recent), 'offers' => $this->cards($offers),
            'availability' => $this->availabilityFor($featured->concat($recent)->concat($offers)->unique('id')),
            'categories' => $this->taxonomy($this->categories()),
            'brands' => Brand::where('status', 'published')->orderBy('name')->get(['name', 'slug'])->toArray(),
        ], config('storefront.tagline'), config('storefront.description'), route('home'));
    }
```

En `index()`, reemplazar las dos líneas que empiezan con `$products = $query->orderBy(` por:

```php
        $page = $query->orderBy($column, $direction)->orderByDesc('id')->paginate(12)->appends($filters);
        $availability = $this->availabilityFor($page->getCollection());
        $products = $page->through(fn ($product) => (new PublicProductResource($product))->resolve());
```

y en el array de `render('catalog/Index', [...])` cambiar `'products' => $products,` por `'products' => $products, 'availability' => $availability,`.

Reemplazar `show()` por:

```php
    public function show(string $slug)
    {
        $product = $this->products()->where('slug', $slug)->firstOrFail();
        $related = $this->products()->where('category_id', $product->category_id)->whereKeyNot($product->id)->orderByDesc('featured')->orderBy('id')->limit(4)->get();
        $data = (new PublicProductResource($product))->resolve();

        return $this->render('catalog/Show', [
            'product' => $data,
            'related' => $this->cards($related),
            'availability' => $this->availabilityFor(collect([$product])->concat($related)),
        ], $product->meta_title ?: $product->name, $product->meta_description ?: mb_substr($product->short_description, 0, 170), route('catalog.show', $product->slug), collect($data['images'])->firstWhere('is_primary', true)['url'] ?? null);
    }
```

En `app/Http/Controllers/CatalogImageController.php:18` cambiar `Product::publiclyVisible()` por `Product::storefrontVisible()`.

- [ ] **Step 6: Correr el test nuevo y verificar que pasa**

Run: `COMMERCE_AVAILABILITY_TTL_MINUTES=10080 php artisan test --filter=StorefrontAvailabilityTest`
Expected: PASS, 7 tests.

- [ ] **Step 7: Ver las regresiones esperadas por D10**

Run: `COMMERCE_AVAILABILITY_TTL_MINUTES=10080 php artisan test`
Expected: FAIL solo en tests que esperan ver públicos productos sin oferta: `StorefrontTest` (varios), `CatalogTest::test_drafts_archived_products_and_inactive_ancestors_are_invisible_publicly`, `CatalogTest::test_image_upload_validation_and_private_visibility`, `CommercialCatalogTest::test_archiving_demo_is_non_destructive_and_preserves_real_products`.

- [ ] **Step 8: Ajustar solo fixtures (sin tocar assertions)**

- `tests/Feature/StorefrontTest.php:26`: `return Product::factory()->sellable()->create(['status' => 'published', 'published_at' => now(), ...$attributes]);`
- `tests/Feature/StorefrontTest.php:111`: `Product::factory()->count(14)->sellable()->create(['status' => 'published', 'category_id' => $category->id, 'featured' => true, 'name' => 'Laptop']);`
- `tests/Feature/CatalogTest.php:174`: `$product = Product::factory()->sellable()->create();`
- `tests/Feature/CatalogTest.php:213`: `$product = Product::factory()->sellable()->create();`
- `tests/Feature/CommercialCatalogTest.php:311`: `$real = Product::factory()->sellable()->create(['status' => 'published', 'is_demo' => false]);`

- [ ] **Step 9: Suite completa**

Run: `COMMERCE_AVAILABILITY_TTL_MINUTES=10080 php artisan test && php vendor/bin/pint --test`
Expected: 135 tests, 0 failures; Pint passed. Si falla otro test por la misma causa, aplicar el mismo cambio de fixture en esa línea y listarlo en el reporte (Task 8).

- [ ] **Step 10: Punto de control del Lote 2 (sin commit)**

Run: `git status --short && git diff --stat -- tests/`
Expected: en `tests/` existentes solo líneas de fixture. Detenerse para revisión.

### Task 4: Reserva local en el carrito

**Files:**
- Create: `app/Services/StockHolds.php`
- Modify: `app/Services/CartService.php` (archivo completo abajo)
- Modify: `app/Http/Controllers/AuthController.php` (`logout`)
- Modify (solo fixture): `tests/Feature/CartTest.php:27`
- Test: `tests/Feature/StockHoldTest.php`

**Interfaces:**
- Consumes: `ProductAvailability::forProducts(..., lock: true)`, `AvailabilityConfig::holdMinutes()`, `PreferredOfferAvailability::now()`, `CartHolder::current()/ensure()`, `HoldUnavailable`, `StockHold`.
- Produces:
  - `StockHolds::reserve(string $holder, Product $product, int $quantity): void` (lanza `HoldUnavailable` con mensaje apto para el comprador).
  - `StockHolds::release(?string $holder, int $productId): void`, `releaseAll(?string $holder): void`.
  - `StockHolds::active(?string $holder, array $productIds, bool $lock = false): Collection<int, StockHold>` (clave: product_id).
  - `StockHolds::convert(string $holder, Order $order, array $items): void` (usado en Task 5), `prune(): int` (Task 6).
  - Carrito: nuevos valores de `reason` en líneas: `'out_of_stock'`, `'hold_expired'`; nueva clave a nivel carrito `hold_expires_at: string|null` (ISO).

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Feature/StockHoldTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Availability\AvailabilityConfig;
use App\Models\Product;
use App\Models\StockHold;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Support\CartHolder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StockHoldTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['commerce.availability.hold_minutes' => 60]);
        $this->freezeSecond();
    }

    private function product(int $stock = 5): Product
    {
        return Product::factory()->sellable($stock)->create(['status' => 'published', 'published_at' => now()]);
    }

    private function mutation(array $extra = []): array
    {
        return ['mutation_id' => (string) Str::uuid(), 'revision' => session('shopping_cart.revision', 0), ...$extra];
    }

    private function add(Product $product, int $quantity = 1)
    {
        return $this->from('/cart')->post('/cart/items', $this->mutation(['product_slug' => $product->slug, 'quantity' => $quantity]));
    }

    private function line(): string
    {
        return array_key_first(session('shopping_cart.items'));
    }

    private function cart(): array
    {
        return $this->get('/cart')->assertOk()->viewData('page')['props']['cart'];
    }

    private function newVisitor(): void
    {
        $this->app['session.store']->flush();
        $this->app['session.store']->regenerate();
    }

    private function hold(Product $product): StockHold
    {
        return StockHold::where('product_id', $product->id)->whereNull('order_id')->sole();
    }

    public function test_adding_creates_a_local_hold_with_the_configured_expiry(): void
    {
        $product = $this->product();
        $this->add($product, 2)->assertSessionHasNoErrors();
        $hold = $this->hold($product);
        $this->assertSame(CartHolder::current(), $hold->holder);
        $this->assertSame(2, $hold->quantity);
        $this->assertTrue($hold->expires_at->equalTo(now()->addMinutes(60)));
        $this->assertSame(now()->addMinutes(60)->toISOString(), $this->cart()['hold_expires_at']);
    }

    public function test_cannot_hold_more_than_the_sellable_quantity(): void
    {
        $product = $this->product(3);
        $this->add($product, 4)->assertSessionHasErrors(['cart' => 'Solo quedan 3 unidades disponibles.']);
        $this->assertDatabaseCount('stock_holds', 0);
        $this->assertSame([], $this->cart()['lines']);
    }

    public function test_two_carts_compete_for_the_last_unit(): void
    {
        $product = $this->product(1);
        $this->add($product)->assertSessionHasNoErrors();
        $this->newVisitor();
        $this->add($product)->assertSessionHasErrors(['cart' => 'Este producto está agotado.']);
        $this->get('/catalog/'.$product->slug)->assertInertia(fn (Assert $page) => $page->where('availability.'.$product->slug, ['state' => 'unavailable', 'quantity' => 0]));
    }

    public function test_other_carts_see_stock_minus_holds_while_the_owner_sees_full_stock(): void
    {
        $product = $this->product(5);
        $this->add($product, 2);
        $this->get('/catalog/'.$product->slug)->assertInertia(fn (Assert $page) => $page->where('availability.'.$product->slug.'.quantity', 5));
        $this->newVisitor();
        $this->get('/catalog/'.$product->slug)->assertInertia(fn (Assert $page) => $page->where('availability.'.$product->slug.'.quantity', 3));
    }

    public function test_expired_hold_blocks_the_line_until_it_is_renewed(): void
    {
        $product = $this->product(1);
        $this->add($product);
        $line = $this->line();
        $this->travel(60)->minutes();
        $cart = $this->cart();
        $this->assertSame('hold_expired', $cart['lines'][0]['reason']);
        $this->assertFalse($cart['lines'][0]['available']);
        $this->assertNull($cart['total_minor']);
        $this->assertNull($cart['hold_expires_at']);
        $this->from('/cart')->patch('/cart/items/'.$line, $this->mutation(['quantity' => 1]))->assertSessionHasNoErrors();
        $this->assertNull($this->cart()['lines'][0]['reason']);
        $this->assertTrue($this->hold($product)->expires_at->equalTo(now()->addMinutes(60)));
    }

    public function test_expired_hold_frees_the_unit_for_other_carts(): void
    {
        $product = $this->product(1);
        $this->add($product);
        $this->newVisitor();
        $this->add($product)->assertSessionHasErrors('cart');
        $this->travel(60)->minutes();
        $this->add($product)->assertSessionHasNoErrors();
    }

    public function test_changing_quantity_or_adding_again_keeps_the_original_expiry(): void
    {
        $product = $this->product(5);
        $this->add($product);
        $expiry = $this->hold($product)->expires_at;
        $this->travel(30)->minutes();
        $this->from('/cart')->patch('/cart/items/'.$this->line(), $this->mutation(['quantity' => 3]))->assertSessionHasNoErrors();
        $this->assertSame(3, $this->hold($product)->quantity);
        $this->assertTrue($this->hold($product)->expires_at->equalTo($expiry));
        $this->add($product)->assertSessionHasNoErrors();
        $this->assertSame(4, $this->hold($product)->quantity);
        $this->assertTrue($this->hold($product)->expires_at->equalTo($expiry));
    }

    public function test_remove_clear_and_logout_release_holds(): void
    {
        $first = $this->product();
        $second = $this->product();
        $this->add($first);
        $this->add($second);
        $this->delete('/cart/items/'.$this->line(), $this->mutation())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('stock_holds', 1);
        $this->delete('/cart', $this->mutation())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('stock_holds', 0);
        $user = User::factory()->create(['password' => 'HoldPassword123!']);
        $this->add($first);
        $this->post('/login', ['email' => $user->email, 'password' => 'HoldPassword123!'])->assertRedirect('/account');
        $this->assertDatabaseCount('stock_holds', 1);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertDatabaseCount('stock_holds', 0);
    }

    public function test_lowered_stock_marks_the_line_out_of_stock_until_the_quantity_fits(): void
    {
        $product = $this->product(5);
        $this->add($product, 3);
        SupplierProduct::where('product_id', $product->id)->update(['stock' => 2]);
        $this->assertSame('out_of_stock', $this->cart()['lines'][0]['reason']);
        $this->from('/cart')->patch('/cart/items/'.$this->line(), $this->mutation(['quantity' => 3]))->assertSessionHasErrors(['cart' => 'Solo quedan 2 unidades disponibles.']);
        $this->from('/cart')->patch('/cart/items/'.$this->line(), $this->mutation(['quantity' => 2]))->assertSessionHasNoErrors();
        $this->assertNull($this->cart()['lines'][0]['reason']);
    }

    public function test_stale_data_hides_a_held_line_without_leaking_it(): void
    {
        $product = $this->product();
        $this->add($product);
        SupplierProduct::where('product_id', $product->id)->update(['observed_at' => now()->subMinutes(AvailabilityConfig::ttlMinutes() + 1)]);
        $line = $this->cart()['lines'][0];
        $this->assertSame('unpublished', $line['reason']);
        $this->assertSame('Producto no disponible', $line['name']);
        $this->assertNull($line['slug']);
    }

    public function test_holds_never_contact_a_supplier(): void
    {
        Http::fake();
        $product = $this->product();
        $this->add($product, 2)->assertSessionHasNoErrors();
        $this->delete('/cart', $this->mutation())->assertSessionHasNoErrors();
        Http::assertNothingSent();
        $this->assertFalse(config('commerce.suppliers.eurocomp.enabled'));
        $this->assertSame([], config('commerce.suppliers.eurocomp.capabilities'));
    }
}
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `COMMERCE_AVAILABILITY_TTL_MINUTES=10080 php artisan test --filter=StockHoldTest`
Expected: FAIL (no se crean reservas).

- [ ] **Step 3: Servicio de reservas**

Crear `app/Services/StockHolds.php`:

```php
<?php

namespace App\Services;

use App\Availability\Availability;
use App\Availability\AvailabilityConfig;
use App\Availability\AvailabilityState;
use App\Availability\HoldUnavailable;
use App\Availability\PreferredOfferAvailability;
use App\Contracts\ProductAvailability;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockHold;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Local soft holds against double selling (V1-B-SCOPE §5). Writers lock the offer row first,
 * so concurrent holds on the same offer are serialized. Expiry needs no job: a hold only
 * counts while expires_at is in the future; holds:prune just removes old rows.
 */
class StockHolds
{
    public function __construct(private ProductAvailability $availability) {}

    /** Create or resize the holder's hold. Resizing keeps the original expiry (D7). */
    public function reserve(string $holder, Product $product, int $quantity): void
    {
        DB::transaction(function () use ($holder, $product, $quantity) {
            $availability = $this->availability->forProducts([$product->id], $holder, true)[$product->id] ?? Availability::unknown();
            if (! $availability->state->isPubliclyVisible()) {
                throw new HoldUnavailable('Este producto ya no está disponible en el catálogo.');
            }
            if ($availability->state !== AvailabilityState::Available) {
                throw new HoldUnavailable('Este producto está agotado.');
            }
            if ($quantity > $availability->quantity) {
                throw new HoldUnavailable($availability->quantity === 1 ? 'Solo queda 1 unidad disponible.' : 'Solo quedan '.$availability->quantity.' unidades disponibles.');
            }
            $now = PreferredOfferAvailability::now();
            $hold = StockHold::where('holder', $holder)->where('product_id', $product->id)->lockForUpdate()->first();
            $keepExpiry = $hold && $hold->supplier_product_id === $availability->offerId && $hold->expires_at->greaterThan($now);
            $hold ??= new StockHold;
            $hold->forceFill(['holder' => $holder, 'product_id' => $product->id, 'supplier_product_id' => $availability->offerId, 'quantity' => $quantity, 'order_id' => null]);
            if (! $keepExpiry) {
                $hold->expires_at = $now->addMinutes(AvailabilityConfig::holdMinutes());
            }
            $hold->save();
        }, 3);
    }

    public function release(?string $holder, int $productId): void
    {
        if ($holder !== null) {
            StockHold::where('holder', $holder)->where('product_id', $productId)->whereNull('order_id')->delete();
        }
    }

    public function releaseAll(?string $holder): void
    {
        if ($holder !== null) {
            StockHold::where('holder', $holder)->whereNull('order_id')->delete();
        }
    }

    /** @return Collection<int, StockHold> active holds keyed by product id */
    public function active(?string $holder, array $productIds, bool $lock = false): Collection
    {
        if ($holder === null || ! $productIds) {
            return collect();
        }
        $query = StockHold::where('holder', $holder)->whereIn('product_id', $productIds)->where('expires_at', '>', PreferredOfferAvailability::now())->orderBy('id');

        return ($lock ? $query->lockForUpdate() : $query)->get()->keyBy('product_id');
    }

    /**
     * Attach the holder's active holds to an order, keeping their expiry (D8). Call inside the
     * checkout transaction after availability was verified under lock.
     */
    public function convert(string $holder, Order $order, array $items): void
    {
        $now = PreferredOfferAvailability::now();
        foreach ($items as $item) {
            $updated = StockHold::where('holder', $holder)->where('product_id', $item['product_id'])->whereNull('order_id')
                ->where('expires_at', '>', $now)->where('quantity', $item['quantity'])
                ->update(['holder' => null, 'order_id' => $order->id, 'updated_at' => $now]);
            if ($updated !== 1) {
                throw new HoldUnavailable('La reserva de algunos productos venció. Revisa el carrito antes de continuar.');
            }
        }
    }

    /** Remove expired cart holds. Order holds are kept as history. */
    public function prune(): int
    {
        return StockHold::whereNull('order_id')->where('expires_at', '<=', PreferredOfferAvailability::now())->delete();
    }
}
```

- [ ] **Step 4: Carrito con reservas**

Reemplazar `app/Services/CartService.php` completo por:

```php
<?php

namespace App\Services;

use App\Availability\Availability;
use App\Availability\AvailabilityState;
use App\Availability\HoldUnavailable;
use App\Contracts\CartStore;
use App\Contracts\ProductAvailability;
use App\Models\Product;
use App\Models\StockHold;
use App\Support\CartHolder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CartService
{
    public const MAX_QUANTITY = 99;

    public const MAX_LINES = 50;

    public function __construct(private CartStore $store, private StockHolds $holds, private ProductAvailability $availability) {}

    public function summary(): array
    {
        $cart = $this->store->read();

        return ['units' => array_sum(array_column($cart['items'], 'quantity')), 'revision' => $cart['revision']];
    }

    public function snapshot(): array
    {
        $cart = $this->store->read();
        $holder = CartHolder::current();
        $products = Product::storefrontVisible()->whereIn('id', array_column($cart['items'], 'product_id'))->with('images')->get()->keyBy('id');
        $availability = $this->availability->forProducts($products->keys()->all(), $holder);
        $holds = $this->holds->active($holder, $products->keys()->all());
        $lines = [];
        $subtotal = 0;
        $blocked = false;
        foreach ($cart['items'] as $id => $item) {
            $product = $products->get($item['product_id']);
            $reason = match (true) {
                ! $product => 'unpublished',
                default => $this->reason($product, $item['quantity'], $cart['currency'], $availability[$product->id] ?? Availability::unknown(), $holds->get($product->id)),
            };
            $available = $reason === null;
            $lineSubtotal = $available ? $product->price_minor * $item['quantity'] : null;
            $subtotal += $lineSubtotal ?? 0;
            $blocked = $blocked || ! $available;
            $image = $product?->images->firstWhere('is_primary', true) ?? $product?->images->first();
            // Explicit public projection. Hidden products do not disclose current metadata.
            $lines[] = [
                'id' => $id, 'quantity' => $item['quantity'], 'available' => $available, 'reason' => $reason,
                'name' => $product?->name ?? 'Producto no disponible', 'slug' => $product?->slug,
                'is_demo' => $product?->is_demo ?? false,
                'image' => $image ? ['url' => route('catalog.image', $image), 'alt' => $image->alt] : null,
                'unit_price_minor' => $available ? $product->price_minor : null,
                'subtotal_minor' => $lineSubtotal, 'currency' => $available ? $cart['currency'] : null,
            ];
        }
        $expiry = $holds->sortBy('expires_at')->first()?->expires_at;

        return [
            'lines' => $lines, 'currency' => $cart['currency'], 'units' => array_sum(array_column($cart['items'], 'quantity')),
            'subtotal_minor' => $subtotal, 'total_minor' => $blocked ? null : $subtotal,
            'has_unavailable' => $blocked, 'revision' => $cart['revision'],
            'max_quantity' => self::MAX_QUANTITY, 'max_lines' => self::MAX_LINES,
            'hold_expires_at' => $expiry?->toISOString(),
        ];
    }

    public function mutate(string $action, array $data, ?string $line = null): bool
    {
        $cart = $this->store->read();
        $fingerprint = hash('sha256', json_encode([$action, $line, $data], JSON_THROW_ON_ERROR));
        if (isset($cart['mutations'][$data['mutation_id']])) {
            if (! hash_equals($cart['mutations'][$data['mutation_id']], $fingerprint)) {
                $this->fail('Esta solicitud ya se utilizó. Actualiza el carrito antes de intentarlo de nuevo.');
            }

            return false;
        }
        if ((int) $data['revision'] !== $cart['revision']) {
            $this->fail('Tu carrito cambió en otra pestaña. Revisa su contenido actualizado e inténtalo de nuevo.');
        }
        $holder = CartHolder::ensure();
        if ($action === 'add') {
            $product = Product::storefrontVisible()->where('slug', $data['product_slug'])->first();
            if (! $product) {
                $this->fail('Este producto ya no está disponible en el catálogo.');
            }
            if ($cart['currency'] && $cart['currency'] !== $product->currency) {
                $this->fail('Tu carrito contiene productos en '.$cart['currency'].'. Vacíalo antes de agregar productos en otra moneda.');
            }
            $existing = collect($cart['items'])->search(fn ($item) => $item['product_id'] === $product->id);
            if ($existing === false && count($cart['items']) >= self::MAX_LINES) {
                $this->fail('Has alcanzado el límite de 50 productos distintos en el carrito.');
            }
            $quantity = (int) $data['quantity'] + ($existing !== false ? $cart['items'][$existing]['quantity'] : 0);
            if ($quantity > self::MAX_QUANTITY) {
                $this->fail('El límite temporal es de 99 unidades por producto. No indica existencias de inventario.');
            }
            $this->hold($holder, $product, $quantity);
            $id = $existing !== false ? $existing : (string) Str::uuid();
            $cart['items'][$id] = ['product_id' => $product->id, 'quantity' => $quantity];
            $cart['currency'] = $product->currency;
        } elseif (in_array($action, ['update', 'remove'], true)) {
            abort_unless(isset($cart['items'][$line]), 404);
            if ($action === 'remove') {
                $this->holds->release($holder, $cart['items'][$line]['product_id']);
                unset($cart['items'][$line]);
            } else {
                $product = Product::storefrontVisible()->whereKey($cart['items'][$line]['product_id'])->first();
                if (! $product || $product->currency !== $cart['currency']) {
                    $this->fail('Este producto cambió y ya no puede actualizarse. Retíralo del carrito.');
                }
                $this->hold($holder, $product, (int) $data['quantity']);
                $cart['items'][$line]['quantity'] = (int) $data['quantity'];
            }
        } elseif ($action === 'clear') {
            $this->holds->releaseAll($holder);
            $cart['items'] = [];
        } else {
            throw new \LogicException('Unknown cart operation.');
        }
        if (! $cart['items']) {
            $cart['currency'] = null;
        }
        $cart['revision']++;
        $cart['mutations'][$data['mutation_id']] = $fingerprint;
        $cart['mutations'] = array_slice($cart['mutations'], -100, null, true);
        $this->store->write($cart);

        return true;
    }

    /** Why a public product line cannot be bought now; null when it can. */
    private function reason(Product $product, int $quantity, ?string $currency, Availability $availability, ?StockHold $hold): ?string
    {
        return match (true) {
            $product->currency !== $currency => 'currency_changed',
            $availability->state !== AvailabilityState::Available || $quantity > $availability->quantity => 'out_of_stock',
            ! $hold || $hold->quantity !== $quantity || $hold->supplier_product_id !== $availability->offerId => 'hold_expired',
            default => null,
        };
    }

    private function hold(string $holder, Product $product, int $quantity): void
    {
        try {
            $this->holds->reserve($holder, $product, $quantity);
        } catch (HoldUnavailable $exception) {
            $this->fail($exception->getMessage());
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['cart' => $message]);
    }
}
```

- [ ] **Step 5: El logout libera las reservas del carrito que se descarta**

En `app/Http/Controllers/AuthController.php` agregar `use App\Services\StockHolds;` y `use App\Support\CartHolder;`; en `logout()`, como primera línea (antes de `Auth::logout();`):

```php
        // The session cart is discarded below, so its local holds must not keep blocking stock.
        app(StockHolds::class)->releaseAll(CartHolder::current());
```

- [ ] **Step 6: Correr el test nuevo y verificar que pasa**

Run: `COMMERCE_AVAILABILITY_TTL_MINUTES=10080 php artisan test --filter=StockHoldTest`
Expected: PASS, 11 tests.

- [ ] **Step 7: Ajustar el fixture de CartTest**

`tests/Feature/CartTest.php:27`: `return Product::factory()->sellable()->create(['status' => 'published', ...$attributes]);` (stock 1000 cubre el tope técnico de 99).

Run: `COMMERCE_AVAILABILITY_TTL_MINUTES=10080 php artisan test --filter='CartTest|StockHoldTest|StorefrontAvailabilityTest'`
Expected: PASS. `CheckoutTest` todavía puede fallar: se ajusta en Task 5.

- [ ] **Step 8: Punto de control (sin commit)**

Run: `git status --short && git diff --stat`

### Task 5: Checkout verifica bajo bloqueo y convierte la reserva

**Files:**
- Modify: `app/Services/CheckoutService.php` (constructor, `quote()`, `confirm()`)
- Modify (solo fixture): `tests/Feature/CheckoutTest.php:32`
- Test: `tests/Feature/CheckoutAvailabilityTest.php`

**Interfaces:**
- Consumes: `ProductAvailability::forProducts($ids, $holder, $lock)`, `StockHolds::active(...)`, `StockHolds::convert(...)`, `CartHolder::current()`, `HoldUnavailable`.
- Produces: el checkout rechaza (clave de error `checkout`) cualquier línea sin disponibilidad vigente, sin reserva activa, con cantidad distinta a la reservada o superior a lo vendible; al confirmar, las reservas quedan con `holder = null`, `order_id` del pedido y el mismo `expires_at`.

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Feature/CheckoutAvailabilityTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Availability\AvailabilityConfig;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockHold;
use App\Models\SupplierProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CheckoutAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['commerce.availability.hold_minutes' => 60]);
        $this->freezeSecond();
    }

    private function prepare(int $stock = 5, int $quantity = 2): Product
    {
        $product = Product::factory()->sellable($stock)->create(['status' => 'published', 'price_minor' => 1000, 'currency' => 'CRC']);
        $this->post('/cart/items', ['mutation_id' => (string) Str::uuid(), 'revision' => session('shopping_cart.revision', 0), 'product_slug' => $product->slug, 'quantity' => $quantity])->assertSessionHasNoErrors();
        $this->get('/checkout')->assertOk();

        return $product;
    }

    private function payload(): array
    {
        return ['token' => session('checkout_review.token'), 'first_name' => 'Ana', 'last_name' => 'Prueba', 'email' => 'guest@example.test', 'phone' => '+50688887777', 'province_code' => '1', 'canton_code' => '101', 'district_code' => '10101', 'exact_address' => 'Dirección ficticia para pruebas, casa azul.', 'additional' => ''];
    }

    private function place(): Order
    {
        $response = $this->from('/checkout')->post('/checkout', $this->payload())->assertSessionHasNoErrors()->assertStatus(303);

        return Order::where('number', basename($response->headers->get('Location')))->firstOrFail();
    }

    private function newVisitor(): void
    {
        $this->app['session.store']->flush();
        $this->app['session.store']->regenerate();
    }

    public function test_confirmation_attaches_the_holds_to_the_order_with_the_same_expiry(): void
    {
        $this->prepare();
        $expiry = StockHold::sole()->expires_at;
        $order = $this->place();
        $hold = StockHold::sole();
        $this->assertNull($hold->holder);
        $this->assertSame($order->id, $hold->order_id);
        $this->assertSame(2, $hold->quantity);
        $this->assertTrue($hold->expires_at->equalTo($expiry));
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
    }

    public function test_order_holds_keep_counting_until_expiry_then_release_stock(): void
    {
        $product = $this->prepare(2, 2);
        $order = $this->place();
        $this->newVisitor();
        $this->get('/catalog/'.$product->slug)->assertInertia(fn (Assert $page) => $page->where('availability.'.$product->slug, ['state' => 'unavailable', 'quantity' => 0]));
        $this->travel(60)->minutes();
        $this->get('/catalog/'.$product->slug)->assertInertia(fn (Assert $page) => $page->where('availability.'.$product->slug, ['state' => 'available', 'quantity' => 2]));
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_expired_hold_blocks_review_and_confirmation(): void
    {
        $this->prepare();
        $payload = $this->payload();
        $this->travel(60)->minutes();
        $this->get('/checkout')->assertRedirect('/cart')->assertSessionHasErrors('checkout');
        $this->post('/checkout', $payload)->assertSessionHasErrors('checkout');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_stale_availability_blocks_confirmation(): void
    {
        $product = $this->prepare();
        SupplierProduct::where('product_id', $product->id)->update(['observed_at' => now()->subMinutes(AvailabilityConfig::ttlMinutes() + 1)]);
        $this->post('/checkout', $this->payload())->assertSessionHasErrors('checkout');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_stock_lowered_below_the_held_quantity_blocks_confirmation(): void
    {
        $product = $this->prepare(5, 2);
        SupplierProduct::where('product_id', $product->id)->update(['stock' => 1]);
        $this->post('/checkout', $this->payload())->assertSessionHasErrors('checkout');
        $this->assertDatabaseCount('orders', 0);
        $this->assertNotNull(StockHold::sole()->holder);
    }

    public function test_retried_confirmation_does_not_attach_holds_twice(): void
    {
        $this->prepare();
        $payload = $this->payload();
        $order = $this->place();
        $this->from('/checkout')->post('/checkout', $payload)->assertStatus(303);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('stock_holds', 1);
        $this->assertSame($order->id, StockHold::sole()->order_id);
    }
}
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `COMMERCE_AVAILABILITY_TTL_MINUTES=10080 php artisan test --filter=CheckoutAvailabilityTest`
Expected: FAIL (la reserva no se convierte; vencida/stale no bloquean).

- [ ] **Step 3: Verificación y conversión en `CheckoutService`**

Agregar imports: `use App\Availability\Availability;`, `use App\Availability\AvailabilityState;`, `use App\Availability\HoldUnavailable;`, `use App\Contracts\ProductAvailability;`, `use App\Support\CartHolder;`.

Reemplazar el constructor por:

```php
    public function __construct(private CartStore $store, private ProductAvailability $availability, private StockHolds $holds) {}
```

En `quote()`, inmediatamente antes de `$items = [];`, insertar:

```php
        $holder = CartHolder::current();
        // Offers and holds are locked after products and taxonomy; hold writers only lock offers, then holds.
        $availability = $this->availability->forProducts($ids, $holder, $lock);
        $holds = $this->holds->active($holder, $ids, $lock);
```

Dentro del `foreach` de `quote()`, entre el `if (...) { $this->fail('Hay productos que ya no se pueden confirmar...'); }` existente y `$items[] = [...]`, insertar:

```php
            $current = $availability[$product->id] ?? Availability::unknown();
            $hold = $holds->get($product->id);
            if ($current->state !== AvailabilityState::Available || $item['quantity'] > $current->quantity || ! $hold || $hold->quantity !== $item['quantity'] || $hold->supplier_product_id !== $current->offerId) {
                $this->fail('La disponibilidad o la reserva de algunos productos cambió. Revisa el carrito antes de continuar.');
            }
```

En `confirm()`, inmediatamente después de `$order->items()->createMany($quote['items']);`, insertar:

```php
            try {
                $this->holds->convert(CartHolder::current() ?? '', $order, $quote['items']);
            } catch (HoldUnavailable $exception) {
                $this->fail($exception->getMessage());
            }
```

- [ ] **Step 4: Correr el test nuevo y verificar que pasa**

Run: `COMMERCE_AVAILABILITY_TTL_MINUTES=10080 php artisan test --filter=CheckoutAvailabilityTest`
Expected: PASS, 6 tests.

- [ ] **Step 5: Ajustar el fixture de CheckoutTest**

`tests/Feature/CheckoutTest.php:32`: `$product = Product::factory()->sellable()->create(['status' => 'published', 'price_minor' => 123456, 'currency' => 'CRC', ...$attributes]);`

- [ ] **Step 6: Suite completa**

Run: `COMMERCE_AVAILABILITY_TTL_MINUTES=10080 php artisan test && php vendor/bin/pint --test`
Expected: 152 tests, 0 failures; Pint passed. `CheckoutTest::test_transaction_failure_rolls_back_order_and_preserves_cart` sigue pasando porque la conversión ocurre dentro de la misma transacción.

- [ ] **Step 7: Punto de control del Lote 3 (sin commit)**

Run: `git status --short && git diff --stat -- tests/`
Expected: en tests existentes solo líneas de fixture. Detenerse para revisión.

### Task 6: Limpieza programada de reservas vencidas

**Files:**
- Create: `app/Console/Commands/PruneStockHolds.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/PruneStockHoldsTest.php`

**Interfaces:**
- Consumes: `StockHolds::prune(): int` (Task 4).
- Produces: comando `holds:prune`, programado cada hora. La corrección no depende de él: una reserva vencida ya no cuenta.

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Feature/PruneStockHoldsTest.php`:

```php
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
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `COMMERCE_AVAILABILITY_TTL_MINUTES=10080 php artisan test --filter=PruneStockHoldsTest`
Expected: FAIL (`Command "holds:prune" is not defined`).

- [ ] **Step 3: Comando y programación**

Crear `app/Console/Commands/PruneStockHolds.php`:

```php
<?php

namespace App\Console\Commands;

use App\Services\StockHolds;
use Illuminate\Console\Command;

class PruneStockHolds extends Command
{
    protected $signature = 'holds:prune';

    protected $description = 'Delete expired local cart holds. Expired holds already stopped counting; this only cleans rows.';

    public function handle(StockHolds $holds): int
    {
        $this->info('Expired cart holds removed: '.$holds->prune());

        return self::SUCCESS;
    }
}
```

Al final de `routes/console.php` agregar (y `use Illuminate\Support\Facades\Schedule;` junto a los imports):

```php
// V1-B: expired holds already stopped counting; this only removes old rows.
Schedule::command('holds:prune')->hourly();
```

- [ ] **Step 4: Correr el test y verificar que pasa**

Run: `COMMERCE_AVAILABILITY_TTL_MINUTES=10080 php artisan test --filter=PruneStockHoldsTest`
Expected: PASS, 2 tests.

- [ ] **Step 5: Punto de control (sin commit)**

Run: `git status --short`

### Task 7: UI de stock y reserva (diseño ui-ux-pro-max)

**Decisiones de diseño (ui-ux-pro-max, verificadas):**
- Estado siempre en **texto + ícono SVG**, nunca solo color (`color-not-only`). Cantidad con `tabular-nums`.
- Tokens nuevos, contraste WCAG AA medido: stock `#17613a` sobre `#edf7f0` = 6.83:1; agotado `#3b4654` sobre `#eef1f5` = 8.46:1; reserva vencida `#7a4300` sobre `#fff5e6` = 7.40:1. Bordes decorativos (el significado va en el texto).
- Reserva: **hora fija de vencimiento**, no cuenta regresiva viva (evita anuncios repetidos a lectores de pantalla). Aviso informativo en azul primario (5.00:1 sobre `surface-blue`).
- Agotado: el formulario se reemplaza por un mensaje (no se muestran controles que no funcionan). `max` del input = `min(99, cantidad)`. Botón "Reservar de nuevo" ≥ 44 px de alto.
- Copy con tuteo, coherente con el sitio.

**Files:**
- Modify: `resources/css/storefront-tokens.css` (dentro de `.storefront { … }`), `resources/css/storefront.css` (al final), `resources/css/cart.css` (al final)
- Modify: `resources/js/types/storefront.ts`, `resources/js/types/cart.ts`
- Create: `resources/js/components/storefront/StockStatus.vue`
- Modify: `resources/js/components/storefront/ProductCard.vue`, `ProductSection.vue`, `AddToCart.vue`, `CartLine.vue`
- Modify: `resources/js/pages/Home.vue`, `resources/js/pages/catalog/Index.vue`, `resources/js/pages/catalog/Show.vue`, `resources/js/pages/cart/Index.vue`

**Interfaces:**
- Consumes: prop `availability` (Task 3); `cart.hold_expires_at` y `reason` `'out_of_stock' | 'hold_expired'` (Task 4).
- Produces: `PublicAvailability`, `AvailabilityMap` (TS); componente `<StockStatus :availability size="card|detail"/>`.

- [ ] **Step 1: Tokens y estilos**

En `resources/css/storefront-tokens.css`, antes de la llave de cierre del bloque `.storefront {`:

```css
    --color-stock-text: #17613a;
    --color-stock-background: #edf7f0;
    --color-stock-border: #6fa585;
    --color-soldout-text: #3b4654;
    --color-soldout-background: #eef1f5;
    --color-soldout-border: #8793a3;
    --color-hold-text: #7a4300;
    --color-hold-background: #fff5e6;
    --color-hold-border: #c98a2b;
```

Al final de `resources/css/storefront.css`, en una línea nueva:

```css
.st-stock{display:inline-flex;align-items:center;gap:6px;margin-top:10px!important;padding:3px 8px;border:1px solid;border-radius:999px;font-size:11px;font-weight:600;line-height:1.5;font-variant-numeric:tabular-nums}.st-stock.is-available{color:var(--color-stock-text);background:var(--color-stock-background);border-color:var(--color-stock-border)}.st-stock.is-sold-out{color:var(--color-soldout-text);background:var(--color-soldout-background);border-color:var(--color-soldout-border)}.st-stock.is-detail{font-size:13px;padding:5px 12px;margin-top:16px!important}.st-stock-icon{flex-shrink:0}
```

Al final de `resources/css/cart.css`, en una línea nueva:

```css
.st-hold-notice{display:flex;gap:10px;align-items:flex-start;margin-top:16px!important;padding:12px 14px;border:1px solid var(--color-border);border-left:3px solid var(--color-primary);background:var(--color-surface-blue);border-radius:5px;font-size:12px;line-height:1.7;color:var(--color-text)}.st-hold-notice svg{flex-shrink:0;margin-top:3px;color:var(--color-primary)}.st-hold-notice strong{font-variant-numeric:tabular-nums}.st-cart-unavailable.is-hold{color:var(--color-hold-text);background:var(--color-hold-background);border-left-color:var(--color-hold-border)}.st-cart-rehold{margin-top:10px;min-height:44px;padding:0 16px;border:1px solid var(--color-primary);border-radius:5px;background:var(--color-background);color:var(--color-primary);font-size:12px;font-weight:650}.st-cart-rehold:hover{background:var(--color-surface-blue)}.st-cart-rehold:disabled{opacity:.5;cursor:not-allowed}
```

- [ ] **Step 2: Tipos**

Al final de `resources/js/types/storefront.ts`:

```ts
export interface PublicAvailability { state: 'available' | 'unavailable'; quantity: number }
export type AvailabilityMap = Record<string, PublicAvailability>;
```

En `resources/js/types/cart.ts`: en `CartLine` cambiar `reason: 'unpublished' | 'currency_changed' | null;` por `reason: 'unpublished' | 'currency_changed' | 'out_of_stock' | 'hold_expired' | null;`, y en `Cart` agregar `hold_expires_at: string | null;` después de `max_lines: number;`.

- [ ] **Step 3: Componente de estado de stock**

Crear `resources/js/components/storefront/StockStatus.vue`:

```vue
<script setup lang="ts">
import type { PublicAvailability } from '../../types/storefront';
defineProps<{ availability?: PublicAvailability; size?: 'card' | 'detail' }>();
</script>
<template>
    <p v-if="availability" class="st-stock" :class="[availability.state === 'available' ? 'is-available' : 'is-sold-out', size === 'detail' ? 'is-detail' : '']">
        <svg class="st-stock-icon" viewBox="0 0 16 16" width="14" height="14" aria-hidden="true" focusable="false">
            <path v-if="availability.state === 'available'" d="M3.5 8.5l3 3 6-7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path v-else d="M4 8h8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span v-if="availability.state === 'available'">{{ availability.quantity === 1 ? 'Queda 1 unidad' : `Quedan ${availability.quantity} unidades` }}</span>
        <span v-else>Agotado</span>
    </p>
</template>
```

- [ ] **Step 4: Tarjeta, sección y páginas de catálogo**

`resources/js/components/storefront/ProductCard.vue`: importar `StockStatus from './StockStatus.vue'` y `type PublicAvailability`; cambiar las props a `defineProps<{ product: PublicProduct; availability?: PublicAvailability }>()`; insertar `<StockStatus :availability="availability"/>` inmediatamente después del `<div class="st-price">…</div>`.

`resources/js/components/storefront/ProductSection.vue` completo:

```vue
<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import ProductCard from './ProductCard.vue';
import type { AvailabilityMap, PublicProduct } from '../../types/storefront';
defineProps<{ title: string; eyebrow: string; products: PublicProduct[]; href?: string; availability?: AvailabilityMap }>();
</script>
<template><section v-if="products.length" class="st-section"><div class="st-section-heading"><div><p class="st-overline">{{ eyebrow }}</p><h2>{{ title }}</h2></div><Link v-if="href" :href="href" class="st-text-link">Ver todos <span aria-hidden="true">↗</span></Link></div><div class="st-product-grid"><ProductCard v-for="product in products" :key="product.slug" :product="product" :availability="availability?.[product.slug]" /></div></section></template>
```

`resources/js/pages/Home.vue`: agregar `type AvailabilityMap` al import de `../types/storefront`; agregar `availability: AvailabilityMap;` a `defineProps`; agregar `:availability="availability"` a los tres `<ProductSection …/>`; en el tercer `<article>` de `.st-benefits` reemplazar el párrafo por `<p>Mostramos la cantidad disponible de cada producto. Si no tenemos un dato vigente, el producto no aparece en el catálogo.</p>`.

`resources/js/pages/catalog/Index.vue`: agregar `type AvailabilityMap` al import; agregar `availability: AvailabilityMap;` a `defineProps`; cambiar `<ProductCard v-for="product in products.data" :key="product.slug" :product="product"/>` por `<ProductCard v-for="product in products.data" :key="product.slug" :product="product" :availability="availability[product.slug]"/>`; reemplazar el texto `La publicación no indica disponibilidad de stock.` por `Solo mostramos productos con disponibilidad vigente.`

`resources/js/pages/catalog/Show.vue`: importar `StockStatus from '../../components/storefront/StockStatus.vue'` y `type AvailabilityMap`; cambiar props a `defineProps<{ product: PublicProduct; related: PublicProduct[]; availability: AvailabilityMap; seo: Seo }>()`; insertar `<StockStatus :availability="availability[product.slug]" size="detail"/>` inmediatamente después del `<div class="st-detail-price">…</div>`; cambiar `<AddToCart :slug="product.slug"/>` por `<AddToCart :slug="product.slug" :availability="availability[product.slug]"/>`; agregar `:availability="availability"` al `<ProductSection …/>` de relacionados.

- [ ] **Step 5: Agregar al carrito**

Reemplazar `resources/js/components/storefront/AddToCart.vue` completo por:

```vue
<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useCartActions } from '../../composables/useCartActions';
import type { PublicAvailability } from '../../types/storefront';
const props = defineProps<{ slug: string; availability?: PublicAvailability }>();
const quantity = ref(1);
const { page, busy, errors, change } = useCartActions();
const soldOut = computed(() => props.availability?.state !== 'available');
const max = computed(() => Math.min(99, props.availability?.quantity ?? 0));
watch(() => props.slug, () => { quantity.value = 1; errors.value = {}; });
</script>
<template>
    <section class="st-add-cart" aria-labelledby="add-cart-title">
        <h2 id="add-cart-title">{{ soldOut ? 'Producto agotado.' : 'Guárdalo en tu carrito.' }}</h2>
        <p v-if="soldOut">No hay unidades disponibles en este momento. Vuelve a consultar más tarde.</p>
        <p v-else>No necesitas una cuenta. Al agregarlo, apartamos las unidades por tiempo limitado mientras completas tu pedido.</p>
        <div v-if="Object.keys(errors).length" id="cart-errors" class="st-form-error" role="alert" tabindex="-1"><p v-for="(message, key) in errors" :key="key">{{ message }}</p><Link href="/cart">Revisar mi carrito →</Link></div>
        <form v-if="!soldOut" class="st-add-cart-form" @submit.prevent="change('post', '/cart/items', { product_slug: slug, quantity })">
            <label for="add-quantity">Cantidad<input id="add-quantity" v-model.number="quantity" type="number" inputmode="numeric" min="1" :max="max" step="1" required :disabled="busy" aria-describedby="cart-quantity-note"></label>
            <button class="st-button" type="submit" :disabled="busy">{{ busy ? 'Agregando…' : 'Agregar al carrito' }} <span aria-hidden="true">+</span></button>
        </form>
        <p v-if="!soldOut" id="cart-quantity-note" class="st-cart-help">Puedes agregar hasta {{ max }} {{ max === 1 ? 'unidad' : 'unidades' }}.</p>
        <div v-if="page.props.cartStatus" class="st-cart-feedback" role="status">{{ page.props.cartStatus }} <Link href="/cart">Ver carrito →</Link></div>
        <p class="st-cart-help">La reserva es interna de esta tienda y no genera ningún cobro. Puedes crear un pedido pendiente de pago.</p>
    </section>
</template>
```

- [ ] **Step 6: Líneas del carrito**

Reemplazar `resources/js/components/storefront/CartLine.vue` completo por:

```vue
<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import { money } from '../../types/catalog';
import type { CartLine } from '../../types/cart';
const props = defineProps<{ line: CartLine; busy: boolean; max: number }>();
const emit = defineEmits<{ update: [quantity: number]; remove: [] }>();
const quantity = ref(props.line.quantity);
watch(() => props.line.quantity, value => { quantity.value = value; });
const editable = computed(() => props.line.available || props.line.reason === 'out_of_stock' || props.line.reason === 'hold_expired');
const message = computed(() => ({
    unpublished: 'Este producto ya no está disponible en el catálogo. Retíralo para continuar.',
    currency_changed: 'La moneda de este producto cambió. Retíralo para continuar.',
    out_of_stock: 'La cantidad disponible cambió. Ajusta la cantidad o retira el producto.',
    hold_expired: 'Tu reserva de este producto venció. Resérvalo de nuevo para continuar.',
}[props.line.reason ?? 'unpublished']));
</script>
<template>
    <article class="st-cart-line" :class="{ 'is-unavailable': !line.available }">
        <div class="st-cart-image"><img v-if="line.image" :src="line.image.url" :alt="line.image.alt" width="160" height="120" loading="lazy"><span v-else aria-hidden="true">—</span></div>
        <div class="st-cart-line-content">
            <p class="st-overline">{{ line.is_demo ? 'DEMOSTRACIÓN' : 'TU SELECCIÓN' }}</p>
            <h2><Link v-if="line.slug" :href="`/catalog/${line.slug}`">{{ line.name }}</Link><span v-else>{{ line.name }}</span></h2>
            <p v-if="line.available && line.currency && line.unit_price_minor !== null" class="st-cart-unit">{{ money(line.unit_price_minor, line.currency) }} <span>por unidad</span></p>
            <p v-else class="st-cart-unavailable" :class="{ 'is-hold': line.reason === 'hold_expired' }">{{ message }} No se incluye en el subtotal.</p>
            <button v-if="line.reason === 'hold_expired'" type="button" class="st-cart-rehold" :disabled="busy" @click="emit('update', line.quantity)">Reservar de nuevo</button>
            <form v-if="editable" class="st-cart-quantity-form" @submit.prevent="emit('update', quantity)">
                <label :for="`quantity-${line.id}`">Cantidad</label>
                <div class="st-quantity-stepper"><button type="button" :disabled="busy || line.quantity <= 1" :aria-label="`Disminuir cantidad de ${line.name}`" @click="emit('update', line.quantity - 1)">−</button><input :id="`quantity-${line.id}`" v-model.number="quantity" type="number" inputmode="numeric" min="1" :max="max" step="1" required :disabled="busy"><button type="button" :disabled="busy || line.quantity >= max" :aria-label="`Aumentar cantidad de ${line.name}`" @click="emit('update', line.quantity + 1)">+</button></div>
                <button class="st-cart-update" :disabled="busy" type="submit">Actualizar</button>
            </form>
            <p v-else class="st-cart-unit">Cantidad guardada: {{ line.quantity }}</p>
            <button class="st-cart-remove" type="button" :disabled="busy" :aria-label="`Eliminar ${line.name}`" @click="emit('remove')">Eliminar</button>
        </div>
        <div class="st-cart-line-total"><span>Subtotal</span><strong v-if="line.available && line.subtotal_minor !== null && line.currency">{{ money(line.subtotal_minor, line.currency) }}</strong><strong v-else>—</strong></div>
    </article>
</template>
```

- [ ] **Step 7: Aviso de reserva en el carrito**

En `resources/js/pages/cart/Index.vue`:
- Cambiar `import { nextTick } from 'vue';` por `import { computed, nextTick } from 'vue';` y `defineProps<{ cart: Cart; seo: Seo }>();` por `const props = defineProps<{ cart: Cart; seo: Seo }>();`.
- Después de `const focusCart = …;` agregar:

```ts
const holdUntil = computed(() => props.cart.hold_expires_at ? new Intl.DateTimeFormat('es-CR', { hour: 'numeric', minute: '2-digit' }).format(new Date(props.cart.hold_expires_at)) : null);
```

- En el `<aside class="st-cart-summary">`, inmediatamente antes de `<Link v-if="!cart.has_unavailable" href="/checkout" class="st-button">`, insertar:

```vue
<p v-if="holdUntil" class="st-hold-notice" role="status"><svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false"><circle cx="8" cy="8" r="6.5" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M8 4.5V8l2.5 1.5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg><span>Reservamos tus productos hasta las <strong>{{ holdUntil }}</strong>. Si no confirmas el pedido antes, las unidades se liberan para otros clientes.</span></p>
```

- Reemplazar `Los precios se actualizan al consultar tu carrito. Agregar un producto no reserva stock ni confirma una compra.` por `Los precios se actualizan al consultar tu carrito. La reserva es interna de esta tienda: no confirma la compra ni genera cobros.`

- [ ] **Step 8: TypeScript y build**

Run: `npm run check`
Expected: `vue-tsc --noEmit` sin errores y `✓ built`.

- [ ] **Step 9: Revisión ui-ux-pro-max (pre-entrega)**

Recorrer el checklist §1–§3 de ui-ux-pro-max sobre los componentes tocados: estados comunicados con texto (no solo color), contraste de tokens (valores arriba), foco visible heredado de `.storefront :focus-visible`, botones ≥ 44 px, sin controles inertes cuando está agotado, `aria-describedby` del input apunta a `#cart-quantity-note` solo cuando existe. La QA visual en navegador queda pendiente hasta que MySQL local esté disponible (la app no sirve páginas sin base de datos); documentarlo en el reporte.

- [ ] **Step 10: Punto de control (sin commit)**

Run: `COMMERCE_AVAILABILITY_TTL_MINUTES=10080 php artisan test && git status --short`
Expected: 154 tests, 0 failures.

### Task 8: Reporte V1-B y verificación final

**Files:**
- Create: `docs/V1-B-REPORT.md`

**Interfaces:**
- Consumes: resultados reales de los comandos de verificación.

- [ ] **Step 1: Verificación completa**

Run, en este orden, y guardar la salida:

```bash
COMMERCE_AVAILABILITY_TTL_MINUTES=10080 php artisan test
npm run check
composer validate --strict
php vendor/bin/pint --test
git diff --check
git status --short
```

Expected: 154 tests / 0 failures; vue-tsc y build OK; `composer.json is valid`; Pint passed; `diff --check` sin errores (solo avisos LF/CRLF).

- [ ] **Step 2: Escribir el reporte**

Crear `docs/V1-B-REPORT.md` con este contenido. En la tabla "Verificación final", reemplazar cada valor esperado por el valor exacto impreso en el Step 1 si difiere:

```markdown
# V1-B · Disponibilidad comercial y reserva local

Fecha: 2026-09-19. Estado: implementada para revisión; sin commit ni push. Documentos rectores: [V1-B-SCOPE.md](V1-B-SCOPE.md), [ADR-005](adr/005-availability.md). Plan ejecutado: [plan V1-B](superpowers/plans/2026-09-19-v1b-disponibilidad-reservas.md).

## Decisiones aplicadas

- Frescura: `COMMERCE_AVAILABILITY_TTL_MINUTES`, sin valor por defecto en código; aplica igual a datos manuales y de proveedor, sin excepciones (incluido el producto de prueba). Fresco mientras `observed_at > ahora − TTL`.
- TTL ausente o inválido: la aplicación falla al arrancar (`AppServiceProvider::boot`). Única excepción: `artisan package:discover`, que Composer ejecuta antes de que exista `.env` (clon limpio y CI).
- Estados: disponible (oferta preferida activa, proveedor activo, dato vigente, stock > 0), no disponible (stock 0 o `unavailable`, visible como agotado), vencido y desconocido (ocultos como un producto no publicado). `available` sin cantidad cuenta como desconocido. Observaciones con fecha futura cuentan como desconocido.
- Cantidad pública exacta: stock menos reservas activas de otros carritos. La disponibilidad pública viaja como prop `availability` (`state`, `quantity`); no se exponen proveedor, costo, fecha ni identificadores de oferta.
- Reserva local de `COMMERCE_CART_HOLD_MINUTES` (60 por defecto, V1-B-SCOPE §5): vencimiento fijo desde su creación; cambiar cantidad no lo extiende. Al confirmar, la reserva pasa al pedido `pending_payment` con el mismo vencimiento y libera la unidad al vencer. El logout libera las reservas del carrito descartado. Nunca se contacta a un proveedor.
- Concurrencia: toda escritura de reservas bloquea primero la fila de la oferta; el checkout bloquea productos, taxonomía, ofertas y reservas en ese orden.

## Cambios

- Migración aditiva `2026_09_19_000001_create_stock_holds_table` (tabla `stock_holds`). Ninguna tabla o fila de Phase 2C se modifica.
- Contratos y dominio: `AvailabilityState`, `Availability`, `ProductAvailability` (implementado por `PreferredOfferAvailability`, reglas PHP y SQL equivalentes), `AvailabilityConfig`, `StockHolds`, `CartHolder`, `StockHold`.
- Integración: `Product::storefrontVisible`, catálogo público, imágenes públicas, carrito (reservar, ajustar, liberar), checkout (verificación bajo bloqueo y conversión), logout.
- Comando `holds:prune`, programado cada hora (solo limpieza; una reserva vencida ya no cuenta).
- UI: indicador "Quedan N unidades"/"Agotado" con texto e ícono, formulario deshabilitado al agotarse, máximo `min(99, N)`, aviso de reserva con hora de vencimiento, motivos de línea "cantidad cambió" y "reserva vencida" con acción "Reservar de nuevo". Tokens de color nuevos con contraste AA medido.
- Configuración: `phpunit.xml` y `.env.example` definen un TTL largo para local/testing; `ENVIRONMENTS-RELEASE.md` documenta la variable por entorno.

## Tests

Nuevos: `AvailabilityConfigTest` (5), `AvailabilityResolutionTest` (7), `StorefrontAvailabilityTest` (7), `StockHoldTest` (11), `CheckoutAvailabilityTest` (6), `PruneStockHoldsTest` (2).

Fixtures ajustados, sin tocar assertions (los productos que deben seguir siendo públicos reciben una oferta vigente con `ProductFactory::sellable()`):

- `tests/Feature/StorefrontTest.php`: helper `product()` y la creación de 14 productos de paginación.
- `tests/Feature/CatalogTest.php`: productos de `test_drafts_archived_products_and_inactive_ancestors_are_invisible_publicly` y `test_image_upload_validation_and_private_visibility`.
- `tests/Feature/CommercialCatalogTest.php`: producto `$real` de `test_archiving_demo_is_non_destructive_and_preserves_real_products`.
- `tests/Feature/CartTest.php`: helper `product()`.
- `tests/Feature/CheckoutTest.php`: helper `prepare()`.

`CommercialTaxonomyTest` no requirió cambios.

## Verificación final

| Verificación | Resultado |
| --- | --- |
| `php artisan test` | 154 passed / 0 failures |
| `npm run check` | PASS: vue-tsc y build |
| `composer validate --strict` | PASS |
| `php vendor/bin/pint --test` | PASS |
| `git diff --check` | PASS |

## Pendientes

- **Concurrencia real con MySQL: pendiente para V1-I.** SQLite en memoria no bloquea filas; la suite cubre estados y transiciones, no la competencia real entre procesos. No se agregó MySQL al CI (decisión del propietario).
- `.env` local: agregar `COMMERCE_AVAILABILITY_TTL_MINUTES` (ventana larga) antes de usar artisan o servir la app localmente; sin ella la aplicación no arranca, por diseño.
- Valor de producción del TTL: definir según la cadencia de polling real de cada proveedor.
- QA visual en navegador: pendiente hasta que MySQL local esté disponible.
- `holds:prune` requiere el scheduler (`schedule:run`) en el entorno donde se despliegue.
- Fuera de alcance (V1-B-SCOPE §6): revisión manual de ofertas vencidas/desconocidas, selección entre múltiples ofertas, capacidades por proveedor, logística (V1-C), pagos y pagos tardíos (V1-E).

**No se implementó** integración con Eurocomp/Dataformas/CQ, logística, pagos ni V1-C en adelante. Sin commit ni push. Detenerse aquí para revisión del propietario.
```

- [ ] **Step 3: Punto de control final del Lote 4 (sin commit)**

Run: `git status --short && git diff --stat`
Expected: todos los archivos listados en el mapa de archivos más el reporte. Presentar al propietario: resultado de tests, `git status`/diff resumido y decisiones tomadas sobre la marcha.

---

## Self-review

- **Cobertura del alcance:** §1 TTL configurable → Task 1–2; §2 estados → Task 2–3; §3 caída de proveedor → `test_supplier_outage_empties_its_category_without_failing`; §4 cantidad exacta → Task 3 y 7; §5 reserva 1 h, liberación automática y segura → Task 4–6; §6 fuera de alcance → sin tareas, registrado en el reporte. D1–D11 y las decisiones nuevas (TTL igual para manual/proveedor, fallo al arrancar, TTL distinto por entorno) → Global Constraints y tests.
- **Sin placeholders:** cada paso de código trae el código; los únicos valores a completar son salidas reales de comandos en el reporte.
- **Consistencia de tipos:** `forProducts(array, ?string, bool): array<int, Availability>`, `Availability::unknown()/toPublic()`, `StockHolds::reserve/release/releaseAll/active/convert/prune`, `CartHolder::current()/ensure()`, `reason` `'out_of_stock' | 'hold_expired'`, `hold_expires_at` y `AvailabilityMap` se usan con los mismos nombres en todas las tareas.

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

## Decisiones tomadas durante la implementación

- **Valor local/testing del TTL:** `10080` minutos (7 días) en `.env.example` y `phpunit.xml`. El CI copia `.env.example` y, con fallo al arrancar, necesita un valor; el código sigue sin valor por defecto. El propietario agregó la variable a su `.env` local.
- **Excepción de `package:discover`:** Composer la ejecuta en `composer install` antes de que exista `.env` (clon limpio y CI); cualquier otro comando valida el TTL.
- **`CartHolder` adelantado a la Tarea 3:** el catálogo lo necesita para no descontar al dueño de una reserva sus propias unidades.
- **Portada y relacionados:** se consultan una vez y se reutilizan para tarjetas y disponibilidad; el tope de 4 productos por sección no cambia.
- **Disponibilidad pública fuera de `PublicProductResource`:** va como prop `availability` para no alterar la lista de claves que protege `StorefrontTest`; los estados de línea del carrito usan valores nuevos de `reason` por el mismo motivo.
- **Copy con tuteo** en la UI nueva, coherente con el resto del storefront.
- **Observación de test:** `test_order_holds_keep_counting_until_expiry_then_release_stock` pasaba antes de implementar la conversión (la reserva sin convertir también bloqueaba). Prueba el efecto visible de D8; la conversión en sí la prueba `test_confirmation_attaches_the_holds_to_the_order_with_the_same_expiry`.
- **Finales de línea:** `resources/js/pages/cart/Index.vue` tenía CRLF en la copia de trabajo; se normalizó a LF (`.gitattributes` ya exige `eol=lf`). Sin cambio de contenido.

## Verificación final

| Verificación | Resultado |
| --- | --- |
| `php artisan test` | **154 passed (3043 assertions) / 0 failures** (116 originales + 38 nuevos) |
| `npm run check` | PASS: vue-tsc y build, 651 módulos |
| `composer validate --strict` | PASS |
| `php vendor/bin/pint --test` | PASS |
| `git diff --check` | PASS (sin avisos tras normalizar a LF `resources/js/pages/cart/Index.vue`) |

## Pendientes

- **Concurrencia real con MySQL: pendiente para V1-I.** SQLite en memoria no bloquea filas; la suite cubre estados y transiciones, no la competencia real entre procesos. No se agregó MySQL al CI (decisión del propietario).
- `.env` local: agregar `COMMERCE_AVAILABILITY_TTL_MINUTES` (ventana larga) antes de usar artisan o servir la app localmente; sin ella la aplicación no arranca, por diseño.
- Valor de producción del TTL: definir según la cadencia de polling real de cada proveedor.
- QA visual en navegador: pendiente hasta que MySQL local esté disponible.
- `holds:prune` requiere el scheduler (`schedule:run`) en el entorno donde se despliegue. La lista completa de lo que falta configurar en el servidor real está en [ENVIRONMENTS-RELEASE.md](ENVIRONMENTS-RELEASE.md), sección «Pendientes de configuración en el servidor real».
- Fuera de alcance (V1-B-SCOPE §6): revisión manual de ofertas vencidas/desconocidas, selección entre múltiples ofertas, capacidades por proveedor, logística (V1-C), pagos y pagos tardíos (V1-E).

**No se implementó** integración con Eurocomp/Dataformas/CQ, logística, pagos ni V1-C en adelante. Sin commit ni push. Detenerse aquí para revisión del propietario.

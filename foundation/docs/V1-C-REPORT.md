# V1-C · Cotización de entrega y total final — Reporte de fase

Plan ejecutado: [2026-09-20-v1c-cotizacion-entrega.md](superpowers/plans/2026-09-20-v1c-cotizacion-entrega.md). Decisiones de negocio confirmadas por el propietario el 2026-09-19. Contratos afectados: [ADR-007](adr/007-delivery.md) y [ADR-008](adr/008-final-total.md).

## 1. Qué se implementó

El servidor calcula el envío, lo muestra antes de confirmar y lo congela en el pedido.

| Pieza | Responsabilidad |
| --- | --- |
| `delivery_rate_sets`, `delivery_rates`, `delivery_zone_cantons` | Tarifas versionadas y la zona de cada uno de los 84 cantones |
| `App\Delivery\DeliveryZone` | Las tres zonas, con etiqueta pública |
| `App\Delivery\DeliveryQuoter` | Cotización autoritativa: zona, monto, umbral y versión de tarifa |
| `App\Delivery\DeliveryQuote` | Valor inmutable; `toPublic()` es lo único que ve el navegador |
| `DeliveryZonesSeeder` | Siembra idempotente de zonas y tarifas aprobadas |
| `CheckoutService::quote()` | Acepta un cantón, suma el envío al total y bloquea monedas distintas de CRC |
| `orders.shipping_minor`, `shipping_zone`, `delivery_rate_set_id` | Instantánea del envío en el pedido |
| `OrderSummary.vue` | Desglose Productos / Envío / Total, en checkout, confirmación y administración |

El comprador elige su cantón y la revisión se vuelve a pedir al servidor con ese destino; el botón de confirmar permanece deshabilitado mientras no haya un envío cotizado.

## 2. Tarifas vigentes

Versión `v1-2026-09`, vigente desde el 2026-09-19. Importes en colones; el umbral se mide sobre el **subtotal de productos**, antes del envío.

| Zona | Tarifa plana | Envío gratis desde |
| --- | --- | --- |
| Gran Área Metropolitana | ₡3.500 (`350000`) | ₡90.000 (`9000000`) |
| Guanacaste norte | ₡0 — siempre gratis | — |
| Resto del país | ₡4.500 (`450000`) | ₡110.000 (`11000000`) |

Ninguno de estos importes está escrito en `app/`: viven en la base de datos y el cotizador los lee. Verificado con `grep -rn "350000\|450000\|9000000\|11000000" app/`, que no devuelve coincidencias.

## 3. Zonas sembradas

**Gran Área Metropolitana (31 cantones).** San José, Escazú, Desamparados, Aserrí, Mora, Goicoechea, Santa Ana, Alajuelita, Vázquez de Coronado, Tibás, Moravia, Montes de Oca, Curridabat, Alajuela, Atenas, Poás, Cartago, Paraíso, La Unión, Alvarado, Oreamuno, El Guarco, Heredia, Barva, Santo Domingo, Santa Bárbara, San Rafael, San Isidro, Belén, Flores, San Pablo.

**Guanacaste norte (4 cantones).** Liberia, Bagaces, Cañas, La Cruz.

**Resto del país (49 cantones).** Todos los demás del catálogo oficial, incluidos los de Guanacaste que no figuran arriba.

31 + 4 + 49 = 84. `DeliveryZonesSeederTest` recorre `cr-territories-2026.json` y falla si algún cantón queda sin zona o si alguna lista cambia, así que el reparto está fijado por test y no por revisión manual.

## 4. Qué cambió de comportamiento visible

El total del pedido ahora incluye el envío, así que algunas afirmaciones anteriores dejaron de ser correctas. Ninguna assertion se eliminó; todas se actualizaron con su valor nuevo.

| Archivo · test | Antes | Después |
| --- | --- | --- |
| `CheckoutTest` · `guest_order_has_snapshots…` | `total 246912` | `total 596912`, `subtotal 246912`, `shipping 350000` |
| `CheckoutTest` · `product_changes_do_not_change_historical_order` | `order.total_minor 246912` | `596912` |
| `CheckoutTest` · `changed_price_requires_explicit_review…` | `place() 400000` | `750000` |
| `CheckoutTest` · `confirmation_exposes_only_allowlisted_snapshot…` | sin `shipping` | con `shipping` |
| `CheckoutTest` · `currency_change_is_blocked_and_usd_order_remains_usd` | el pedido en USD se creaba | renombrado a `…_and_no_usd_order_is_created`: ya no se crea |
| `CommercialCatalogTest` · `public_catalog_cart_checkout…` | `1000000` | `1350000` |

Además, cuatro tests existentes necesitaron pedir la revisión con cantón porque **confirmar a un cantón distinto del cotizado ahora se rechaza**. Esto no estaba previsto en el plan y se decidió durante la ejecución:

- `CheckoutTest::test_territorial_hierarchy_and_full_catalog` revisaba el cantón 101 y confirmaba en Guácimo (706).
- `CheckoutTest::test_changed_price_requires_explicit_review…` volvía a revisar sin cantón antes de confirmar.
- `CheckoutTest::test_only_authenticated_customer…` y `CommercialCatalogTest` pisaban la revisión cotizada al visitar `/checkout` sin cantón.
- `CheckoutAvailabilityTest` y `OrderPaymentTest` revisaban sin cantón y luego confirmaban.

Dos garantías quedaron cubiertas por test explícito: un pedido confirmado **no cambia** si después se cargan tarifas nuevas, y una tarifa que cambia **entre** la revisión y la confirmación obliga a revisar de nuevo en vez de cobrar un monto que el comprador no vio.

Los pedidos anteriores a V1-C conservan `shipping_minor` nulo. No se recalculan, y su resumen no muestra línea de envío en lugar de inventar una.

## 5. Qué quedó fuera

Nada de lo siguiente se implementó, y conviene no darlo por hecho al planear V1-D:

- **Administración de tarifas.** ADR-007 pide tarifas configurables desde administración y esa pantalla no existe. Hoy cambiar una tarifa exige insertar un `rate set` nuevo en la base de datos. Es deuda explícita de V1-D.
- **Modos de entrega.** `direct_supplier` y `via_operation` (ADR-007) siguen sin implementarse: el cotizador no distingue el origen del envío.
- **Peso, dimensiones y cobertura por distrito.** La tarifa depende solo del cantón.
- **Impuestos.** No se calcula ni se aplica IVA. La puerta fiscal de ADR-008 sigue abierta.
- **Transportistas y seguimiento.** No hay integración con Rapedido ni con ninguna API de encomienda. El pedido no fija fecha de entrega.
- **Costo logístico real.** Lo que se cobra al comprador y lo que cuesta el transporte siguen siendo conceptos distintos; solo se modela el primero.

## 6. Verificación

Ejecutado en local sobre SQLite `:memory:`, al cierre de la fase:

```
php artisan test      → Tests: 195 passed (3478 assertions)
php vendor/bin/pint --test → {"tool":"pint","result":"passed"}
npm run check         → TypeScript sin errores; ✓ built
```

De los 195, 25 son nuevos de V1-C: 5 de `DeliveryZonesSeederTest`, 10 de `DeliveryQuoterTest` y 10 de `DeliveryCheckoutTest`.

**Pendiente de verificación humana:** la revisión visual del checkout en navegador (Tarea 4, paso 9) no fue ejecutada por quien escribe este reporte. Falta comprobar con la vista que el desglose y el bloqueo del botón se comportan como se espera en escritorio y en ancho de móvil.

**Pendiente en el servidor real:** `php artisan db:seed --class=DeliveryZonesSeeder --force`, una sola vez. Sin tarifas sembradas el checkout bloquea la confirmación. Registrado en [ENVIRONMENTS-RELEASE.md](ENVIRONMENTS-RELEASE.md).

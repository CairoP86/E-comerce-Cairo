# Fase 2C — Catálogo comercial, proveedores y precios

Estado: implementada y verificada, pendiente de revisión del usuario. Fecha local: 17 de septiembre de 2026. Base: checkpoint de Fase 2B `506ddf4`. Sin commit ni push.

## Resultado y alcance

El administrador puede gestionar proveedores y ofertas privadas sobre los productos existentes, escoger una oferta preferida, configurar multiplicadores y aplicar expresamente un precio sugerido. La tienda, el carrito y el checkout siguen usando exclusivamente el precio publicado de `products`. Los pedidos conservan sus snapshots.

Se registraron Eurocomp, Dataformas y CQ International como proveedores conocidos. Esto no declara que exista una integración disponible. No se implementaron clientes HTTP, endpoints externos, credenciales, scraping, sincronizaciones, pagos ni logística.

El trabajo previo no aprobado de 3A quedó apartado antes de continuar 2C, en el respaldo local ignorado `.local/phase-3a-held-before-2c`, con manifiesto y diff. Se verificó que sus tablas estaban vacías antes de revertir exclusivamente esa migración. El código activo no incluye pagos; la comprobación final confirma que `payment_attempts` y `payment_events` no existen. El respaldo no forma parte de la entrega ni debe publicarse como parte de 2C.

## Arquitectura

Se conservan `Product`, `Category`, `Brand`, imágenes, especificaciones, publicación, recursos públicos, carrito y checkout existentes. Una oferta no crea otro producto público.

Una migración nueva, `database/migrations/2026_09_18_000002_create_supplier_catalog.php`, agrega cinco tablas:

| Tabla | Responsabilidad |
| --- | --- |
| `suppliers` | Código único, nombre, activo, notas privadas y timestamps. |
| `supplier_products` | Producto y proveedor, SKU del proveedor, referencia, costo entero nullable, moneda, disponibilidad, stock nullable, fecha observada, fuente, activo y notas. |
| `pricing_rules` | Nombre único y multiplicador de punto fijo con cuatro decimales. |
| `category_pricing_rules` | Una regla por categoría; permite heredar desde una categoría superior. |
| `product_commercial_settings` | Una configuración por producto: oferta preferida nullable y excepción de multiplicador nullable. |

Las relaciones tienen claves foráneas con borrado restringido. El SKU es único dentro de cada proveedor. Un producto admite cero o múltiples ofertas. La preferencia solo acepta una oferta de ese producto, activa y de proveedor activo. Nunca se elige automáticamente el menor costo.

La fuente se fija en `manual` desde el servidor. `observed_at` indica cuándo se observó el dato y `updated_at` cuándo se guardó la fila: no se confunden con sincronización ni stock en tiempo real.

### Configuración inicial

`CommercialSetupSeeder` es explícito e idempotente: conserva modificaciones existentes y no crea productos, ofertas, SKU, costos ni stock.

Las categorías anteriores eran DEMO y no se remapearon a categorías comerciales. Se conservaron Cómputo, Redes y Periféricos como raíces en borrador, con sus IDs y reglas respectivas. Si el slug de una categoría ya existe al ejecutar `CommercialSetupSeeder`, este no le asigna una regla automáticamente; la relación debe revisarse en administración.

```powershell
php artisan migrate --force
php artisan db:seed --class=CommercialSetupSeeder --force
php artisan db:seed --class=CommercialTaxonomySeeder --force
```

### Corrección de revisión: taxonomía comercial inicial

Se ejecutó `CommercialTaxonomySeeder` sobre la base local y se repitió para comprobar idempotencia. Conservó las tres raíces existentes y creó **21 subcategorías**, para un total de **24 categorías comerciales en borrador**. La segunda ejecución no cambió filas de categorías, timestamps ni auditoría y no generó duplicados.

El seeder correctivo se ejecuta explícitamente, no automáticamente en cada despliegue. Inicializa esta jerarquía en borrador, por lo que no debe reutilizarse como sincronizador después de una futura publicación comercial. Identifica DEMO por el término independiente en nombre o slug e incluye todos los descendientes de esas ramas, aunque no lleven la marca en su propio nombre. Archiva sin borrar ni cambiar sus IDs o relaciones padre/hijo; no identifica categorías DEMO por el mero hecho de contener un producto DEMO.

Categorías DEMO archivadas (12): Tecnología DEMO, Laptops DEMO, Computadoras DEMO, Monitores DEMO, Procesadores DEMO, Tarjetas gráficas DEMO, Memoria RAM DEMO, Almacenamiento DEMO, Routers DEMO, Switches DEMO, Periféricos DEMO y Accesorios DEMO. Todas estaban publicadas antes de esta corrección.

Jerarquía final, completamente en **BORRADOR**:

```text
Cómputo [raíz · ×1.40]
├── Laptops
├── Computadoras de escritorio
├── Monitores
├── Componentes
│   ├── Procesadores
│   ├── Memoria RAM
│   ├── Almacenamiento
│   └── Tarjetas gráficas
└── UPS y energía
Redes [raíz · ×1.50]
├── Routers
├── Switches
├── Access Points
├── Wi-Fi Mesh
├── Adaptadores de red
└── Cableado y accesorios
Periféricos [raíz · ×1.70]
├── Teclados
├── Mouse
├── Combos teclado y mouse
├── Audífonos
├── Webcams
└── Docking stations y hubs
```

Los slugs comerciales siguen la ruta, por ejemplo `computo-componentes-memoria-ram`; se distinguen de los slugs históricos `demo-*`. Solo existen las tres asignaciones de reglas originales en las raíces. Las 21 subcategorías heredan sin asignaciones propias. Se verifica explícitamente `Cómputo → Componentes → Memoria RAM = ×1.4000`, y que una excepción de producto continúa teniendo prioridad.

La comprobación local antes/después confirmó que no cambiaron productos, imágenes, proveedores, ofertas, multiplicadores, pedidos, líneas ni direcciones de pedidos. No se crearon datos comerciales de productos. Quedan 36 categorías: 12 DEMO archivadas y 24 comerciales en borrador; ninguna categoría pública.

Archivos de esta corrección: `database/seeders/CommercialTaxonomySeeder.php`, `tests/Feature/CommercialTaxonomyTest.php`, `docs/PHASE-2C.md` y `README.md`. No requiere migración adicional. Los fixtures y el seeder DEMO existente permanecen intactos.

## Dinero y reglas comerciales

- Monedas admitidas: CRC y USD. Costo y precio deben tener la misma moneda para calcular una sugerencia; no existe conversión automática.
- Importes persistidos como enteros en unidades menores (100 unidades menores por unidad monetaria). No se usa float para calcular precios.
- Costo desconocido: `null`, nunca cero inventado. Costo confirmado: entre 1 y 999999999999 unidades menores.
- Multiplicador: entero escalado por 10000; `14000` representa `1.4000`. Rango permitido: `0.0001` a `100.0000`.
- Reglas iniciales: Cómputo ×1.40; Redes ×1.50; Periféricos ×1.70. Son multiplicadores, no porcentajes de margen.
- Prioridad: excepción del producto → regla de su categoría → regla del ancestro más cercano. Sin regla, no hay sugerencia.
- Redondeo: mitad hacia arriba a la unidad menor, usando `intdiv(costo * multiplicador + 5000, 10000)`. Los límites mantienen la operación dentro de un entero PHP de 64 bits.
- Un resultado fuera del rango público 1–999999999999 se rechaza.

`CommercialDecimal` concentra conversión, formato y cálculo. `CommercialPricing` selecciona los datos autorizados y produce la sugerencia. Editar costo, preferencia o multiplicador no cambia `products.price_minor`. El precio sugerido se calcula al consultar la pantalla privada; no se almacena como una segunda fuente de precio público.

### Aplicación manual y concurrencia

El botón «Aplicar precio sugerido» envía una revisión HMAC del cálculo mostrado. El servidor abre una transacción, bloquea el producto y los datos comerciales implicados, vuelve a calcular y compara la revisión. Un cálculo cambiado o no disponible se rechaza y requiere revisión del administrador.

Se actualiza únicamente el precio público; también se limpia el precio anterior si ya no sería mayor al nuevo precio. No se altera moneda, publicación ni pedidos históricos. El carrito y checkout mantienen sus validaciones existentes al detectar un precio público cambiado.

Los bloqueos de reglas, proveedores y categorías son amplios intencionalmente para este catálogo pequeño. Antes de automatizaciones masivas deberá revisarse su granularidad y medirse contención. Se probó rechazo de revisiones desactualizadas; no se realizó una prueba de carga concurrente de MySQL. No se añade un sistema general de idempotencia comercial: aplicar repetidamente el mismo importe no incrementa cantidades ni crea compras.

## Disponibilidad manual

- `unknown`: stock vacío.
- `available`: stock positivo si se conoce la cantidad; puede quedar vacío si solo se confirmó disponibilidad.
- `unavailable`: stock cero o vacío; no admite una cantidad positiva.
- Stock conocido: entero de 0 a 2147483647. La fecha observada es obligatoria y no puede ser futura.
- Desactivar oferta o proveedor invalida la sugerencia que dependía de ellos.

La disponibilidad manual se muestra privadamente y no reserva unidades ni constituye una garantía de venta. No modifica automáticamente publicación ni disponibilidad del carrito. Se preservan los límites y comportamiento de 2A/2B. Una oferta sin stock puede conservarse como referencia de costo; el administrador debe revisar comercialización antes de publicar.

## Administración y rutas

Las pantallas siguen el layout administrativo existente. El storefront conserva su paleta clara y sus componentes.

| Método y ruta | Uso |
| --- | --- |
| GET `/admin/commercial/suppliers` | Lista de proveedores. |
| GET `/admin/commercial/suppliers/{supplier}` | Detalle y ofertas paginadas. |
| POST `/admin/commercial/suppliers` | Crear proveedor. |
| PUT `/admin/commercial/suppliers/{supplier}` | Editar/desactivar proveedor. |
| GET `/admin/commercial/products/{product}` | Ofertas, preferencia, regla, costo y precios. |
| POST `/admin/commercial/products/{product}/offers` | Crear oferta manual. |
| PUT `/admin/commercial/products/{product}/offers/{offer}` | Editar/desactivar oferta. |
| PUT `/admin/commercial/products/{product}/settings` | Preferencia y excepción. |
| POST `/admin/commercial/products/{product}/apply-price` | Aplicar sugerencia revisada. |
| GET `/admin/commercial/rules` | Reglas y asignaciones. |
| POST `/admin/commercial/rules` | Crear regla. |
| PUT `/admin/commercial/rules/{rule}` | Editar regla. |
| PUT `/admin/commercial/category-rule` | Asignar regla o volver a heredar. |

Se accede a las ofertas desde el detalle administrativo de cada producto. El detalle del proveedor enlaza sus productos. No hay eliminación destructiva de ofertas.

## Seguridad y privacidad

- Autenticación, sesión válida, correo verificado y gates existentes en todas las rutas comerciales.
- `admin`: lectura y escritura. `operator`: lectura únicamente; cada ruta de escritura rechaza peticiones directas. `customer` recibe 403 y el invitado se redirige a login.
- Formularios con protección CSRF del middleware web existente; validación de campos permitidos y rechazo de parámetros adicionales.
- Listas explícitas de mass assignment en modelos nuevos. `product_id` y `source` de una oferta los establece el servidor.
- Se endurecieron `Order`, `OrderItem` y `OrderAddress`: la creación del pedido usa un conjunto explícito de valores calculados por el servidor; las relaciones fijan la pertenencia de sus snapshots.
- Costos y multiplicadores no se agregan a `Product`, a recursos públicos ni a los datos de carrito, checkout o pedidos públicos. La oferta preferida no se expone al cliente.
- Las respuestas comerciales usan `private, no-store`, `noindex, nofollow` e historial Inertia cifrado. Los errores de validación no conservan datos comerciales en `_old_input`.
- Las excepciones SQL de estas rutas se redactan para evitar consultas, bindings y costos en respuesta o logs.

La auditoría guarda evento, actor, entidad, identificador y nombres de campos afectados; no guarda importes, notas, SKU privados ni cuerpos de solicitudes. Cubre proveedores, ofertas/costos/activación, reglas, asignaciones, preferencia y aplicación de precios. No pretende reconstruir valores comerciales históricos completos; los pedidos sí mantienen sus snapshots comerciales originales.

## Catálogo real y datos locales

Se ejecutó `catalog:archive-demo`: archivó 20 productos que aún no estaban archivados. La comprobación posterior encuentra 21 DEMO archivados en total, incluyendo uno previamente archivado. No se borraron productos, imágenes, fixtures ni pedidos.

Estado local final: 3 proveedores, 3 reglas, 0 ofertas y 0 productos públicos. No se cargó información comercial ficticia. Las pruebas de navegador con ofertas se ejecutaron en SQLite aislado, no en la base MySQL de trabajo. Al finalizar se detuvo el servidor QA y se retiraron su base y archivo de credenciales temporales; se conservan scripts locales ignorados y capturas como evidencia.

Para comenzar con 20–30 productos, el administrador debe ingresar datos confirmados:

1. Marca y categoría reales; publicar la taxonomía adecuada.
2. Nombre/modelo, SKU interno, descripciones, garantía, especificaciones e imágenes autorizadas.
3. Moneda y precio público inicial. El flujo existente crea el producto en borrador; después puede aplicar una sugerencia.
4. Por proveedor: SKU confirmado, referencia, costo y moneda, disponibilidad y cantidad si se conoce, fecha observada y notas necesarias.
5. Elegir oferta preferida, revisar regla/excepción y decidir si aplica la sugerencia.
6. Revisar precio e imágenes antes de publicar. La carga normal crea productos sin marca DEMO.

El estado de preapertura del storefront y los avisos de pedidos sin cobro permanecen. La apertura comercial, condiciones definitivas y sustitución de textos generales de demostración requieren una revisión posterior; 2C no habilita cobros ni despachos.

## Verificación automatizada

Suite completa tras la corrección de taxonomía: **116 pruebas / 2678 aserciones**, todas correctas. Las 96 pruebas originales permanecen; Fase 2C añade 17 pruebas / 250 aserciones en `tests/Feature/CommercialCatalogTest.php` y esta corrección añade 3 pruebas / 99 aserciones en `tests/Feature/CommercialTaxonomyTest.php`.

Las pruebas de taxonomía cubren archivado no destructivo de ramas DEMO y descendientes sin marca, conservación de relaciones y productos, exclusión pública, raíces existentes, ausencia de datos comerciales inventados, idempotencia sin nuevos eventos de auditoría y herencia en las 21 subcategorías, incluido el segundo nivel de Memoria RAM y la prioridad de una excepción de producto. Los costos sintéticos utilizados para verificar el motor existen exclusivamente dentro de las transacciones de test.

Cobertura nueva: setup idempotente y ausencia de datos inventados, categorías existentes, permisos de todas las rutas, creación/desactivación de proveedores, múltiples ofertas, SKU/costo/moneda/stock, validaciones, costo desconocido, preferencia explícita, oferta ajena/inactiva, herencia y excepción, precisión y límites, edición de costo/regla sin publicación, revisión desactualizada, aplicación manual, auditoría sin valores, moneda incompatible, proveedor inactivo, desbordamiento de precio, producto público único, privacidad pública, carrito/checkout con precio publicado, snapshots históricos, archivado DEMO y mass assignment.

| Comprobación | Resultado |
| --- | --- |
| `php artisan test` | PASS: 116 / 2678 |
| `npm run check` | PASS: TypeScript y build |
| `npm run build` | PASS, ejecutado también por separado |
| `php vendor/bin/pint --test` | PASS |
| `composer validate --strict` | PASS |
| `composer audit` | Sin avisos de vulnerabilidad |
| `composer check-platform-reqs` | PASS con PHP 8.5.6 |
| `npm audit` | 0 vulnerabilidades |
| `php artisan foundation:check` | PASS MySQL, Redis cache y cola |
| `git diff --check` | PASS |

Composer emite un aviso de deprecación de su propia dependencia `json-schema` sobre `$http_response_header` en PHP 8.5; no fallan los comandos. No se modificaron dependencias para silenciarlo.

## Navegador, responsive y evidencia

Chrome local automatizado mediante Playwright. El navegador integrado no pudo iniciar por el error de entorno `missing field sandboxPolicy`; se usó la alternativa local y se documenta esta limitación.

Se revisaron 320×740, 430×932, 768×1024, 1366×768 y 1920×1080. En cada tamaño: proveedores, detalle de proveedor, reglas, ofertas del producto, producto público, carrito y checkout. Sin desbordamiento horizontal ni errores JavaScript. Se verificaron creación de oferta, selección manual, sugerencia con redondeo, aplicación de precio, foco en resumen de errores y controles de operator sin escritura. No es una auditoría exhaustiva con lector de pantalla.

34 capturas en `docs/screenshots/phase-2c-*.png`: 30 para las seis vistas principales en cinco tamaños, una de operator y tres de estados vacíos en la aplicación MySQL después del archivado. Las capturas con SKU y costo ficticios pertenecen exclusivamente al entorno QA y al producto identificado como prueba aislada.

Evidencia representativa:

- [Ofertas a 320 px](screenshots/phase-2c-product-320.png)
- [Proveedores a 430 px](screenshots/phase-2c-suppliers-430.png)
- [Reglas a 1366 px](screenshots/phase-2c-rules-1366.png)
- [Detalle de proveedor a 768 px](screenshots/phase-2c-supplier-768.png)
- [Producto público a 1366 px](screenshots/phase-2c-public-1366.png)
- [Checkout a 430 px](screenshots/phase-2c-checkout-430.png)
- [Operator](screenshots/phase-2c-operator.png)
- [Catálogo local vacío](screenshots/phase-2c-local-empty-catalog.png)

## Archivos principales de la entrega

- Migración de cinco tablas y `CommercialSetupSeeder`.
- Modelos: `Supplier`, `SupplierProduct`, `PricingRule`, `ProductCommercialSetting`.
- `CommercialCatalogController`, `CommercialPricing`, `CommercialDecimal`, `routes/commercial.php` y registro en `routes/web.php`.
- `ArchiveDemoCatalog`; endurecimiento de modelos de pedidos, creación interna en `CheckoutService` y tratamiento privado de errores en `bootstrap/app.php`.
- Cuatro páginas Vue en `pages/admin/commercial`, tipos comerciales, `CommercialErrors` y estilos; enlaces desde `AccountLayout` y `ProductForm`, e importación de CSS en `app.ts`.
- `CommercialCatalogTest`, este reporte, README y capturas.

## APIs futuras y decisiones pendientes

La futura sincronización podrá actualizar ofertas internas sin rediseñar `Product`. Debe conservar la separación entre costo, sugerencia y precio publicado, así como la auditoría y la fuente/fecha de datos. No se añadieron interfaces sin una responsabilidad actualmente implementada.

| Proveedor | Información pendiente antes de integrar |
| --- | --- |
| Eurocomp | Documentación oficial, entorno y credenciales; confirmar autenticación, catálogo, monedas, stock, reservas, órdenes y seguimiento según capacidades verificadas. La autorización general de integración no define estos contratos. |
| Dataformas | Confirmar disponibilidad y condiciones de API, documentación, credenciales y capacidades efectivas. |
| CQ International | Confirmar disponibilidad y condiciones de API, documentación, credenciales y capacidades efectivas. |

Una capacidad no documentada o no verificada se considera no disponible. No hay conexiones API configuradas.

La jerarquía solicitada por el usuario quedó aplicada y permanece en borrador para revisión. Pendientes posteriores: revisar si las reglas requieren excepciones para productos concretos y aportar los primeros datos comerciales confirmados. No se publican categorías ni se cargan productos en esta corrección. Redondeo comercial adicional, impuestos, tipo de cambio, caducidad de disponibilidad, selección automática y reglas de compra al proveedor quedan pendientes de alcance futuro.

Fase 2C termina aquí. No se inicia Fase 3.

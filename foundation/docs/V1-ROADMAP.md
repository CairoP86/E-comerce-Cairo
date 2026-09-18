# Auditoría técnica y roadmap V1.0

Fecha: 2026-09-18. Proyecto: `foundation/`. Base inspeccionada: `1adf939` — Complete Phase 2C. Estado inicial de Git: limpio.

## Actualización aprobada V1-A · 2026-09-18

La auditoría siguiente conserva el estado observado originalmente. V1-A documenta decisiones aprobadas en [ADRs](adr/README.md) y prepara [entornos/CI](ENVIRONMENTS-RELEASE.md); resultados en [V1-A-REPORT.md](V1-A-REPORT.md). La moneda operativa de lanzamiento es **CRC**, sin conversión; soporte heredado de lectura/pruebas USD se conserva y la restricción operativa de nuevos cobros se concretará en V1-C/E. La elección de monedas V1 ya no está pendiente.

Se ratifican invitado, separación costo/sugerencia/público, editorial vs vendible, capabilities pequeñas verificadas, modos direct_supplier/via_operation y estados comerciales/financieros/logísticos separados. Fiscalidad (IVA/inclusión/comprobantes/responsable/base de costos) y política final de cancelaciones/reembolsos permanecen pendientes; no se presumen aprobadas. Configuración `eurocom` normalizada a `eurocomp`, sin tocar filas; discrepancia documental corregida. Existe ahora CI mínimo sin deploy/seeders; ninguna integración o V1-B/C/D/E se implementa con ello.

Las filas de auditoría que describen ausencia de CI o alias eurocom corresponden a la inspección inicial; para el estado posterior consultar el reporte V1-A. Se mantiene la necesidad futura de MySQL/Redis real y certificación V1-I/J.

**Dictamen:** existe una base funcional hasta pedido pendiente de pago y mantenimiento comercial manual. No es todavía una V1 operable de venta, cobro y entrega. Conviene extenderla mediante migraciones aditivas y servicios de aplicación, conservando los contratos públicos, sesiones, snapshots y controles ya probados. No se justifica reescribirla ni separar microservicios.

Esta entrega es exclusivamente documentación. No crea productos, ofertas, tarifas, adapters, dependencias ni funcionalidades; tampoco ejecuta seeders o migraciones. El producto de prueba existente indicado por el propietario es suficiente: no hace falta poblar un catálogo ficticio.

## 1. Método, evidencia y límites

Se inspeccionaron migraciones, modelos, servicios, controladores, requests, recursos públicos, rutas, middleware, configuración, páginas Vue, scripts locales, tests y documentación 0A–2C. Las afirmaciones sobre comportamiento proceden del código activo; una clase prevista en un documento no se cuenta como implementación.

Referencias de esquema:

- `database/migrations/2026_09_15_000001_add_roles_and_audit_logs.php`: roles y auditoría.
- `database/migrations/2026_09_16_000001_create_catalog_tables.php`: categorías, marcas, productos e imágenes.
- `database/migrations/2026_09_17_000001_create_orders.php`: pedidos, líneas, dirección e historial inicial.
- `database/migrations/2026_09_18_000002_create_supplier_catalog.php`: proveedores, ofertas, reglas, asignaciones y preferencias.
- Migraciones iniciales de usuarios, caché y jobs: infraestructura base.

Se intentó una consulta agregada de solo lectura a MySQL local; la conexión a `127.0.0.1:3307` fue rechazada. Por ello no se certifica aquí el inventario de registros, el producto publicado ni las migraciones efectivamente aplicadas en esa instancia. El estado anterior de 2C es evidencia histórica, no una lectura actual. No se iniciaron ni reconfiguraron servicios para ocultar esta limitación. La suite usa SQLite en memoria, no esa base.

No se revisaron contratos externos de TiloPay o proveedores porque no fueron suministrados. No se atribuyen capacidades concretas a esas APIs. La entrega directa de Eurocomp sin factura comercial y la futura disponibilidad de credenciales se toman como contexto proporcionado por el propietario, no como contratos técnicos verificados.

Las capturas 1B–2C acreditan revisiones anteriores; esta auditoría no repite una certificación visual ni un pentest, prueba de carga o restauración de backups. No se leyeron ni reprodujeron secretos de `.env`.

## 2. Estado resumido

Flujo actualmente implementado:

`producto local publicado → carrito en sesión → revisión de datos y precios → pedido pending_payment → confirmación en la misma sesión`

El catálogo separa producto público de ofertas privadas. Los costos y reglas producen una sugerencia; solo una acción administrativa cambia el precio público. Los pedidos guardan importes y dirección propios. Los permisos son gates por rol, no un conjunto de policies por entidad; esto funciona para el alcance actual.

Flujo pendiente:

`disponibilidad vendible → cotización de entrega e impuestos → total final congelado → pago verificado → preparación → despacho(s) → seguimiento → entrega`

La V1 propuesta puede operar con proveedores y transportistas administrados manualmente, siempre que disponga de un control conservador y aprobado de disponibilidad y un procedimiento real de compra/despacho. **Una API de proveedor no es requisito para terminar el desarrollo base. TiloPay real, condiciones comerciales, tarifas y operación de producción sí son puertas de lanzamiento.** No se presentará un modo manual como stock en tiempo real.

## 3. Matriz de capacidades

Leyenda: **COMPLETO / COMPLETE** = resuelto en el alcance concreto señalado, no certificación global de producción; **PARCIAL / PARTIAL** = existe base pero faltan piezas; **NO IMPLEMENTADO / NOT IMPLEMENTED** = no hay flujo ejecutable; **REQUIERE INTEGRACIÓN EXTERNA / EXTERNAL** = depende además de contratos o servicios reales. La columna final identifica dependencias y siguiente trabajo.

### 3.1 Catálogo

| Elemento / estado | Evidencia actual | Falta y riesgo | Dependencia / propuesta |
| --- | --- | --- | --- |
| Productos, marcas y edición manual — COMPLETO | `Product`, `Brand`, `CatalogProductController`, `SaveProductRequest`, `routes/catalog.php`, `pages/admin/catalog/*`; SKU/slug únicos, dinero entero, borrador/publicado/archivado. | Mantener regresiones; publicación no demuestra stock ni posibilidad de entrega. | Reutilizar CRUD; disponibilidad en V1-B y logística en V1-C. |
| Categorías y subcategorías — COMPLETO | `Category::publicIds`, `CatalogTaxonomyController`; validación de ciclos y ancestros; `CommercialTaxonomySeeder` y `CommercialTaxonomyTest`. | Seeder de inicialización vuelve a borrador; no debe correr en despliegues rutinarios. | Conservar 3 raíces y 21 descendientes, sin republicar DEMO. La publicación es una decisión comercial posterior. |
| Imágenes — PARCIAL | `CatalogImageController`, `ProductImage`; máximo 12, JPEG/PNG/WebP, tamaño/dimensiones, recodificación WebP, alt, orden y soft delete. Disco privado `catalog`. | No hay variantes responsivas/CDN; cada imagen pasa por PHP con sesión y `no-store`. GD/WebP es requisito efectivo no expresado como extensión requerida en Composer. | Verificar GD/WebP en imagen de producción; almacenamiento persistente con backup y entrega cacheable solo para imágenes públicas. |
| Especificaciones — COMPLETO | JSON validado con claves estables, grupos/unidades y límite 50, render en `pages/catalog/Show.vue`. | No son atributos normalizados para facetas técnicas. | Conservar; facetas por RAM/CPU no bloquean V1 inicial. |
| Publicación — COMPLETO | `validatePublication`: marca y toda la cadena de categorías publicadas, al menos una imagen; restauración de archivado vía borrador. | No comprueba disponibilidad comercial. | Añadir condición de venta diferenciada del estado editorial; no convertir publicación en reserva. |
| Búsqueda/filtros/ordenamiento — COMPLETO | `PublicCatalogController::index`: nombre/SKU, escape LIKE, categoría con descendientes, marca, moneda, precio, editorial, destacados/ofertas; 6 órdenes y paginación. | LIKE y carga de taxonomía pueden degradarse al crecer; no hay búsqueda avanzada. | Medir con volumen objetivo antes de sustituir motor. Evitar una dependencia de búsqueda prematura. |
| SEO de lanzamiento — PARCIAL | Meta title/description, canonical y Open Graph en `PublicCatalogController`, `StoreSeo.vue` y `views/app.blade.php`. | `noindex, nofollow` fijo; `public/robots.txt` permite rastreo; no sitemap, Product JSON-LD ni SSR de contenido. Cambiar slug deja URL anterior sin redirección registrada. | V1-H: modo de indexación por entorno, sitemap solo publicado y redirecciones; evaluar render inicial/SSR. No basta quitar una meta sin revisar las demás. |
| Importación CSV/Excel — NO IMPLEMENTADO | No rutas, jobs ni parser de importación en código/dependencias. | Importación directa podría duplicar SKU, publicar datos incompletos o filtrar costos. | V1-H: CSV con previsualización, validación, reporte por fila y staging; Excel mediante exportación a CSV en V1. XLSX nativo es mejora posterior si se confirma necesario. |

### 3.2 Proveedores y pricing

| Elemento / estado | Evidencia actual | Falta y riesgo | Dependencia / propuesta |
| --- | --- | --- | --- |
| Múltiples ofertas y privacidad — COMPLETO | `Supplier`, `SupplierProduct`, `ProductCommercialSetting`, migración comercial, `CommercialCatalogController`; SKU único por proveedor, referencia, costo/moneda, stock, disponibilidad, fecha y fuente manual. `PublicProductResource` usa lista pública explícita. | La preferencia no es una asignación de compra ni confirma inventario. | Reutilizar IDs; añadir asignación operativa por línea y snapshot privado de costo. |
| Disponibilidad vendible — PARCIAL | Se registran `stock`, `availability`, `observed_at`, `active`; campos visibles solo en administración. | `CartService` y `CheckoutService` no consultan ofertas; 99 unidades es límite técnico, no stock. No TTL ni reservas/asignaciones. | V1-B: política de frescura y aprobación manual, disponibilidad vendible y asignaciones locales atómicas. Sin dato válido, bloquear cobro automático o solicitar revisión. |
| Adapters de proveedor — REQUIERE INTEGRACIÓN EXTERNA | Diseño en `docs/EUROCOMP-ARCHITECTURE.md`; no interfaces/adapters ejecutables. `config/commerce.php` mantiene `eurocom` deshabilitado, frente al código persistido `eurocomp`. | Desfase nominal/documental; una interfaz única grande obligaría a simular funciones no soportadas. | Normalizar identificación en V1-B y crear puertos pequeños solo al existir un consumidor real; integración opcional X1 con documentación. |
| Reglas y sugerencia — COMPLETO | `CommercialDecimal`, `CommercialPricing`, `PricingRule`; escala 10000, redondeo entero; herencia hasta raíz y override por producto; pruebas para RAM a segundo nivel. | Multiplier admite valores menores a 1; no hay política de autorización de venta bajo costo. | Conservar cálculo; definir alertas privadas y autorización en V1-G. No cambiar automáticamente precios. |
| Aplicación manual / snapshots — COMPLETO | `CommercialPricing::apply` usa revisión HMAC, transacción y locks; `CheckoutService`, `OrderItem`, `OrderAddress` guardan snapshots. | Los locks de reglas/proveedores/categorías son amplios; no hay prueba de carga MySQL. | Validar concurrencia real y reducir contención solo con evidencia. |
| Margen/rentabilidad — PARCIAL | Se ven costo, multiplicador, sugerencia y precio público en `admin/commercial/Product.vue`. | No se muestran margen bruto, comisión de pago, costo logístico real ni rentabilidad de pedido; no hay costo histórico de compra. | V1-D/G: costo por línea y gastos privados con procedencia; margen estimado/real explícitos, nunca confundir ×1.40 con 40% de margen. |

### 3.3 Logística, pagos y pedidos

| Elemento / estado | Evidencia actual | Falta y riesgo | Dependencia / propuesta |
| --- | --- | --- | --- |
| Dirección Costa Rica — COMPLETO | `CostaRicaTerritories`, `ConfirmCheckoutRequest`, snapshot `order_addresses`; jerarquía provincia/cantón/distrito y teléfono normalizado. | Un distrito válido no implica cobertura; referencia territorial debe mantenerse. | Usar códigos como entrada de zonas y versionar cambios, sin alterar direcciones históricas. |
| Tarifas, peso, volumen, retiro y envío gratis — NO IMPLEMENTADO | No tablas/campos ni servicios logísticos. UI dice que transporte no se calcula. | Cobrar subtotal como total final sería incorrecto cuando existan cargos. | V1-C: tarifas configurables, perfiles físicos y cotización versionada. Requiere decisiones y estudio de tarifas reales. |
| Despacho directo / vía operación / tracking — NO IMPLEMENTADO | No shipments, transportistas, guías ni asignaciones proveedor-pedido. | No se puede coordinar entrega ni distinguir costo real/cobrado. | V1-D/F: uno o más despachos y tramos, actualización manual auditable; APIs de transportista opcionales. |
| Total final — PARCIAL | `CheckoutService::quote` suma líneas; `subtotal_minor` y `total_minor` se guardan iguales. | No hay shipping, descuentos explícitos, impuestos o snapshots de cálculo. | V1-C: calculador autoritativo, total = subtotal − descuentos + envío + impuestos según política de inclusión acordada. |
| TiloPay — REQUIERE INTEGRACIÓN EXTERNA | No cliente, rutas, modelos ni migraciones de pago en árbol activo. | Cuenta existente no demuestra contrato técnico, monedas, firma ni conciliación. | V1-E: documentación y sandbox reales; diseñar intents internos sin inventar requests, webhooks o estados del proveedor. |
| Creación de pedido — COMPLETO | `CheckoutService::confirm`: token, HMAC de revisión/datos, transacción, clave única, owner y vaciado posterior al commit. | Es pedido sin cobro; no hay fecha de expiración ni proceso de abandono. | Preservar idempotencia; agregar TTL y revisión compatible antes del pago. |
| Estados/historial operativo — PARCIAL | `OrderStatus` contiene solo `PendingPayment`; `order_status_history` registra creación; `OrderAdminController` solo lista/consulta. | No existe máquina de transiciones completa, notas, cancelación ni historial presentado al operador. | V1-D: transiciones controladas y separación de estados financieros y logísticos; no reinterpretar pedidos anteriores como pagados. |
| Costos históricos/proveedor/rentabilidad — NO IMPLEMENTADO | `order_items` conserva precio vendido pero no oferta/costo; `orders` no tiene costo de envío o pago. | Cambiar proveedor preferido después no permite reconstruir la operación. | Snapshots privados de asignación/costo y libro de gastos; desconocido permanece null. |

### 3.4 Cliente, administración, notificaciones y producción

| Elemento / estado | Evidencia actual | Falta y riesgo | Dependencia / propuesta |
| --- | --- | --- | --- |
| Carrito invitado — COMPLETO | `CartStore`, `SessionCartStore`, `CartService`, rutas/Vue; moneda única, 99/50, revisión y registro de últimas 100 operaciones. Bloqueo global de sesión en `config/session.php`. | Logout invalida carrito; no hay persistencia entre dispositivos ni TTL de oferta. Locks también afectan páginas/imágenes. | Mantener invitado principal; validar política de sesión y rendimiento real en V1-I. |
| Checkout y confirmación — PARCIAL | `CheckoutController`, `pages/checkout/*`; invitado sin registro y asociación opcional a customer. | Confirmación requiere `checkout_owner` en sesión, incluso para cliente registrado; no recuperación tras perder sesión. | V1-F: acceso seguro por correo y cuenta, manteniendo aislamiento. |
| Historial/direcciones guardadas — NO IMPLEMENTADO | `account/Overview.vue` solo muestra perfil; `orders.user_id` ya existe. | Cliente no puede consultar historial fuera de confirmación. | V1-F: listado por ownership, direcciones opcionales y asociación de invitados solo tras prueba de control del correo. |
| Políticas, garantía y devoluciones — PARCIAL | Texto de garantía por producto; avisos generales de preapertura. No rutas de términos, privacidad, política de entrega o devolución. | Falta información aprobada para vender y procedimiento de posventa. | V1-H: contenido versionado aprobado y solicitud de posventa manual trazable. Revisión fiscal/legal externa; no inventar plazos normativos. |
| Administración — PARCIAL | CRUD catálogo/comercial, pedidos read-only y auditoría. `admin/Overview.vue` sigue siendo panel informativo 0B. | Sin cola de preparación, incidencias, ventas conciliadas ni alertas operativas. | V1-D/G: acciones reales con permisos y filtros; indicadores derivados de pagos/entregas, no de pedidos meramente creados. |
| Emails comerciales — NO IMPLEMENTADO | Solo mensajes de verificación y recuperación en `AppServiceProvider`; único job propio: `VerifyInfrastructure`. | No aviso de pedido/pago/envío ni reintentos comerciales. | V1-D/F: eventos internos + outbox transaccional + jobs idempotentes, proveedor de email real. |
| Autenticación/autorización — PARCIAL para producción | `AuthController`, gates, middleware auth/session/verified, roles no asignables públicamente, throttles de auth; operator comercial read-only. | No MFA/backoffice granular, permisos de reembolso/despacho aún inexistentes. | V1-A/D/I: permisos por acción, MFA administrativo o control equivalente aprobado; tests de denegación para cada ruta nueva. |
| CSRF/validación/PII — PARCIAL para producción | Middleware web, requests, recursos allowlist, `PrivateCommerce`, cifrado de historial y errores SQL redactados en rutas comerciales/pedidos. | No política de retención/anonimización ni redacción global de excepciones de nuevos módulos; PII en BD sin cifrado de campos. | V1-I: minimización, cifrado de infraestructura y acceso, retención aprobada, tests de logs/errores; no publicar cuerpos externos. |
| Rate limiting y abuso — PARCIAL | Límites login/auth y POST checkout 20/min; no throttle explícito de mutaciones de carrito o búsqueda. | Abuso de recursos, spam de pedidos y de futuras recuperaciones. | V1-I/F: límites por operación/IP/identidad y monitorización, con excepciones legítimas evaluadas. |
| Colas/Redis/correo — PARCIAL | Config Redis; after_commit en colas; migración failed jobs; herramientas locales y Mailpit en `.env.example`. | No supervisión de workers, scheduler comercial, alerta de cola ni entrega SMTP productiva demostrada. | V1-I: workers supervisados, scheduler, retries/backoff/DLQ y correo autenticado; healthchecks reales. |
| Despliegue/secretos/backups — PARCIAL | Lockfiles, `.env.example`, ignores y scripts solo locales. No CI/deploy/restore versionados encontrados. | `APP_DEBUG=true`, HTTP y SMTP local son valores de ejemplo, no perfil de producción; backups restaurables no demostrados. | V1-A/I: staging/CI, secretos externos a Git, TLS, permisos y backup BD+imágenes+claves con restauración comprobada. |
| Observabilidad/errores — PARCIAL | `/up`, logs Laravel y auditoría; check local MySQL/Redis. | `/up` no valida todo el negocio; no SLO, métricas, correlación, alarmas o runbooks. Auditoría ORM no equivale a almacenamiento inviolable. | V1-I: señales de checkout/pago/despacho/colas, acceso mínimo y exportación/retención de auditoría. |
| Pruebas y preparación de release — PARCIAL | PHPUnit + Vue TypeScript/build; reportes visuales históricos. Tests con SQLite, cache/session array, queue sync. | Sin CI ni E2E completo versionado; no prueban locks MySQL/Redis ni TiloPay, email real, recuperación o despliegue. | V1-I/J: matriz automatizada y certificación operativa indicada abajo. |

## 4. Riesgos priorizados

| Prioridad | Hallazgo y evidencia | Tratamiento / cierre |
| --- | --- | --- |
| Bloqueante | No hay stock vendible ni reserva: checkout valida publicación, no ofertas. | Política conservadora de V1-B antes de habilitar pagos; prueba de stock agotado, vencido y competencia. Consulta nunca se etiqueta como reserva. |
| Bloqueante | Total solo de productos; sin envío/impuestos definidos. | Cotización congelada y desglose correcto antes de integrar monto de cobro. Definir inclusión fiscal y moneda con responsables. |
| Bloqueante | No cobro verificable ni operaciones posteriores al pedido. | Completar V1-D/E/F con sandbox y pruebas de fallos; no despachar por retorno del navegador. |
| Alto | Cliente pierde acceso al pedido al perder sesión. | Recuperación por correo sin enumeración, acceso limitado/expirable y comprobación de titularidad. |
| Alto | No costo histórico, guía ni proveedor asignado al pedido. | Snapshots internos por línea y por tramo; rentabilidad desconocida hasta tener datos reales. |
| Alto | El perfil productivo, backup y restauración no están demostrados. | Ensayo en staging y restauración independiente antes de release; revisión de secretos/cookies/debug. |
| Alto | Tests no reproducen locks/colas del stack productivo. | Pruebas MySQL/Redis con procesos concurrentes y reentregas; no extrapolar resultados SQLite. |
| Medio | Serialización de todas las solicitudes de sesión; imágenes privadas sin caché. | Medir latencia/bloqueos; optimizar entrega pública sin introducir carreras de sesión. |
| Medio | Locks amplios de pricing/categorías y búsqueda LIKE. | Pruebas de volumen y duración de transacciones; mantener llamadas externas fuera de locks. |
| Medio | Métodos administrativos de oferta/regla no llevan revisión optimista general; solo aplicación del precio sí. | Evitar sobrescritura silenciosa entre operadores mediante versión o conflicto explícito en ediciones críticas. |
| Medio | Inicializadores pueden archivar DEMO/reiniciar taxonomía a borrador. | Excluir seeders de inicialización del deploy; migraciones aditivas y pasos operativos explícitos. |
| Medio | `config/commerce.php` usa eurocom; documento temprano dice no tablas y su flujo parece publicar cálculo automáticamente. | Actualizar ADR al iniciar implementación: 2C autoriza tablas/manual y prevalece aplicación explícita del precio. No conectar por un alias ambiguo. |
| Medio | Frontend presenta textos demo/preapertura y `noindex` fijo. | Checklist editorial y SEO por entorno; mantener privadas rutas administrativas y de pedido. |

Estos son gaps constatados y riesgos de diseño; no son afirmaciones de explotación ni certificación legal. No se halló necesidad de reemplazar las bases de catálogo, dinero o checkout.

## 5. Arquitectura objetivo propuesta

### 5.1 Límites de módulos

Mantener un monolito: Catálogo, Proveedores/Disponibilidad, Pricing, Checkout, Pedidos, Pagos, Fulfillment/Envíos, Notificaciones y Operación. Servicios de aplicación coordinan transacciones. Recursos públicos siguen siendo allowlists independientes de modelos comerciales. Admin modifica mediante comandos autorizados, no asignando estados directamente desde formularios.

Nombres siguientes son **diseño**, no clases ya existentes ni una autorización para crearlas ahora.

### 5.2 Proveedores y disponibilidad

`SupplierAdapterInterface` puede identificar al proveedor y exponer capacidades verificadas; separar lectura de catálogo, consulta de disponibilidad, reserva/liberación y envío/consulta de órdenes. Un adaptador no tiene que implementar capacidades desconocidas. Resultado desconocido, vencido o error es explícito; jamás se transforma en éxito o stock ilimitado.

Primero implementar un consumidor de disponibilidad y su operación manual real. Luego conectar Eurocomp, Dataformas o CQ cuando exista documentación. Las firmas externas, auth, DTOs y endpoints se definen en X1; no se derivan del ejemplo de dominio. HTTP fuera de transacciones largas, timeouts y límites documentados; idempotencia/reconciliación antes de reintentar compras.

Cada oferta conserva origen, SKU, costo y stock. Una futura sync agrega ejecuciones/errores, fecha de sincronización, versión y frescura sin escribir precios públicos. Mapeos explícitos relacionan taxonomía externa con producto interno; no fusionar solo por nombre. Imágenes/especificaciones importadas requieren derechos y validación.

Las asignaciones locales solo evitan doble venta interna; no reservan inventario del proveedor. Si no hay garantía externa, V1 debe usar aprobación manual con vigencia y límites conservadores, o dejar solicitud sin posibilidad de cobro automático hasta confirmación. La política final y resolución por falta de stock requieren aprobación comercial antes de producción.

### 5.3 Logística configurable y total final

Agregar de forma aditiva perfiles físicos (peso en gramos, dimensiones en milímetros, nulabilidad explícita), ubicaciones/orígenes, métodos de entrega/retiro, zonas por códigos territoriales, reglas de tarifa y versiones/vigencias. No usar float para dinero ni peso. No calcular peso volumétrico con un divisor supuesto: será un parámetro validado con el transportista.

Una tarifa administra moneda, zona, método, origen aplicable, prioridad, bandas de peso/volumen/monto, cargo base/adicional, umbral de gratuidad, exclusiones y vigencia. Validar solapamientos: precedencia definida y empate inválido; una zona sin cobertura bloquea el método, no produce envío gratis. Retiro requiere ubicación/horario confirmado; no inventar costo cero para otros métodos.

Cotización interna: ID opaco, revisión, vencimiento, destino normalizado, paquete(s), origen(es), método, regla/versión, desglose y moneda. Cambiar dirección, cantidades, método o tarifa invalida revisión. La selección del cliente nunca aporta importes autorizados. Si falta peso necesario, pedir validación operativa o deshabilitar ese método; no asumir cero.

Preservar `orders.subtotal_minor` y `total_minor`; añadir descuentos, envío cobrado e impuestos con snapshots/versión. Definir si precios incluyen impuestos para no sumarlos dos veces. Sin descuentos habilitados su importe es cero explícito; promociones complejas no son requisito. No convertir divisas automáticamente: tarifa/cotización/pedido/pago deben ser compatibles. Un proveedor/tarifa disponible solo en otra moneda exige una política aprobada o no habilitar esa combinación.

Para pedidos históricos no inventar costos de envío o impuestos: usar versión legacy y campos desconocidos/null cuando corresponda. Las migraciones no recalculan totales antiguos.

Separar `shipping_charged_minor` público del costo logístico real privado; el envío gratis al cliente no borra el gasto. El monto enviado a pagos será el total final persistido, nunca un cálculo del navegador ni una suma nueva de tarifas cambiantes.

### 5.4 Pedidos y fulfillment

Conservar `pending_payment` y extender de manera compatible:

| Transición comercial propuesta | Condición |
| --- | --- |
| pending_payment → paid | Confirmación financiera confiable con moneda e importe coincidentes. |
| pending_payment → cancelled | Cancelación/expiración permitida; tratar pago tardío sin reactivar automáticamente un pedido cancelado. |
| paid → processing | Asignación de abastecimiento y autorización operativa. |
| processing → ready_to_ship | Preparación completada; paquetes y destino revisados. |
| ready_to_ship → shipped | Despacho registrado con transportista/referencia y responsable. |
| shipped → delivered | Evidencia manual o externa verificada de entrega de todos los paquetes requeridos. |
| paid/processing/ready_to_ship → cancelled | Política y permisos; obligación de reembolso registrada por separado. |

`failed` corresponde a un intento de pago; no destruye un pedido reintentable. `refunded` corresponde a situación financiera; no sustituye el historial logístico. Mantener estados financieros, comerciales y de despacho separados y derivar resúmenes coherentes. Devolución después del despacho se registra como caso de posventa; no se borra el hecho de haber enviado/entregado.

Toda transición registra origen/destino, actor o evento confiable, causa, timestamp y revisión/idempotencia. Rechazar saltos y concurrencia incompatible. Agregar notas internas con acceso limitado y trazabilidad, sin incluirlas en confirmación pública.

Pedido puede tener múltiples asignaciones por línea y múltiples paquetes. Guardar proveedor/oferta/SKU, cantidad, costo confirmado y moneda como snapshot privado, además de fecha/fuente. Cambios de preferencia posteriores no alteran esta asignación.

Modelar modo `direct_supplier` y `via_operation`: en el segundo registrar recepción en operación y salida final como tramos distintos; en el primero registrar origen proveedor y destino cliente. En V1 el operador puede registrar compra, transportista, guía, eventos y evidencia manualmente. No afirmar que existe una orden automática al proveedor. Pedido parcialmente despachado sigue pendiente de entrega completa; la UI debe explicar paquetes pendientes.

Tracking público expone solo transportista, referencia autorizada, eventos útiles y estado; no costos, acuerdos ni notas internas. Validar URLs de tracking por plantilla de transportista/lista permitida. La condición de entrega sin factura comercial de Eurocomp se recoge en instrucciones/confirmación operativa; no se interpreta como exención de nuestras obligaciones de comprobantes.

### 5.5 Pagos TiloPay

Persistir intentos de pago, eventos recibidos y movimientos/reembolsos separados del pedido. El intento fija pedido, moneda, total final, identificador interno y estado; referencias externas solo cuando realmente recibidas. No almacenar PAN/CVV ni secretos en esos registros.

Diseñar operaciones internas de iniciar, consultar/conciliar y solicitar reembolso únicamente cuando el método externo esté documentado. El mecanismo concreto de autenticación y verificación de notificaciones depende de la documentación real. Si no existe webhook verificable, no fingirlo: evaluar el mecanismo oficial alternativo antes de aprobar integración.

El retorno del navegador es UX, no fuente de verdad. Procesar confirmación autenticada y, cuando corresponda, consulta servidor a servidor; validar importe, moneda, pedido y duplicados. Guardar evento antes de procesarlo, limitar/reducir payloads, soportar desorden y reentrega. No repetir una petición de cobro de resultado desconocido sin conciliación. Un segundo pago, diferencia de monto o pago tardío dispara incidencia privada y acción financiera controlada.

Las transacciones y el envío de correo deben ser consistentes: actualizar intento/pedido/historial/outbox en una transacción local; llamadas externas fuera de locks. Política de cancelación y reembolso total es necesaria para operar; reembolso parcial solo se habilita tras verificar soporte y definir reglas. No etiquetar como reembolsado hasta prueba financiera.

### 5.6 Notificaciones y acceso del cliente

Eventos internos: pedido creado, pago confirmado, preparación iniciada, despachado/tracking y entregado; también cancelación/incidencia útil para el comprador. Outbox transaccional, consumidores idempotentes por evento/canal/plantilla y jobs con backoff, límites y alerta de fallos. Preferir email; puertos de canal permiten ampliar después sin invocar proveedores de mensajería desde `CheckoutService`.

Correo enviado no equivale a recibido: registrar intentos y estado cuando el servicio real lo permita. Evitar cuerpos completos en logs. Plantillas solo con proyección pública y enlaces seguros.

Invitado recupera su pedido mediante respuesta genérica y enlace secreto expirable/de un solo uso que crea acceso limitado, o código de verificación equivalente; rate limiting y auditoría. No bastan número de pedido+correo sin prueba de control. Un cliente autenticado ve solo pedidos propios; la vinculación posterior de pedidos invitados requiere verificación explícita, nunca coincidencia automática de email. Direcciones guardadas no reescriben snapshots.

### 5.7 Márgenes y administración

Mostrar contribución estimada por producto = precio neto comparable − costo base comparable. Margen bruto = contribución / venta neta, con precisión controlada; markup es otra métrica. Requiere costos/precios con base fiscal y moneda compatibles. Si no hay costo confirmado, indicar «desconocido», no beneficio del 100%.

Por pedido distinguir estimación al aceptar, compras confirmadas, gastos logísticos reales, comisiones y ajustes/reembolsos. Reportar rentabilidad provisional hasta conocer todos los costos. No recalcular pasado desde ofertas actuales. Dashboard mínimo: pedidos pendientes de acción, pagos sin conciliar, stock vencido, despachos pendientes, fallos de email/cola y ventas confirmadas por moneda. Ninguna cifra de demostración.

## 6. Roadmap V1.0 recomendado

Etiquetas V1-A…J son una propuesta de planificación; no sustituyen retrospectivamente fases 0A–2C ni autorizan iniciar una fase. Cada fase termina con reporte y revisión. No necesita cargar catálogo real: usar el único producto existente y fixtures exclusivos de tests/staging.

| Fase | Alcance y dependencias | Criterios de aceptación | Pruebas obligatorias |
| --- | --- | --- | --- |
| **V1-A · Contratos internos y base de release** | Parte de 2C. Aprobar políticas de stock, fiscalidad/monedas, cancelación, entrega y permisos; preparar CI/staging y resolver ADR/alias de proveedor. | ADRs claros; pipeline reproduce baseline; secretos fuera de repositorio; nadie despliega seeders de inicialización automáticamente. | Suite actual intacta, instalación limpia, migración aditiva de ensayo y aislamiento de datos. |
| **V1-B · Disponibilidad comercial y preparación de abastecimiento** | A. Consumidor de disponibilidad manual, frescura, límites vendibles y asignaciones locales; contratos pequeños con consumidores reales. | No se puede cobrar disponibilidad desconocida/vencida sin revisión aprobada; preferencia y asignación diferenciadas; no API ficticia ni precio automático. | Stock agotado/cambiante, doble compra, TTL, proveedor inactivo, ninguna reserva externa simulada; pruebas MySQL/Redis. |
| **V1-C · Cotización de entrega y total final** | A/B y política comercial/fiscal. Zonas, tarifas configurables, peso/volumen, envío gratis/retiro, ambos modos de despacho y snapshot monetario. | Admin modifica tarifas sin código; checkout calcula y confirma total completo; cobertura ausente bloquea; históricos inmutables. | Fronteras de zonas/bandas/umbral, segundo nivel territorial, monedas, redondeo, datos físicos ausentes, tarifa vencida y cambios concurrentes. |
| **V1-D · Núcleo operativo de pedidos** | B/C. Transiciones compatibles, asignaciones/snapshots, notas, paquetes/tramos, historial visible y outbox. | Se conserva pending_payment; estados nuevos solo por comandos; preparación/despacho requieren precondiciones y actor; no cambia un pedido histórico. | Máquina de estados, ownership/roles, conflictos, cancelación, paquetes parciales y rollback de historial/outbox. |
| **V1-E · Pagos TiloPay verificables** | C/D + documentación, sandbox y credenciales reales. Intentos, notificaciones según contrato, retorno, conciliación, cancelación/reembolso permitido. | Monto enviado coincide con snapshot; retorno no marca paid; duplicados no duplican efectos; incertidumbre genera conciliación/incidencia. | Sandbox real: éxito, rechazo, timeout, reentrega/desorden, importe/moneda incorrectos, pago tardío/doble, refund y recuperación tras caída. |
| **V1-F · Operación de entrega y experiencia del cliente** | D/E + transportistas/modos/manual operativo + proveedor email. UI de preparación, despacho, guía, tracking, entrega; emails, recuperación invitado, historial y direcciones opcionales. | Ambos recorridos ejecutables; cliente accede sin registro y recibe comunicaciones; guías/notas/costos correctamente separados. | E2E directo y vía operación; múltiples paquetes, pérdida de sesión, enlace vencido/reutilizado, permisos, emails duplicados/reintentados. |
| **V1-G · Control comercial y operativo** | D/E/F. Margen estimado/real, gastos y comisiones, alertas, filtros de pedidos y conciliación. | Métricas provienen de eventos reales; moneda segregada; costos faltantes explícitos; operador no puede alterar finanzas sin permiso. | Reembolsos y netos, costos posteriores, exportación privada, controles de acceso y no fuga en props públicas. |
| **V1-H · Catálogo de lanzamiento y contenido** | A; cierre después de C/F. CSV con preview/importación en borrador, SEO, redirecciones, políticas, garantía/posventa y limpieza de textos demo. | No publicación masiva implícita; sitemap solo público; indexación por entorno; textos y políticas aprobados; sitio sin promesas falsas. | CSV mal formado/duplicado/fórmula, imágenes inválidas, fuga de columnas privadas, metadatos/canonical/404/301, teclado y responsive. |
| **V1-I · Seguridad y operación productiva** | Trabajo progresivo desde A; cierre tras E–H. TLS, cookies/headers, MFA/control admin, límites, backups, workers/scheduler, observabilidad y perfil de producción. | Restauración demostrada, alertas y runbooks, servicios supervisados, secretos rotables, errores sin PII; sin hallazgos críticos/altos abiertos. | Restore aislado, fallo Redis/DB/email, reconexión/colas, carga concurrente y recursos, CSP según frontend, cache privada y logs redactados. |
| **V1-J · Certificación y release** | Todas las puertas anteriores y datos externos aprobados. Ensayo integral y despliegue controlado con rollback. | Checklist de sección 7 firmado y evidencia adjunta; release reproducible desde checkout limpio. | Matriz completa de sección 9, smoke postdeploy y simulacro de incidencia/rollback. |

**Orden crítico:** A → B → C → D → E → F → G → cierre H/I → J. Contenido/importación y preparación de infraestructura pueden trabajarse en paralelo tras A; no son motivo para interrumpir el camino de disponibilidad → total → pago → operación.

**X1 · APIs reales de proveedores, rama condicionada:** después de B y solo con documentación. Eurocomp primero, los demás independientes. Criterios: contratos verificados, sincronización idempotente y trazable, catálogo local siempre disponible para navegar, privacidad, timeouts y desconocido explícito; reservas/órdenes solo si soportadas. X1 no bloquea V1 si el modo manual acordado es operativo y seguro. Si el negocio exige abastecimiento automático como condición de lanzamiento, X1 pasa a puerta obligatoria y su fecha depende de terceros. Lo mismo aplica a tracking automático de transportista: V1 permite tracking manual veraz.

Fuera del mínimo V1 propuesto: catálogo poblado de 20–30 productos, promociones complejas, marketplace público, compra automática multifuente, canales WhatsApp/SMS, XLSX nativo, motor de búsqueda externo y multi-divisa con conversión automática. Facturación electrónica no se implementa por inferencia en esta auditoría: su necesidad, integración o procedimiento compatible debe resolverse con responsable fiscal antes de autorizar venta real.

## 7. Criterio exacto de V1.0 terminada

V1 está terminada únicamente cuando **todas** estas puertas estén cumplidas y documentadas:

1. Release instalable desde Git limpio, lockfiles, migraciones aditivas, configuración validada, CI verde y staging equivalente al stack productivo. No tocar migraciones históricas ni recalcular snapshots.
2. Usuario invitado completa producto → carrito → disponibilidad válida → entrega cotizada → total final → pago verificado → pedido → preparación → despacho → tracking → entrega. Cuenta sigue opcional. Pasan también los dos modos de abastecimiento/entrega.
3. Se preservan moneda, importes, proveedor asignado y costos históricos privados; tarifas/costos/reglas cambiados no alteran un pedido pagado. No se cobran impuestos dos veces ni se presenta un subtotal como total.
4. Admin puede operar, conciliar y resolver excepciones sin editar SQL; roles y historial impiden saltos indebidos. Stock desconocido, pago incierto y envío parcial tienen tratamiento explícito.
5. Sandbox real de TiloPay aprobado y configuración productiva validada según procedimiento del proveedor; antes de abrir ventas, prueba controlada de pago/conciliación/reembolso autorizada por el propietario y compatible con sus condiciones. Un simulador interno no satisface esta puerta.
6. Emails reales autenticados, recuperaciones seguras, historial del cliente y tracking probado. Reintentos no duplican notificaciones ni cambios financieros.
7. Tarifas/cobertura/métodos, política de disponibilidad, impuestos/comprobantes y políticas legales aprobados; no hay datos comerciales inventados. Si falta esa información, el código puede ser un candidato a release, pero no una tienda autorizada para operar.
8. Backup y restauración BD+imágenes+claves ensayados, alertas comprobadas, workers/scheduler supervisados, TLS y perfil de producción; rollback ensayado sin pérdida de registros financieros.
9. Suite de sección 9 verde, ninguna incidencia de severidad crítica/alta sin resolver y ningún caso de seguridad/integración obligatorio omitido. No se usa un número arbitrario de tests como sustituto de cobertura.
10. Presupuesto operativo propuesto aprobado: carga equivalente a 2× el pico esperado por negocio durante 30 minutos, p95 de rutas locales de catálogo/carrito/checkout ≤2 s y errores 5xx <1%; medir llamadas externas por separado según SLA acordado. Cero pedidos/cobros/efectos duplicados. Si no se define el pico esperado, esta prueba no se da por aprobada. Recuperación propuesta: RPO ≤15 min para BD y RTO ≤2 h, con imágenes/clave disponibles para el mismo punto de recuperación; confirmar viabilidad/costo en A.
11. SEO/indexación y contenido activados solo para el entorno de lanzamiento; áreas privadas siguen no indexables y no cacheables públicamente. Aceptación responsive en 320, 430, 768, 1366 y 1920 px, teclado, foco y lector de pantalla en el flujo de compra.
12. Reporte de release, runbooks, responsables y autorización explícita de apertura. El catálogo real puede cargarse después del cierre técnico; no se abre venta de productos ficticios para demostrar que se terminó.

## 8. Información externa a solicitar

| Responsable | Información necesaria | Momento |
| --- | --- | --- |
| Negocio | Moneda(s) efectivas V1, base fiscal de precios/costos, política de disponibilidad manual/TTL, cancelación, reembolso, sustitución de proveedor, reserva interna y atención de incidencias. | A/B/C |
| Eurocomp | Documentación oficial/versionada, credenciales por entorno, auth/renovación, límites/paginación, SKU, monedas/impuestos, semántica/frescura de stock, reservas, órdenes e idempotencia solo si existen; condiciones de entrega directa y comprobantes. | B para operación manual; X1 para API |
| Dataformas y CQ | Acuerdos de suministro, costos/monedas/stock verificados, condiciones de entrega y disponibilidad de documentación/API, sin asumir que la ofrecen. | B/F y eventual X1 |
| Logística | Orígenes, zonas excluidas, transportistas, tarifas reales/vigencias, peso real/volumétrico y divisor, dimensiones, seguro/recargos, SLA/horarios, retiro y evidencia de entrega, costo por tramo. | C/F |
| TiloPay | Documentación oficial del producto contratado, sandbox/credenciales, medios/monedas, límites, expiración, confirmación y autenticidad de eventos, retorno, consulta, idempotencia real, liquidación/conciliación, comisiones y reembolsos. | E |
| Fiscal/legal | Tratamiento de impuestos, facturación/comprobantes y responsable de emisión; términos, privacidad, conservación de PII, garantía, devoluciones y consentimiento aplicable. | A y antes de J |
| Email/infraestructura | Dominio/DNS, remitente/proveedor, autenticación de correo, límites/bounces; hosting, TLS, Redis/DB, almacenamiento, backups, RPO/RTO y contactos de soporte. | A/F/I |
| Propietario | Identidad final, autorización de apertura y pruebas financieras reales, pico esperado, objetivos de rendimiento y aceptación de operación manual mientras no exista API. | A/J |

Solicitar credenciales por un canal seguro y cargarlas en el gestor de secretos; no pegarlas en documentos, fixtures ni Git. Un dato pendiente se mantiene pendiente, no se reemplaza por un endpoint, costo o tarifa de ejemplo en producción.

## 9. Suite objetivo de V1

Conservar toda la suite existente y añadir escenarios por comportamiento, sin duplicar pruebas que solo reflejen métodos internos:

- **Catálogo:** publicación/ocultación transitiva, slugs únicos y redirecciones, fotos inválidas y archivadas, recursos públicos sin campos privados, filtros combinados y paginación estable; CSV con dry-run, duplicados, errores parciales y exclusión de publicación accidental. Excel-export/CSV protegido contra fórmulas cuando se genere archivo.
- **Proveedores/pricing:** herencia de dos niveles, override, costo null, moneda incompatible, revisión obsoleta, límites/redondeo, desactivación; cambios de costos sin publicación automática; offers múltiples sin duplicar producto; futuros adapters con contract tests contra fixtures de documentación oficial y sandbox, no inventadas como evidencia de integración.
- **Disponibilidad:** dato vencido/desconocido, decremento/asignación atómica, expiración/liberación, aprobación manual y concurrencia por última unidad; no confundir reserva interna con externa.
- **Entrega:** cobertura por distrito, bandas y fronteras, peso/volumen faltante, orígenes múltiples, modo directo/vía operación, gratuidad con exclusiones, retiro, reglas solapadas/vencidas y divisas; cambio de destino/cantidades invalida cotización.
- **Totales:** subtotal/descuento/envío/impuesto, inclusión fiscal, redondeo, límites y rechazo de importes inyectados; snapshot constante tras cambios de tarifas. Migración de pedidos legacy sin valores inventados.
- **Pagos:** éxito/rechazo/cancelación, firma o autenticidad según contrato, replay/desorden, webhook antes/después del retorno, timeout de cobro, payload inválido, moneda/monto erróneos, pago doble/tardío, conciliación y refund; caída entre persistencia/llamada/procesamiento.
- **Pedidos:** transiciones válidas/prohibidas, roles, historial/notas, idempotencia y locking, reintento con carrito nuevo, costos históricos, sustitución de proveedor, paquetes/tramos parciales, entrega completa y cancelación con obligación financiera independiente.
- **Cliente:** todo como invitado, registro opcional, sesión perdida, recuperación genérica sin enumeración, token vencido/reusado/robado, ownership e historial, vínculo de invitado verificado y direcciones que no cambian pedidos previos.
- **Notificaciones:** outbox atómico, entrega duplicada/reintento, plantillas sin costos internos, contenido correcto por idioma/estado, enlace seguro y fallos SMTP sin revertir pagos confirmados.
- **Seguridad:** CSRF en toda mutación web nueva; excepción mínima solo para callback realmente autenticado, autorización/IDOR por entidad, mass assignment, rate limits, XSS, uploads, PII en logs/errores, caché, historial y ausencia de secretos en artefactos.
- **Infraestructura:** MySQL real + Redis real con procesos paralelos, workers después del commit, scheduler/failed jobs y reintentos, restore, secretos, upgrade/rollback, rendimiento bajo carga y alarmas verificadas.
- **E2E reproducible:** ruta completa de invitado y registrado, directo y vía operación, pago rechazado/reintentado, stock agotado durante checkout, pago confirmado durante caída del navegador, reembolso y tracking parcial/completo. Un solo producto publicado puede cubrir flujo feliz; escenarios sintéticos adicionales solo en entorno aislado de pruebas.

Versionar los escenarios E2E en el repositorio y ejecutarlos en CI/staging. Las capturas manuales históricas y scripts ignorados de `.local` no sustituyen esa automatización.

## 10. Verificación de esta auditoría

Verificación ejecutada el 18 de septiembre de 2026:

| Comando / comprobación | Resultado |
| --- | --- |
| `php artisan test --log-junit ../.local/v1-audit-tests.xml` | **116 tests / 2678 assertions; 0 failures, 0 errors, 0 skipped.** Exit 0. Duración reportada: 166.08 s. |
| `npm run check` | **PASS**, ejecuta `vue-tsc --noEmit` y `vite build`; exit 0. Build: 649 módulos, 22.39 s. |
| Consulta agregada MySQL local | **NO VERIFICABLE:** conexión rechazada en 3307. No demuestra un fallo de la lógica cubierta por SQLite ni confirma disponibilidad de infraestructura. |
| `git diff --check` | Sin errores en el diff rastreado; el único archivo nuevo es este documento. |
| `git status --short` inicial | Sin cambios. |
| `git status --short` final | `?? foundation/docs/V1-ROADMAP.md` |

Logs temporales ignorados: `.local/v1-audit-tests.log`, `.local/v1-audit-tests.xml` y `.local/v1-audit-build.log`. Los artefactos del build están ignorados como ya estaba configurado. No se volvieron a ejecutar auditorías de dependencias ni checks de infraestructura con escritura: los resultados de fases anteriores no se presentan como vigentes en esta auditoría.

No se ejecutaron pruebas financieras, proveedores, envíos, restauración ni navegador en esta tarea. Tampoco se certifica conectividad MySQL/Redis productiva. No se agregaron dependencias ni se modificó código de aplicación.

Git inicial: limpio en `1adf939`. Git final: solo este documento nuevo. No commit ni push. La siguiente acción propuesta es revisar/aprobar V1-A y las decisiones comerciales; esta auditoría no inicia esa implementación.

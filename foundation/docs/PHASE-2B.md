# Fase 2B — Checkout invitado y creación de pedido

**Estado:** implementada y verificada, pendiente de revisión. No se inicia la siguiente fase.

**Fecha:** 17 de septiembre de 2026. Base revisada: commit `c41f0af`, con Fase 2A aprobada y árbol Git limpio al iniciar. Se revisaron arquitectura, migraciones, sesión/carrito, roles y auditoría antes de modificar código. Se continuó sobre esa base sin reconstruir el carrito.

## Resultado

Flujo disponible: **producto → carrito → checkout → contacto y dirección → revisión → confirmar → pedido recibido con número público**.

El CTA real «Continuar con la compra» aparece en `/cart` cuando hay productos y ninguna línea bloqueada. El checkout es una sola página organizada en Contacto, Entrega y Revisión. La cuenta y la contraseña no son requisitos. El pedido se crea pendiente de pago; no se cobra, no se reserva inventario y no se solicita ningún despacho.

Se conserva la identidad azul/navy/blanco. En escritorio el resumen acompaña al formulario; en móvil hay un resumen compacto fijo con acceso al detalle. Se actualizaron los mensajes antiguos que indicaban que el checkout no existía, manteniendo explícito que el catálogo local contiene demostraciones y no se realizan cobros.

## Arquitectura

| Componente | Responsabilidad |
| --- | --- |
| `CartStore` / `SessionCartStore` | Persistencia de 2A conservada: sesión Redis, sin tabla nueva de carrito. |
| `CheckoutService` | Reconstruye selección, calcula precios, firma la revisión, crea pedido transaccional e implementa idempotencia. |
| `ConfirmCheckoutRequest` | Validación, normalización limitada y lista de campos permitidos. |
| `CostaRicaTerritories` | Catálogo local versionado y validación de provincia → cantón → distrito. |
| `CheckoutController` | Checkout público y confirmación limitada a la sesión propietaria. |
| `Order`, `OrderItem`, `OrderAddress` | Persistencia y proyección explícita de snapshots. |
| `OrderStatus` | Estado inicial tipado `pending_payment`. |
| `OrderAdminController` | Listado paginado y detalle de solo lectura para operaciones. |
| `PrivateCommerce` | Respuestas privadas, no-store, noindex e historial Inertia cifrado. |
| `OrderSummary.vue` / `OrderDetails.vue` | Presentación de importes y snapshots sin recalcular precios en Vue. |

No se añadieron dependencias de aplicación ni APIs de terceros. La fuente territorial se descargó durante el desarrollo; el funcionamiento de la tienda no depende de ella en línea.

## Migración y modelo de datos

Migración aditiva: `2026_09_17_000001_create_orders.php`, aplicada al MySQL local mediante `php artisan migrate --force`. Las pruebas automatizadas también ejecutan las migraciones en SQLite aislado.

| Tabla | Contenido |
| --- | --- |
| `orders` | UUID interno, número público único, `user_id` nullable, estado, comprador, moneda, subtotal/total en unidades menores, marcas temporales y hashes internos para idempotencia y autorización por sesión. |
| `order_items` | Referencia nullable al producto y snapshot de nombre, SKU, condición demo, cantidad, precio unitario, subtotal y moneda. |
| `order_addresses` | Una dirección por pedido: país CR, códigos y nombres de provincia/cantón/distrito, versión territorial, señas y texto adicional opcional. |
| `order_status_history` | Estado anterior nullable, estado inicial, actor nullable y fecha; asociado al UUID del pedido. |

Las referencias a usuario y producto usan `nullOnDelete`: el pedido conserva sus datos históricos sin depender de la permanencia de esas entidades. Las relaciones desde los componentes del pedido hacia el pedido restringen su eliminación. No hay rutas para editar o borrar los snapshots ni para modificar estados.

### Snapshots y dinero

- Se consultan los precios actuales al entrar al checkout y nuevamente al confirmar.
- El pedido conserva el nombre, SKU e importes confirmados aunque cambie el catálogo después.
- La dirección conserva códigos y nombres oficiales resueltos por el servidor, además del texto exacto normalizado por recorte exterior. No depende de futuras direcciones guardadas del cliente.
- Un pedido tiene una sola moneda: CRC o USD. No se convierten monedas.
- Los importes se almacenan como enteros en unidades monetarias menores. El backend calcula cantidad × precio y suma los subtotales.
- `subtotal_minor` y `total_minor` representan exclusivamente los productos en esta fase. La interfaz señala expresamente que transporte y cargos adicionales no están calculados. No se registra un transporte gratuito ficticio ni se promete un total final con servicios todavía indefinidos.
- Continúan los límites técnicos de 99 unidades por línea y 50 productos distintos; no representan stock.

## Dirección territorial

Se incorporó `database/data/cr-territories-2026.json`: **7 provincias, 84 cantones y 494 distritos**, con códigos jerárquicos oficiales como strings. Los selectores dependen del nivel superior y el servidor rechaza combinaciones que no existan en ese catálogo.

La fuente principal es la [División Territorial Administrativa 2026 del Registro Nacional / IGN](https://www.snitcr.go.cr/pdfs/ign_repositorio/DTA-TABLA%20POR%20PROVINCIA-CANT%C3%93N-DISTRITO%202026.pdf). Su tabla de áreas omite la fila final `70605`, pese a declarar 494 distritos. Duacarí se contrastó e incorporó usando fuentes oficiales adicionales; la procedencia y la corrección están detalladas en [database/data/README.md](../database/data/README.md).

No se aceptan nombres territoriales libres enviados por el navegador. La versión `IGN-2026` queda guardada en el snapshot. El catálogo administrativo no representa cobertura de entrega ni calcula transporte.

## Comprador invitado y autenticado

- El invitado proporciona nombre, apellidos, correo, teléfono, dirección y, opcionalmente, información adicional. No se crea una cuenta ni una contraseña.
- Si existe una sesión autenticada con rol `customer`, el servidor asocia su `user_id`; no acepta ese campo desde el cliente. El correo de la propia cuenta se ofrece como valor editable inicial.
- No se intenta dividir automáticamente el nombre completo de la cuenta en nombre/apellidos, ni se inventan teléfono o dirección.
- `admin` y `operator` pueden recorrer el checkout, pero no se les asigna propiedad como clientes: sus pedidos quedan sin `user_id`. El actor autenticado sí queda en la auditoría.
- No se exige verificación de correo para crear el pedido. Sí se conserva la verificación exigida por el panel administrativo existente.
- La coincidencia de correos nunca vincula un pedido invitado a una cuenta. No se implementa recuperación ni vinculación posterior de pedidos.
- El inicio de sesión conserva carrito y contexto de checkout de la sesión. Cerrar sesión elimina ese acceso y limpia las claves de historial Inertia.

### Validación

Validación backend de presencia, tipo y longitud; email con sintaxis válida; teléfono costarricense de ocho dígitos y prefijo +506. Se retiran espacios, paréntesis y guiones del teléfono y se añade +506 cuando se proporciona el número local. Esto valida formato, no titularidad ni existencia de la línea.

Los nombres y direcciones conservan acentos, mayúsculas, apóstrofos y saltos de línea; solo se recortan extremos. Se exige al menos una letra en nombres. La dirección admite hasta 1000 caracteres y las notas hasta 500. Campos desconocidos, importes, moneda, estado, usuario, número y líneas proporcionados por el cliente se rechazan.

## Revisión antes de confirmar

El servidor conserva en sesión un UUID de revisión y un HMAC de la selección comercial: revisión del carrito, productos, nombres, SKU, cantidades, precios, moneda y total. La sesión no conserva un borrador de contacto ni dirección.

Al confirmar, el servicio reconstruye el carrito y compara ese resumen con la revisión que vio el cliente. Un cambio de precio, nombre, SKU, cantidad o contenido exige una nueva revisión. Un producto oculto, archivado, con marca/categoría no publicada o moneda incompatible impide crear el pedido. El error conserva el formulario en la navegación Inertia y el carrito; la siguiente respuesta muestra el resumen actualizado para una nueva confirmación explícita.

Dentro de la transacción se bloquean productos, categorías y marcas mientras se decide la comercialización y se crean los snapshots. Se utilizan las filas actuales obtenidas bajo bloqueo, evitando depender de una lectura anterior de MySQL con aislamiento repeatable-read.

## Transacción, concurrencia e idempotencia

1. El identificador de confirmación es emitido por el servidor y vinculado a la revisión de la sesión.
2. Su SHA-256 queda en `orders.checkout_key` con restricción única. Un HMAC del contenido validado permite distinguir repetición idéntica de reutilización con datos distintos.
3. Una repetición idéntica desde la sesión autorizada devuelve el mismo pedido. Reutilizar el identificador con otro comprador o dirección produce un error; no modifica el pedido original.
4. Pedido, líneas, dirección, historial inicial y auditoría se crean en una única transacción. Si falla, todo se revierte y el carrito sigue intacto.
5. Solo después del commit se vacía el carrito, y únicamente si su revisión aún corresponde a la usada para crear el pedido. Repetir una confirmación antigua no vacía un carrito nuevo.
6. El bloqueo de sesión Redis de 2A sigue activo —60 segundos de duración, 10 de espera— y serializa las solicitudes de una misma sesión. La transacción permite hasta tres intentos ante interbloqueos reconocidos por Laravel.
7. La redirección POST → GET evita que recargar la confirmación vuelva a enviar el pedido. La protección no depende del botón deshabilitado: existe también en servidor y base de datos.

Redis y MySQL no comparten una transacción distribuida. Si el pedido se confirmó en MySQL pero falla el guardado de sesión o se pierde la respuesta, el reintento con el mismo contexto recupera el pedido existente y completa la limpieza del carrito. Una pérdida total de la sesión requiere un mecanismo futuro de recuperación; no se concede acceso basándose en el correo o el número.

## Estado y número público

Estado implementado: **`OrderStatus::PendingPayment` → `pending_payment`**, mostrado como «Pendiente de pago». La única transición actual es creación → pendiente de pago; queda registrada en `order_status_history`.

Máquina mínima prevista para fases futuras: pendiente de pago → pagado mediante confirmación verificable de pago, o pendiente de pago → cancelado mediante una política autorizada. Estados posteriores de preparación y entrega dependen de decisiones e integraciones futuras. **Ninguna de esas transiciones ni endpoints están implementados en 2B.**

El UUID interno es independiente del número público `TC-` seguido de 20 caracteres hexadecimales aleatorios (80 bits), respaldado por un índice único. No revela un ID incremental y sirve como referencia para soporte. No es una contraseña ni un mecanismo de autorización.

## Privacidad y seguridad

- La confirmación requiere que el HMAC del secreto aleatorio conservado en la sesión coincida con el del pedido. El secreto nunca se envía en props o URL. Otra sesión recibe 404 aunque conozca el número o el identificador de confirmación.
- El acceso inmediato dura lo que la sesión de 2A, configurada localmente en 120 minutos de inactividad. No se creó un portal permanente de seguimiento ni acceso por correo.
- Checkout, confirmaciones y pedidos administrativos incluyen `Cache-Control: private, no-store`, `X-Robots-Tag: noindex, nofollow` y metadatos noindex. Se mantienen estas cabeceras también en errores de esas rutas.
- Las páginas de comercio privado usan historial Inertia cifrado. El logout limpia ese historial y revoca el contexto de sesión. Esto no impide que un usuario conserve una captura de información que ya ha visto.
- PII del formulario y el token se excluyen del flash de entradas fallidas. El formulario no utiliza una clave de persistencia de `useForm` ni almacenamiento local para datos del comprador.
- La proyección pública del pedido enumera número, fecha, comprador, estado, moneda, subtotal/total, productos y dirección. No incluye UUID del pedido, claves de producto, hashes de idempotencia, secreto, actor, historial administrativo, costos, márgenes ni proveedores. Se conserva el contrato previo de identidad de la propia cuenta autenticada en props compartidas.
- CSRF/origen del framework permanece activo. La confirmación tiene un límite de 20 solicitudes por minuto conforme al middleware de throttling. La cookie conserva las protecciones existentes de sesión.
- Errores SQL en estas rutas se registran con mensaje genérico y SQLSTATE, sin consulta ni bindings personales, y producen una respuesta 503 neutral sin datos internos. La prueba simula expresamente una excepción con PII.
- Auditoría existente: `order.created` se escribe dentro de la transacción y `order.viewed` registra consultas de detalle administrativas. Solo se guardan actor, tipo/UUID de entidad y estado cuando aplica, sin contacto ni dirección en metadata.

Los datos del pedido persisten en MySQL; no se introdujo cifrado por campo. Para producción siguen pendientes las políticas de retención, acceso a respaldos, cifrado de infraestructura y recuperación de sesión. El entorno local utiliza datos sintéticos para estas comprobaciones.

## Administración y rutas

| Método | Ruta | Acceso |
| --- | --- | --- |
| GET | `/checkout` | Público; exige carrito válido. |
| POST | `/checkout` | Público, sesión/CSRF, validación e idempotencia. |
| GET | `/checkout/confirmation/{number}` | Solo contexto de sesión que creó el pedido. |
| GET | `/admin/orders` | `admin` / `operator`, autenticados y verificados, permiso `access-operations`. |
| GET | `/admin/orders/{number}` | Mismos permisos; detalle de solo lectura y auditoría. |

Listado administrativo paginado con número, fecha, cliente, total, moneda, estado y enlace al detalle. Navegación «Pedidos» añadida al panel existente. PATCH/DELETE no están disponibles; ni siquiera un administrador puede cambiar estados o editar el contenido histórico desde estas rutas.

## Pruebas automatizadas y calidad

**Suite completa: 96 pruebas correctas y 2329 aserciones.** Se conservan los 70 casos previos. Una expectativa de 0B que exigía 404 en `/checkout` se actualizó al comportamiento autorizado: carrito vacío → `/cart`; se conserva la comprobación de que `/products` no existe. No se deshabilitaron pruebas para hacer pasar la suite.

Se añadieron **26 pruebas** en `tests/Feature/CheckoutTest.php`. Incluyen acceso invitado, cuenta opcional y no verificada, validación de comprador/email/teléfono, normalización, las 494 filas territoriales y combinaciones inválidas, carrito vacío/bloqueado, snapshots, cambio de precio/nombre/SKU/cantidad/moneda/publicación, autoridad backend, manipulación de campos, ausencia de `user_id` invitado, asociación segura a customer, números únicos, estado e historial inicial, vaciado al éxito, rollback con conservación del carrito, confirmación repetida, reutilización conflictiva, aislamiento entre sesiones, privacidad de props, no-store, auditoría sin PII, permisos, logout y respuesta segura a errores SQL.

| Verificación desde `foundation/` | Resultado |
| --- | --- |
| `php artisan test` | PASS — 96 pruebas / 2329 aserciones, suite completa. |
| `npm run check` | PASS — TypeScript y compilación Vite. |
| `php vendor/bin/pint --test` | PASS. |
| `composer validate --strict` | PASS. |
| `composer audit` | Sin avisos de vulnerabilidad. |
| `composer check-platform-reqs` | Requisitos satisfechos. |
| `npm audit` | 0 vulnerabilidades. |
| `php artisan foundation:check` | PASS — MySQL, Redis caché y cola. |
| `git diff --check` | Sin errores de whitespace. |

Composer global sigue emitiendo el aviso de deprecación de `$http_response_header` con PHP 8.5 ya observado en 2A; las verificaciones terminan correctamente.

## Navegador y evidencia visual

Chrome local mediante Playwright, sobre Laravel + MySQL + Redis reales. La conexión al navegador integrado falló con `missing field sandboxPolicy`; se utilizó la alternativa local, sin incorporar Playwright a las dependencias de aplicación.

En cada viewport se verificaron: rechazo de checkout vacío, agregado desde producto, CTA del carrito, checkout sin login, validación y foco en primer error, labels/errores asociados, selectores dependientes, introducción de contacto y dirección, resumen, confirmación, estado pendiente, recarga conservando el mismo pedido, carrito vacío tras éxito y acceso 404 desde otro contexto. Sin desbordamiento horizontal ni errores JavaScript.

| Viewport | Capturas |
| --- | --- |
| 320 × 740 | [Checkout](screenshots/phase-2b-checkout-small.png) · [Confirmación](screenshots/phase-2b-confirmation-small.png) |
| 430 × 932 | [Checkout](screenshots/phase-2b-checkout-large.png) · [Confirmación](screenshots/phase-2b-confirmation-large.png) |
| 768 × 1024 | [Checkout](screenshots/phase-2b-checkout-tablet.png) · [Confirmación](screenshots/phase-2b-confirmation-tablet.png) |
| 1366 × 768 | [Checkout](screenshots/phase-2b-checkout-laptop.png) · [Confirmación](screenshots/phase-2b-confirmation-laptop.png) |
| 1920 × 1080 | [Checkout](screenshots/phase-2b-checkout-desktop.png) · [Confirmación](screenshots/phase-2b-confirmation-desktop.png) |

Verificaciones adicionales en navegador:

- Carrito invitado conservado tras login, correo de cuenta prellenado, creación autenticada y acceso revocado al cerrar sesión.
- Dos solicitudes de confirmación lanzadas en paralelo devolvieron el mismo número público; una solicitud sin token CSRF ni origen confiable devolvió 419.
- [Listado administrativo](screenshots/phase-2b-admin-orders.png) y [detalle](screenshots/phase-2b-admin-detail.png), con cabecera no-store.
- Revisión visual de capturas de móvil, confirmación de escritorio y detalle administrativo. Los errores de campo se limpian al corregir su valor.

Las cuentas temporales de pruebas y su archivo local de credenciales se eliminaron al terminar. Se conservan en el MySQL local pedidos de prueba claramente identificados con contactos `example.test`, señas ficticias y productos DEMO; no se envió correo ni se solicitó ningún cobro o entrega. Las capturas contienen exclusivamente esos datos sintéticos.

Estas comprobaciones no sustituyen una prueba de carga con varios workers MySQL/Redis ni una certificación de accesibilidad con lectores de pantalla. El servidor de desarrollo puede serializar las solicitudes HTTP; las pruebas verifican además las barreras de revisión/idempotencia y la restricción única en base de datos.

## Documentación y decisiones pendientes

- Se corrigió el nombre del proveedor a **Eurocomp** en la documentación existente y se renombró la decisión a [EUROCOMP-ARCHITECTURE.md](EUROCOMP-ARCHITECTURE.md), actualizando sus enlaces. No hubo integración por esta corrección.
- Los bloqueos actuales incluyen todas las categorías para validar ancestros de forma coherente. Es una opción conservadora para el catálogo actual; antes de escalar conviene optimizar su alcance y medir contención/interbloqueos con varios workers.
- Definir cargos finales, políticas comerciales, cancelación, pago verificable y comunicaciones al comprador antes de apertura comercial. No se inventaron instrucciones bancarias, métodos ni fechas de entrega.
- Definir retención/anonimización de PII, recuperación de confirmaciones y vinculación verificada de pedidos a futuras cuentas. No existe vinculación por coincidencia de email.
- Mantener actualizado el catálogo territorial oficial mediante cambios revisados y conservar siempre los snapshots existentes.
- La pérdida total de sesión no permite recuperar públicamente un pedido. El número sirve para soporte, pero el futuro procedimiento deberá verificar titularidad por separado.

**Fuera de alcance y no implementado:** pasarelas de pago, TiloPay/Stripe/PayPal, SINPE/transferencias/tarjetas, facturación electrónica, transporte real, etiquetas, logística, APIs de Eurocomp/Dataformas u otros proveedores, reservas, órdenes a distribuidores y promociones complejas.

**Cierre:** entregar 2B para revisión y detenerse; no iniciar la fase siguiente sin autorización.

# Fase 2A — Carrito de compras

**Estado:** implementada y verificada; pendiente de revisión del propietario. Fase 2B no iniciada.

**Fecha:** 17 de septiembre de 2026. Aplicación: `foundation/`. URL local: <http://127.0.0.1:8086/cart>.

## Resultado y alcance

Carrito público que permite agregar, incrementar, disminuir, editar cantidades, eliminar líneas y vaciar. Conserva su contenido al navegar y recargar durante la sesión. La cabecera del storefront muestra un contador de unidades. La cuenta es opcional y el inicio de sesión conserva el carrito existente.

Se mantuvo la implementación de los 19 archivos encontrados al retomar la fase. La revisión detectó y corrigió una comilla faltante en el mensaje de moneda incompatible, que impedía ejecutar `CartService`. También se añadió `Cache-Control: private, no-store` al carrito y su comprobación. No se reconstruyó el trabajo desde cero.

No se implementaron checkout, pedidos, pagos, logística ni proveedores. La integración Eurocomp continúa exclusivamente como decisión arquitectónica documentada; una capacidad no documentada o no verificada sigue considerándose no disponible.

## Arquitectura y persistencia

| Elemento | Responsabilidad |
| --- | --- |
| `app/Contracts/CartStore.php` | Contrato de lectura/escritura para separar las reglas de la persistencia. |
| `app/Services/SessionCartStore.php` | Guarda el carrito en la sesión bajo `shopping_cart`. |
| `app/Services/CartService.php` | Reglas, límites, moneda, proyección pública, cálculos y protección de operaciones. |
| `app/Http/Controllers/CartController.php` | Valida entradas permitidas y responde mediante Inertia/redirecciones. |
| `resources/js/composables/useCartActions.ts` | Envía operaciones con revisión y UUID; gestiona espera y errores. |
| `resources/js/components/storefront/AddToCart.vue` y `CartLine.vue` | Agregado desde la ficha y controles de cada línea. |
| `resources/js/pages/cart/Index.vue` | Estado vacío, contenido, mensajes y resumen provisional. |
| `resources/css/cart.css` | Presentación responsive utilizando los tokens existentes. |

El entorno local utiliza sesiones Redis. El carrito contiene únicamente referencias internas de producto y cantidades, identificadores UUID de línea, moneda, revisión y un registro acotado de huellas de operaciones. No guarda copias de precios, modelos completos ni datos personales propios del carrito. Los precios se consultan en el catálogo local al construir la respuesta.

No se crearon tablas ni migraciones de carrito. La duración local de la sesión es de 120 minutos de inactividad; no equivale a un carrito permanente. Redis almacena la sesión del lado del servidor; su contenido no está cifrado por la configuración local de sesiones. No se utiliza `localStorage` como autoridad ni persistencia del carrito Laravel.

### Invitado y usuario autenticado

- Navegación y operaciones completas sin autenticación ni verificación de correo.
- El inicio de sesión opcional regenera el identificador de sesión conservando el carrito.
- Los roles `customer`, `operator` y `admin` pueden usar el mismo flujo de tienda.
- Cerrar sesión invalida la sesión y elimina su carrito, como medida de privacidad.
- Otra sesión o navegador tiene un carrito independiente, incluso si corresponde a la misma cuenta. No existe fusión entre dispositivos ni persistencia asociada al usuario en esta fase.

## Rutas

Todas usan el middleware web de sesión y protección contra falsificación de solicitudes. Ninguna requiere cuenta.

| Método | Ruta | Nombre | Acción |
| --- | --- | --- | --- |
| GET | `/cart` | `cart.show` | Ver carrito. |
| POST | `/cart/items` | `cart.add` | Agregar o sumar unidades de un producto. |
| PATCH | `/cart/items/{line}` | `cart.update` | Establecer cantidad absoluta. |
| DELETE | `/cart/items/{line}` | `cart.remove` | Eliminar línea. |
| DELETE | `/cart` | `cart.clear` | Vaciar. |

`{line}` debe ser un UUID existente en la sesión actual. Un identificador de otra sesión no permite modificar su contenido y responde 404.

## Precios, moneda y comercialización

- El servidor obtiene publicación, moneda y precio del catálogo local. El cliente solamente formatea los importes calculados por el backend.
- Los cálculos utilizan enteros en unidades monetarias menores. El resumen es provisional: no calcula transporte, impuestos adicionales ni cargos de un checkout.
- Cada carrito admite una sola moneda. El primer producto la establece; agregar otra moneda se rechaza. Vaciar o eliminar la última línea libera esa elección. No hay conversión de divisas.
- Máximo técnico de **99 unidades por línea** y **50 productos distintos**. Agregar de nuevo un producto suma en su misma línea y respeta el límite acumulado. Estos límites no representan existencias.
- Cantidades vacías, cero, negativas, fraccionarias, no numéricas, arrays y cantidades superiores al límite se rechazan en el servidor.
- Productos no publicados, archivados o excluidos por la visibilidad de marca/categoría no se pueden agregar.
- Si un producto deja de ser visible después de agregarlo, permanece como línea bloqueada con el texto genérico «Producto no disponible», sin revelar su nombre actual, imagen, enlace ni precio. Puede eliminarse, pero no cambiar su cantidad.
- Si cambia a otra moneda, su línea queda bloqueada y no aporta importes al resumen. Puede retirarse; para iniciar un carrito en otra moneda debe vaciarse el anterior.
- Las líneas bloqueadas quedan fuera del subtotal y hacen que el total sea `null`, con una advertencia visible. No se presentan como productos comprables.
- Si cambia un precio dentro de la misma moneda, el siguiente cálculo utiliza el precio actual. El carrito no constituye una cotización ni reserva.

La disponibilidad de esta fase es editorial, basada en el catálogo local. No hay consulta ni reserva de inventario de Eurocomp.

## Seguridad y datos públicos

El controlador acepta exclusivamente los campos correspondientes a cada acción: `mutation_id`, `revision`, y cuando corresponde `product_slug` o `quantity`; permite también los campos técnicos `_token` y `_method`. Campos adicionales de precio, moneda, publicación, propietario u otros datos se rechazan.

Las props de carrito se construyen mediante una lista explícita. Cada línea expone únicamente `id` (UUID de línea), `quantity`, `available`, `reason`, `name`, `slug`, `is_demo`, `image` (`url`, `alt`), `unit_price_minor`, `subtotal_minor` y `currency`. El resumen expone líneas, moneda, unidades, subtotal, total, indicador de líneas bloqueadas, revisión y límites. La cabecera recibe unidades y revisión.

No se serializan modelos completos, claves internas de producto, costos, márgenes, credenciales, respuestas de integración, identificadores de sesión ni el registro de operaciones. La prueba de props verifica esa lista permitida.

Las mutaciones mantienen la protección CSRF/origen del framework. Las cookies de sesión tienen `HttpOnly` y `SameSite=Lax` por defecto; el despliegue HTTPS debe conservar su configuración segura de producción. `/cart` usa `private, no-store` y metadatos `noindex, nofollow`.

## Concurrencia e idempotencia

1. Cada operación lleva una revisión del carrito y un UUID de operación.
2. El servidor compara la revisión antes de aplicar una operación nueva. Una pestaña obsoleta recibe un error y no sobrescribe cambios recientes.
3. Se conserva una huella SHA-256 de acción, línea y datos validados para las últimas 100 operaciones aplicadas. Repetir el mismo identificador con los mismos datos no vuelve a aplicar la mutación. Reutilizarlo con datos distintos se rechaza.
4. Cada cambio aplicado incrementa la revisión. Vaciar conserva revisión e historial, para que repetir un vaciado antiguo no elimine productos agregados posteriormente.
5. Una repetición cuyo identificador salió del historial sigue siendo rechazada si conserva su revisión antigua. No se promete deduplicación indefinida de identificadores fuera de esa ventana.
6. El bloqueo de sesión serializa lecturas y escrituras, evitando que una respuesta de navegación guarde una versión antigua de la sesión sobre una mutación reciente. Usa el almacén de bloqueo configurado o el de caché por defecto: Redis en el entorno local. Duración de bloqueo: 60 segundos; espera máxima: 10 segundos.

La interfaz evita envíos mientras una operación está pendiente, pero la protección decisiva está en el servidor. El contador incluye todas las unidades guardadas, también las de líneas bloqueadas; estas se explican al abrir el carrito. Otras pestañas actualizan su contador en la siguiente solicitud, sin notificaciones en tiempo real.

## Interfaz, responsive y accesibilidad

Se conserva la paleta de tokens azul/navy/blanco de 1B. Las líneas se presentan como tarjetas adaptables, con controles etiquetados, foco visible, mensajes de estado y error, enlace al catálogo y estado vacío propio. Tras eliminar o vaciar se devuelve el foco al encabezado; los errores reciben foco para facilitar su localización. No hay botón que simule un checkout disponible.

Se realizaron pruebas con Chrome mediante Playwright local, usando el servidor Laravel y Redis reales. El navegador integrado no pudo inicializarse por el error de herramienta `missing field sandboxPolicy`; se utilizó Chrome como alternativa sin añadir dependencias al proyecto de aplicación.

| Viewport | Verificación | Evidencia |
| --- | --- | --- |
| 320 × 740 | Flujo completo, estado vacío, sin desbordamiento horizontal | [Carrito](screenshots/phase-2a-cart-small.png), [vacío](screenshots/phase-2a-empty-small.png), [agregado](screenshots/phase-2a-add-small.png) |
| 430 × 932 | Flujo completo, estado vacío, sin desbordamiento horizontal | [Carrito](screenshots/phase-2a-cart-large.png), [vacío](screenshots/phase-2a-empty-large.png) |
| 768 × 1024 | Flujo completo, estado vacío, sin desbordamiento horizontal | [Carrito](screenshots/phase-2a-cart-tablet.png), [vacío](screenshots/phase-2a-empty-tablet.png) |
| 1366 × 768 | Flujo completo, estado vacío, sin desbordamiento horizontal | [Carrito](screenshots/phase-2a-cart-laptop.png), [vacío](screenshots/phase-2a-empty-laptop.png) |
| 1920 × 1080 | Flujo completo, estado vacío, sin desbordamiento horizontal | [Carrito](screenshots/phase-2a-cart-desktop.png), [vacío](screenshots/phase-2a-empty-desktop.png) |

En cada tamaño: agregado de dos unidades, contador, incremento a tres, disminución a dos, edición a cuatro, persistencia al recargar, rechazo del cero desde el control, eliminación, nuevo agregado y vaciado. Se comprobó el cálculo de cuatro unidades del producto de demostración: 6 000 000 unidades monetarias menores, equivalentes a ₡60 000. Se revisaron capturas de escritorio y móvil, navegación por teclado y foco del enlace de salto. No se detectaron errores de JavaScript.

Adicionalmente, con contextos de navegador independientes y Redis: aislamiento, línea ajena con 404, solicitudes repetidas sin doble agregado, dos cambios competidores con una aceptación y un rechazo, entrada de precio/cantidad manipulada rechazada, solicitud sin token ni origen confiable con 419, inicio de sesión conservando el carrito y cierre de sesión eliminándolo. La cuenta temporal y su archivo de credenciales se eliminaron al terminar; no se modificaron los productos del catálogo para estas pruebas visuales.

## Pruebas automatizadas

Se añadieron **18 pruebas** en `tests/Feature/CartTest.php`, con **330 aserciones**, sobre las 52 pruebas y 693 aserciones existentes. **Resultado final de toda la suite: 70 pruebas correctas, 1023 aserciones.**

Cobertura añadida:

1. Carrito vacío público, sin creación de cuenta.
2. Agregado repetido en una línea, contador y persistencia entre páginas.
3. Edición, disminución, eliminación y vaciado.
4. Recálculo de precios actuales y totales enteros desde backend.
5. Rechazo de manipulación de precios, moneda, estado y campos adicionales.
6. Rechazo de productos no publicados, archivados o con taxonomía oculta.
7. Cantidades inválidas al agregar y editar.
8. Límite acumulado de 99 y máximo de 50 productos.
9. Aislamiento de sesiones y líneas ajenas.
10. Moneda única y restablecimiento al vaciar.
11. Cambio posterior de moneda y bloqueo del total.
12. Producto ocultado después del agregado y protección de sus metadatos.
13. Idempotencia del agregado y rechazo de UUID reutilizado con otros datos.
14. Revisión obsoleta y repetición de un vaciado antiguo.
15. Inicio de sesión conservando carrito y cierre eliminándolo.
16. Acceso de los tres roles sin exigir verificación de correo.
17. Lista explícita de props públicas.
18. Protección CSRF y validación de metadatos de operación.

La comprobación de respuesta privada sin caché está incluida en las pruebas del carrito.

### Verificaciones ejecutadas

| Comando, desde `foundation/` | Resultado |
| --- | --- |
| `php artisan test` | PASS — suite completa, 70 pruebas / 1023 aserciones. |
| `npm run check` | PASS — comprobación TypeScript y compilación Vite. |
| `php vendor/bin/pint --test` | PASS. |
| `composer validate --strict` | PASS. |
| `composer audit` | Sin avisos de vulnerabilidad. |
| `composer check-platform-reqs` | Todos los requisitos satisfechos. |
| `npm audit` | 0 vulnerabilidades. |
| `php artisan foundation:check` | PASS — MySQL, caché Redis y trabajo en cola Redis. |

La instalación global de Composer emitió un aviso de deprecación de PHP 8.5 sobre `$http_response_header`; no provocó fallos en las verificaciones. Su actualización corresponde al entorno de herramientas.

## Límites y decisiones pendientes

- Validar bloqueo y tiempos de espera con varios workers y carga representativa antes de producción. Las solicitudes competidoras se probaron contra el servidor de desarrollo; eso no sustituye una prueba de carga multiproceso. Las operaciones deben terminar antes de vencer el bloqueo de 60 segundos.
- Confirmar política comercial futura de permanencia, recuperación y fusión de carritos entre sesiones/dispositivos. Esta fase conserva exclusivamente la sesión y su expiración.
- Las comprobaciones de accesibilidad cubren teclado, etiquetas, foco, mensajes y presentación responsive; no constituyen una certificación WCAG ni una evaluación completa con lectores de pantalla.
- El stock real, reservas, cálculo final de cargos y futura compra como invitado pertenecen a fases posteriores. Los límites técnicos actuales no garantizan disponibilidad.
- No hay integración externa ni costos de proveedor en las props. Cualquier incorporación de proveedores depende de documentación oficial y del alcance autorizado posteriormente.
- No se pudo generar un diff Git: ni la carpeta raíz ni `foundation/` contienen `.git`. Se revisaron los archivos existentes directamente; no se creó un repositorio ni se revirtieron archivos. El reporte describe el estado comprobado, no una comparación contra un commit inexistente.

**Cierre:** Fase 2A lista para revisión. Detenerse aquí; Fase 2B permanece sin iniciar.

# Fase 1B — Storefront público

Estado: implementada para revisión. Fase 1A aprobada. No se inicia la siguiente fase.

## Resultado y acceso

- Portada: http://127.0.0.1:8086/
- Catálogo: http://127.0.0.1:8086/catalog
- Ficha de ejemplo: http://127.0.0.1:8086/catalog/demo-laptop-studio-14

La navegación completa es pública y utiliza exclusivamente el catálogo local. Se mantienen los 20 productos demo de 1A y sus imágenes; no se agregan productos reales ni se consulta a proveedores. El producto QA archivado de 1A permanece fuera del storefront.

## Páginas y componentes

| Página / componente | Función |
| --- | --- |
| `Home.vue` | Hero propio, categorías, selección destacada, novedades, ofertas con precio anterior, marcas, beneficios informativos y acceso al catálogo. Secciones de productos vacías se ocultan. |
| `catalog/Index.vue` | Búsqueda, filtros combinables, ordenamiento, resultados, paginación y estado vacío. |
| `catalog/Show.vue` | Galería, principal, miniaturas, marca, nombre, SKU, precios, descripción, especificaciones agrupadas, garantía y relacionados publicados de la misma categoría. |
| `StoreLayout.vue` | Navegación, buscador, menú móvil, acceso secundario a cuenta, salto al contenido y footer. |
| `ProductCard.vue` | Tarjeta reutilizable exclusivamente con datos públicos y enlace a detalle. |
| `ProductSection.vue` | Encabezado y grilla reutilizable para colecciones. |
| `StoreSeo.vue` | Metadata del navegador consistente entre navegación inicial y navegación Inertia. |
| `TechArtwork.vue` | Ilustración SVG conceptual propia del hero, identificada como demo. No es una fotografía de un producto comercial. |

No hay botones de compra simulados. La ficha reserva un área informativa para la futura operación comercial y explica que la compra aún no está habilitada. No se muestran cantidades, existencias ni promesas de entrega.

## Identidad y propuesta cromática

Nombre de trabajo: **TECH COMMERCE**. `config/storefront.php` centraliza nombre, marca textual, descripción general, tagline, región y locale. Se comparte como configuración pública; no contiene secretos. Cambiar el nombre no cambia rutas ni lógica comercial. Los layouts de cuenta también reciben esta identidad.

Referencia cromática indicada por el propietario: [CQ International](https://store.cqintl.com/). Se utiliza como inspiración general de azules, blanco y contraste oscuro; no se incorporan código, componentes, identidad, estructura ni recursos gráficos del sitio.

Todos los colores del storefront, incluidos los del SVG conceptual, se resuelven desde **`resources/css/storefront-tokens.css`**. La propuesta queda editable para revisión:

| Token | Valor | Aplicación |
| --- | --- | --- |
| `--color-primary` | `#1261D8` | Botones principales, enlaces y detalles |
| `--color-primary-hover` | `#0A48A5` | Estado hover |
| `--color-navy` | `#10233F` | Hero y footer |
| `--color-background` | `#FFFFFF` | Fondo principal y tarjetas |
| `--color-surface` | `#F4F7FB` | Superficies secundarias |
| `--color-surface-blue` | `#EAF2FD` | Avisos y secciones informativas |
| `--color-text` | `#202C3A` | Texto principal |
| `--color-muted` | `#586777` | Texto secundario |
| `--color-highlight-on-dark` | `#83B7FF` | Énfasis sobre navy |

No se necesita un acento de otro color. Navegación y fondo principal blancos, tarjetas limpias y amplias superficies claras; navy queda limitado a secciones de contraste. Sin capas de glassmorphism. Tipografía del sistema, sin descargar fuentes ni añadir dependencias frontend.

Las tarjetas y la galería priorizan el espacio para imágenes, con `object-fit: contain` y fondos claros. Se conservan las ilustraciones genéricas de 1A para cumplir el alcance de datos demo. **Las fotografías reales y autorizadas siguen pendientes**; no se presentan ilustraciones como fotos reales ni se utilizan imágenes de proveedores.

## Navegación pública y cuentas

Portada, búsqueda, categorías, marcas, filtros, paginación, ficha y relacionados funcionan sin autenticación. El menú móvil se abre con botón, anuncia su estado, se cierra con Escape y devuelve el foco al botón. “Ingresar” / “Mi cuenta” es acceso secundario; seleccionar un producto nunca redirige al registro.

Se conserva la [decisión de compra como invitado](GUEST-CHECKOUT-DECISION.md): será el flujo principal futuro. La cuenta `customer` es opcional. El pedido futuro podrá conservar los datos del comprador independientemente de un usuario, con asociación opcional y verificada. En 1B no se implementan pedidos ni captura de datos de invitados.

El footer contiene enlaces reales de catálogo y cuenta, región e información sobre la vista previa. No se inventan contactos, direcciones, políticas legales, medios de pago ni condiciones de entrega.

## Búsqueda, filtros y orden

`GET /catalog` acepta parámetros validados y conserva únicamente estos en enlaces de paginación:

| Parámetro | Comportamiento |
| --- | --- |
| `q` | Nombre o SKU, máximo 100 caracteres. `%` y `_` se buscan literalmente; consulta parametrizada. |
| `category` | Slug público; incluye descendientes publicados y excluye ramas con antecesores ocultos. |
| `brand` | Slug de marca publicada. |
| `currency` | CRC por defecto o USD. No se comparan precios entre monedas. |
| `min`, `max` | Importes con hasta dos decimales; conversión exacta a unidades menores para la consulta. Se valida mínimo ≤ máximo. |
| `editorial` | `demo` o `standard`; distingue marca de demostración. No representa stock. |
| `featured` | Solo productos destacados cuando vale 1. |
| `offers` | Solo productos cuyo precio anterior es mayor que el actual. |
| `sort` | `newest`, `featured`, `price_asc`, `price_desc`, `name_asc`, `name_desc`. |
| `page` | Página positiva, 12 productos por página. |

Orden estable con desempate por ID. Novedades usa fecha de publicación. Los filtros se combinan, aparecen en la URL y se pueden compartir o limpiar. Cambiar filtros reinicia la página. Categorías y marcas inexistentes/no públicas devuelven 404; entradas inválidas se rechazan. La validación de precios también tiene respuesta inmediata en el formulario y foco en el error, manteniendo al servidor como autoridad.

El catálogo solo incluye productos publicados con marca y jerarquía de categorías visibles. El detalle de un producto oculto devuelve 404; relacionados aplican la misma regla y excluyen el propio producto.

## SEO básico

- Título, descripción, canonical y Open Graph (`title`, `description`, `url`, `type`, `site_name`, `locale` e imagen principal cuando existe).
- Metadata incluida en el HTML inicial y actualizada con claves únicas durante la navegación Inertia. Cambiar miniatura no cambia la imagen principal de Open Graph.
- Fichas mediante slug; categoría mediante `?category=slug`, con título y descripción específicos cuando existe información.
- Canonical de catálogo conserva categoría, moneda no predeterminada y página; omite búsqueda, orden y filtros auxiliares.
- Todo el storefront continúa con `noindex, nofollow` porque es una vista previa con datos demo. La apertura a indexación requiere una decisión de lanzamiento.
- Metadata escapada; sin SSR completo, sitemap comercial, datos estructurados avanzados ni promesas de indexación.

## Datos públicos y separación del dominio

Se reutiliza `PublicProductResource` con lista explícita, nunca se entrega el modelo completo. `PublicProduct` es un tipo frontend independiente del tipo administrativo.

Campos expuestos: `name`, `sku`, `slug`, `short_description`, `description`, `warranty`, `specifications`, `price_minor`, `previous_price_minor`, `currency`, `featured`, `is_demo`, `meta_title`, `meta_description`, `category`, `brand`, `images`.

Categoría/marca del producto exponen solo nombre y slug. Imágenes: ID público para selección, URL controlada, alt, posición y principal; no ruta de almacenamiento. Las opciones de categorías exponen nombre, slug, descripción y slug del padre; las marcas, nombre y slug. Además se envían filtros, paginación, metadata e identidad pública.

La información de sesión de 0B continúa limitada al usuario autenticado; para visitantes es `null`. Las pruebas inyectan atributos y relaciones ficticias de proveedor únicamente en memoria y comprueban que no se serialicen. No se crean columnas, tablas ni capacidades de proveedores.

## Responsive, accesibilidad y rendimiento

- Diseño mobile-first; columnas adaptables y filtros desplegables bajo 900 px. En escritorio los filtros permanecen visibles a un lado del catálogo.
- Verificación en **320×740**, **430×932**, **768×1024**, **1366×768** y **1920×1080**: home, catálogo y ficha, sin desbordamiento horizontal.
- HTML semántico, un H1 por página, encabezados jerárquicos, etiquetas de formularios, alt, controles nativos, estados de foco, menú con `aria-expanded`, galería con `aria-pressed`, resultados anunciados y enlace para saltar al contenido.
- Navegación por teclado y retorno del foco al cerrar menú. Errores con `role=alert`; enfoque del área de resultados tras aplicar filtros.
- Contrastes medidos: texto blanco/botón azul **5.63:1**, texto principal/blanco **14.16:1**, secundario/blanco **5.80:1**, secundario/superficie **5.40:1**, azul claro/navy **7.63:1**. Son comprobaciones concretas, no una certificación integral WCAG.
- Imágenes con dimensiones y relación de aspecto reservadas; carga diferida en tarjetas y miniaturas. Principal de ficha priorizada. Se respeta reducción de movimiento. No hay carruseles automáticos ni librerías de animación.
- Listados limitados y paginados, relaciones precargadas. Las imágenes conservan la política privada de 1A para permitir revocar visibilidad; CDN, caché, variantes `srcset` y pruebas de carga siguen pendientes para producción.

## Pruebas y verificaciones

**52 pruebas PHP aprobadas, 693 aserciones.** Se mantienen las 38 pruebas/333 aserciones anteriores y se agregan 14 pruebas/360 aserciones en `tests/Feature/StorefrontTest.php`.

Cobertura nueva: home y vacío público; búsqueda nombre/SKU y comodines literales; categorías descendientes y ocultas; filtros combinados; separación de monedas; seis órdenes y desempate; paginación y URL; validaciones; detalle y relacionados; invisibilidad; metadata escapada y específica; lista pública de campos y cambio de identidad sin cambiar rutas.

Los tests de home anteriores conservan sus aserciones y ahora preparan la base de datos, necesaria porque la portada consulta productos.

Validaciones ejecutadas:

```text
php artisan test                       PASS — 52 / 693
npm run check                          PASS — TypeScript + Vite
php vendor/bin/pint --test              PASS
composer validate --strict              PASS
composer audit                         Sin avisos de vulnerabilidades
composer check-platform-reqs            PASS
npm audit                              0 vulnerabilidades
php artisan foundation:check            PASS — MySQL, caché y trabajo Redis
```

Composer emitió un aviso de deprecación de su herramienta global bajo PHP 8.5; los comandos finalizaron correctamente. No procede del código del storefront.

Chrome headless/Playwright, con contexto sin sesión, verificó los cinco tamaños, menú y Escape, búsqueda por teclado, filtros, orden, paginación preservada, estado vacío, rango inválido, galería, canonical/metadata únicos, salto al contenido y ausencia de errores JavaScript. El navegador integrado falló al inicializar; se utilizó Chrome local separado. La herramienta de navegador está aislada en `.local/browser-tools`, no en las dependencias frontend. Script de comprobación de esta sesión: `.local/storefront-browser.mjs` en la raíz del workspace. Los tests PHP utilizan SQLite; las comprobaciones de navegador utilizan MySQL local.

### Evidencia visual

Capturas de las tres páginas en los cinco tamaños guardadas en `docs/screenshots/phase-1b-*.png`. Se revisaron portada escritorio/móvil, catálogo laptop y ficha móvil. Las capturas de producto se toman tras cargar las imágenes visibles.

- [Portada escritorio](screenshots/phase-1b-home-desktop.png)
- [Portada completa laptop](screenshots/phase-1b-home-laptop.png)
- [Portada móvil pequeño](screenshots/phase-1b-home-small.png)
- [Catálogo laptop](screenshots/phase-1b-catalog-laptop.png)
- [Ficha completa móvil](screenshots/phase-1b-product-large.png)

## Decisiones pendientes y límite de fase

1. Revisar esta propuesta visual; elegir nombre y logo definitivos. Los tokens permiten ajustar la paleta sin recorrer componentes.
2. Incorporar fotografías autorizadas y contenido comercial definitivo. Las ilustraciones actuales son genéricas y deliberadamente identificadas como demo.
3. Definir políticas, contacto, impuestos, garantías comerciales, disponibilidad, entrega e indexación antes de vender. La interfaz no promete condiciones aún no aprobadas.
4. Implementar en futuras fases compra como invitado, cuenta opcional y protección de datos, según la decisión arquitectónica registrada.
5. Evaluar búsqueda avanzada, índices y caché al crecer el catálogo; ahora se utiliza búsqueda local `LIKE` y navegación por categorías del catálogo pequeño.
6. Proveedores siguen pospuestos y desacoplados conforme a la decisión Eurocomp. Una capacidad no documentada/no verificada se considera no disponible.

**No se implementan carrito, checkout, pedidos, pagos, logística ni APIs de Eurocomp/Dataformas. No se avanza de fase sin autorización.**

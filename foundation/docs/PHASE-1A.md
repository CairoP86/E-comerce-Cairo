# Fase 1A — Catálogo base

Estado: implementada para revisión. Fase 0B aprobada. No se inicia Fase 1B.

## Resultado y acceso

Catálogo comercial local administrable, con categorías jerárquicas, marcas, productos, especificaciones, precios manuales, imágenes, publicación y auditoría. Se conserva la base visual oscura con acentos champagne; el nombre comercial sigue pendiente. La visualización pública es una comprobación funcional, no el diseño completo de la tienda.

- Público: http://127.0.0.1:8086/catalog
- Administración: http://127.0.0.1:8086/admin/catalog/products
- Categorías: `/admin/catalog/categories`; marcas: `/admin/catalog/brands`.
- Se requiere cuenta verificada y rol autorizado. Se mantiene el mecanismo de asignación de roles de 0B; no se agrega una cuenta administrativa con contraseña predeterminada.

## Modelo, migración y relaciones

Migración: `database/migrations/2026_09_16_000001_create_catalog_tables.php`.

| Tabla | Contenido y relaciones |
| --- | --- |
| `categories` | Nombre, slug único, descripción, estado; `parent_id` opcional referencia otra categoría. Se impiden ciclos y autorreferencias. |
| `brands` | Nombre, slug único, descripción y estado. Una marca tiene muchos productos. |
| `products` | Categoría y marca obligatorias; SKU interno único, slug único, nombre, descripciones corta/completa, garantía, especificaciones JSON, precio público, precio anterior opcional, moneda, destacado, estado, indicador demo, SEO y primera fecha de publicación. |
| `product_images` | Producto, ruta privada, alt, posición, principal, fechas y `deleted_at`. Un producto tiene múltiples imágenes. |

Las claves foráneas restringen la eliminación de registros relacionados. No existen rutas de borrado de productos, marcas ni categorías. Sus estados son `draft`, `published` y `archived`; los registros archivados conservan SKU/slug exclusivos. La restauración de un producto archivado pasa por borrador. Retirar una imagen hace soft delete y conserva su archivo.

No se crean tablas de proveedores: añadir posteriormente ofertas relacionadas con `products.id` es una migración aditiva, sin reemplazar la identidad comercial ni mezclar costos con precios públicos.

## Especificaciones técnicas

Lista JSON de atributos con clave estable, etiqueta, valor, unidad y grupo opcionales. Máximo 50 atributos, claves distintas y validadas; no se aceptan campos anidados arbitrarios. Ejemplo:

```json
[{"key":"ram_gb","label":"Memoria RAM","value":"16","unit":"GB","group":"Memoria"}]
```

Permite representar laptops, computadoras, monitores, componentes, redes y accesorios sin nuevas columnas por especificación. Las importaciones futuras deberán normalizar claves, unidades y valores. Un diccionario tipado y los índices necesarios para filtros avanzados se decidirán cuando exista ese alcance; hoy los valores son descriptivos.

## Precios y publicación

- Dinero en enteros de unidades menores y moneda explícita (`CRC` o `USD`, dos decimales). No se utilizan floats para persistir precios ni existe conversión de divisas.
- Precio positivo, máximo `999999999999` unidades menores. Precio anterior opcional y estrictamente mayor que el actual.
- El servidor valida precios, taxonomía, datos y permisos. Los cambios de estado usan una operación dedicada; estado, fecha de publicación y bandera demo no se aceptan mediante edición masiva.
- Publicar requiere imágenes activas, marca publicada y categoría con todos sus antecesores publicados. Despublicar o archivar una taxonomía oculta sus productos sin destruirlos.
- Un producto no visible devuelve 404 públicamente, incluidas sus imágenes para visitantes. No se declara stock disponible ni se permite comprar.
- SEO básico: título, descripción, canonical y Open Graph en la respuesta HTML inicial del detalle; páginas demo con `noindex`. No se implementa SSR completo ni datos estructurados comerciales.

## Imágenes

Disco privado `catalog`, en `storage/app/catalog`; sin enlace público directo. Las imágenes se sirven mediante un controlador que verifica visibilidad o acceso de personal verificado.

Se admiten JPEG, PNG y WebP, hasta 5 MB y 4096 × 4096 por archivo, máximo 12 imágenes activas por producto. GD recodifica a WebP, elimina metadatos y asigna nombres aleatorios. SVG no se admite. El despliegue necesita PHP GD con soporte WebP y permisos de escritura en ese disco.

El panel permite subir varios archivos, ordenar, elegir principal y editar texto alternativo. La primera imagen es principal; al retirarla se selecciona otra. No se permite retirar la última imagen de un producto publicado. Reordenar valida el conjunto completo de IDs del mismo producto. Las operaciones bloquean el producto durante la transacción para preservar estas reglas. Fallos de subida revierten registros y limpian los archivos recién escritos.

Las ilustraciones demo se generan localmente; no hay imágenes descargadas de Eurocomp, Dataformas ni fabricantes. Las futuras importaciones deberán acreditar derechos de uso y pasar por validaciones equivalentes.

## Permisos y auditoría

| Operación | Visitante / customer | operator verificado | admin verificado |
| --- | --- | --- | --- |
| Consultar catálogo publicado | Sí | Sí | Sí |
| Consultar catálogo administrativo e imágenes privadas | No | Sí | Sí |
| Crear o editar productos, marcas y categorías | No | No | Sí |
| Publicar, despublicar, archivar o restaurar | No | No | Sí |
| Subir, ordenar, modificar o retirar imágenes | No | No | Sí |

`operator` es exclusivamente de consulta para este módulo. Gates, middleware y validación del backend hacen cumplir la política aunque se envíen solicitudes manuales.

La auditoría de 0B registra altas, modificaciones, estados e imágenes dentro de la transacción. Incluye actor, entidad, ID, nombres de campos afectados y transición de estado cuando corresponde; no guarda cuerpos completos, contraseñas, tokens ni valores comerciales. `subject_id` sigue reservado para usuarios; el producto se identifica en metadata. Se conserva la consulta de auditoría restringida a admin.

## Rutas

| Método | Ruta | Uso |
| --- | --- | --- |
| GET | `/catalog` | Lista pública paginada |
| GET | `/catalog/{slug}` | Detalle publicado |
| GET | `/catalog-images/{image}` | Imagen autorizada |
| GET | `/admin/catalog/products` | Lista administrativa, búsqueda y estado |
| GET | `/admin/catalog/products/create` | Formulario de alta, solo admin |
| GET | `/admin/catalog/products/{product}/edit` | Detalle; operator consulta |
| POST / PUT | `/admin/catalog/products` / `/{product}` | Crear / editar |
| PATCH | `/admin/catalog/products/{product}/status` | Publicación y archivo |
| POST / PATCH | `/admin/catalog/products/{product}/images` | Subir / ordenar y actualizar |
| DELETE | `/admin/catalog/products/{product}/images/{image}` | Retiro lógico |
| GET / POST | `/admin/catalog/{kind}` | Consultar / crear categoría o marca |
| PUT | `/admin/catalog/{kind}/{id}` | Editar categoría o marca y estado |

`kind` solo admite `categories` o `brands`. Las escrituras requieren admin, sesión autenticada, correo verificado y CSRF. La serialización pública utiliza `PublicProductResource` con lista explícita de campos permitidos; no entrega rutas de disco, costos ni estructuras de proveedores.

## Administración y demostración

Formularios manuales de todas las entidades, selector jerárquico, estados y archivo, especificaciones dinámicas, precios y moneda, destacado, SEO y gestor de imágenes. Vistas adaptables a escritorio y móvil.

Capturas verificadas: [panel de catálogo](screenshots/phase-1a-admin.png) y [formulario móvil](screenshots/phase-1a-mobile.png).

Factories: `CategoryFactory`, `BrandFactory`, `ProductFactory`, `ProductImageFactory`. `CatalogDemoSeeder` funciona solo en local/testing, crea 20 productos, cuatro marcas ficticias, una categoría raíz y 11 categorías temáticas, con 21 ilustraciones propias. SKU `DEMO-001` a `DEMO-020`. Los productos están marcados como demostración; los datos no representan ofertas ni stock reales. Reejecutar conserva las ediciones de productos existentes.

```powershell
php artisan migrate
php artisan db:seed --class=CatalogDemoSeeder
```

En la base local queda además un producto de verificación UI archivado y marcado demo, preservado junto con sus imágenes e historial. La cuenta temporal de navegador y su archivo de credenciales se retiran al cerrar las pruebas.

## Verificación

- Suite PHP completa: **38 pruebas, 333 aserciones**; incluye las 22 pruebas previas de 0A/0B y 16 nuevas de catálogo.
- Cobertura: categorías/marcas y archivo, jerarquía sin ciclos, productos, SKU/slug exclusivos incluso archivados, especificaciones, precios, campos protegidos, matriz de roles, publicación, invisibilidad pública, imágenes múltiples/orden/principal/alt, pertenencia de imágenes, validación de archivos, retiro lógico, auditoría y seeder idempotente.
- `npm run check`: comprobación TypeScript y compilación Vite.
- `php vendor/bin/pint --test`: formato PHP.
- `composer validate --strict`, `composer audit`, `composer check-platform-reqs` y `npm audit`: dependencias y requisitos.
- Migración y carga demo ejecutadas sobre MySQL local. `php artisan foundation:check`: MySQL, caché Redis y trabajo Redis.
- Navegador Chrome headless con Playwright contra la aplicación local: acceso, alta/edición, especificaciones, imágenes múltiples, orden/principal/alt, publicación, detalle público, despublicación con 404, archivo y cierre de sesión. Sin errores JavaScript en esos recorridos. Vista móvil de 390 px sin desbordamiento horizontal.
- El navegador integrado falló al inicializar (`sandboxPolicy`); la comprobación visual se hizo con Chrome separado. Los scripts y herramientas de esta comprobación están en `.local`, fuera de las dependencias de la aplicación. La suite PHP usa SQLite en memoria; las pruebas de navegador ejercitan MySQL local, no constituyen una prueba de carga.

## Decisiones, riesgos y pendientes

1. `products` es la identidad comercial propia. Las futuras `supplier_products` serán ofertas externas, posiblemente varias por producto, con SKU externo, costo y disponibilidad separados. No existe implementación de proveedores en 1A.
2. Se mantiene la [decisión Eurocomp para Fase 2](EUROCOMP-ARCHITECTURE.md). Primero se construye la tienda independiente; Dataformas es una posibilidad posterior. Una capacidad no documentada o no verificada no está disponible.
3. La tienda lee datos locales. Costos futuros deberán permanecer fuera de resources, props, HTML y respuestas públicas. El precio actual es manual; las reglas definitivas de margen, impuestos y redondeo comercial están pendientes.
4. Las futuras importaciones necesitan mapeo y conciliación de identidades, normalización de atributos, procedencia de imágenes, autenticación documentada, trazabilidad y política de conflictos con ediciones manuales. No se inventan endpoints ni DTOs externos.
5. Archivar conserva archivos; falta definir retención, recuperación de imágenes mediante UI, backups y conciliación de archivos huérfanos, especialmente ante interrupciones del seeder. No hay purga automática.
6. Cambiar un slug no crea redirecciones históricas. La edición simultánea no tiene aviso de versión obsoleta: la última edición válida prevalece. Se necesita definir estas políticas antes de operación comercial a escala.
7. El almacenamiento privado prioriza revocación de visibilidad; CDN, caché de imágenes y optimizaciones para catálogos grandes requieren una estrategia posterior.
8. El entorno sigue siendo de desarrollo. Se conservan los pendientes operativos de 0A/0B, incluido reemplazar Redis local antiguo para producción y configurar correo, TLS y supervisión.

No se implementan Fase 1B, Eurocomp, Dataformas, carrito, checkout, pagos, pedidos ni logística. El siguiente paso es la revisión de este resultado.

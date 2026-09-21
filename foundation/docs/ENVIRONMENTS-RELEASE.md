# Entornos y base de release · V1-A

Este documento prepara el proceso; no declara staging desplegado ni producción lista. Sigue [ADRs V1-A](adr/README.md) y [roadmap](V1-ROADMAP.md). La raíz servida siempre será `foundation/public`.

## Matriz de configuración

| Aspecto | local | testing / CI | staging | production |
| --- | --- | --- | --- | --- |
| APP_ENV | local | testing | staging | production |
| APP_DEBUG | false por defecto; true solo para depuración local privada | false en CI | false | false obligatorio |
| COMMERCE_AVAILABILITY_TTL_MINUTES | Obligatoria; ventana larga para datos manuales de prueba | Obligatoria; ventana larga (`phpunit.xml`, `.env.example`) | Obligatoria; según cadencia de polling a definir | Obligatoria y corta; sin ella la aplicación no arranca |
| TRUSTED_PROXIES | Sin definir; el valor por defecto (loopback) sirve | Sin definir | Dirección del proxy que termina TLS | Dirección del proxy que termina TLS; nunca `*` |
| APP_URL | http://127.0.0.1:8086 | http://localhost; aislado | URL HTTPS de staging aprobada | Dominio HTTPS aprobado |
| APP_KEY | propia de local | efímera generada en runner | propia, secreto persistente | propia, secreto persistente y respaldado |
| BD | MySQL existente; sin reset/reseed | SQLite :memory:, RefreshDatabase por test | MySQL separado, datos autorizados/anónimos | MySQL persistente, permisos mínimos |
| Caché/sesión | Redis local | array | Redis aislado | Redis aislado; política de persistencia/HA en V1-I |
| Colas | Redis local | sync | Redis y workers supervisados | Redis y workers supervisados |
| Correo | Mailpit/local, no destinatarios reales | array | sandbox/buzón controlado | servicio real aprobado antes de apertura |
| Imágenes | storage/app/catalog | Storage fake/fixtures de tests | volumen/bucket exclusivo persistente | almacenamiento persistente + backup |
| Cookies | Secure=false solo HTTP local; HttpOnly=true, SameSite=lax | sesión array | Secure=true, HttpOnly=true, SameSite=lax | Secure=true, HttpOnly=true, SameSite=lax |
| Sesión en reposo | configurable local | efímera | SESSION_ENCRYPT=true | SESSION_ENCRYPT=true; validar migración operativa |
| Logs | sin secretos, debug local solo si necesario | salida de test sin secretos | nivel info/error; redacción | nivel info/error, rotación/retención y acceso restringido |
| Indexación | noindex | noindex | noindex + protección de acceso | actualmente noindex; activación solo V1-H/J |

SQLite/array/sync certifica la regresión funcional, no concurrencia MySQL/Redis, workers ni disponibilidad de producción. Las pruebas de infraestructura real corresponden a V1-B/I.

## Plantilla y secretos

`.env.example` es una plantilla **local**, con direcciones loopback y secretos vacíos. No copiarla literalmente a staging/producción. No se modifica `.env` existente con esta tarea. Usar valores por entorno: APP_ENV, APP_DEBUG, APP_URL, DB_CONNECTION/HOST/PORT/DATABASE/USERNAME/PASSWORD, CACHE_STORE, CACHE_PREFIX, REDIS_CLIENT/HOST/PORT/PASSWORD/PREFIX, SESSION_DRIVER/ENCRYPT/SECURE_COOKIE/DOMAIN, QUEUE_CONNECTION, MAIL_* y almacenamiento.

Separar Redis por entorno (instancias/credenciales y prefijos); prefijos no sustituyen aislamiento de acceso. Si se elige almacenamiento remoto, el disco `catalog` necesita configuración específica en V1-I: las variables AWS de ejemplo no cambian automáticamente su driver local.

APP_KEY, contraseñas y credenciales se suministran por gestor de secretos o mecanismo protegido del host. No imprimir `.env`, `config:show` completo ni `env` en CI. Nunca poner credenciales en `VITE_*`: esas variables pueden llegar al navegador. No subir `.env`, logs, dumps, caché de configuración o `.local` como artefactos. No regenerar APP_KEY en cada release: afecta cifrado/sesiones y requiere plan de rotación.

No hay variables ni clientes para TiloPay/proveedores: se definirán contra documentación real. No suponer que añadir una API key habilita una capacidad.

## CI mínimo

Archivo en raíz del repositorio: `.github/workflows/ci.yml`. GitHub Actions, ubuntu-24.04, PHP 8.4 (la versión del servidor de producción), Composer 2 y Node 22. Acciones fijadas por SHA, permisos `contents: read`, credenciales de checkout no persistidas, timeout y cancelación de runs obsoletos. No usa secretos del negocio ni servicios MySQL/Redis.

Secuencia:

1. Checkout y herramientas; extensiones SQLite, GD/WebP y las requeridas por Laravel/tests.
2. `composer install` desde composer.lock, sin update.
3. Copiar plantilla al `.env` **efímero del runner**, generar clave sin imprimirla y verificar soporte WebP.
4. `npm ci --ignore-scripts` desde package-lock.json; `npm run check` (TypeScript y build). Va antes de la suite porque genera `public/build/manifest.json`, que necesitan los tests que renderizan `app.blade.php`.
5. Validar Composer/plataforma/Pint; `php artisan test` completo.

No contiene deploy, `db:seed`, `migrate --seed`, credenciales productivas ni carga de datos. La única excepción autorizada a esa regla es `DeliveryZonesSeeder`, que siembra tarifas de configuración, no datos comerciales, y se ejecuta a mano una vez por entorno. Los tests sí pueden usar seeders/fixtures dentro de SQLite aislado; esto no es seeding de la base comercial. No cambiar a `pull_request_target` para ejecutar código de contribuciones con secretos.

Fuentes primarias consultadas para las acciones: [checkout](https://github.com/actions/checkout), [setup-node](https://github.com/actions/setup-node), [setup-php](https://github.com/shivammathur/setup-php). Revisar y actualizar los SHA mediante PR deliberado. Que el YAML exista no significa que se haya ejecutado remotamente: la primera ejecución de GitHub queda pendiente de publicar los cambios de forma autorizada.

## Pendientes de configuración en el servidor real

Nada de esto vive en el repositorio: son tareas del host, previas a abrir la tienda. Ninguna se ha ejecutado.

| Pendiente | Qué hacer | Si falta |
| --- | --- | --- |
| **PHP 8.4** | El proyecto exige `^8.4`; el chequeo generado por Composer pide `PHP_VERSION_ID >= 80401`, es decir 8.4.1 o superior | `composer install` falla, y un `vendor` copiado aborta con error 500 |
| **`COMMERCE_AVAILABILITY_TTL_MINUTES`** | Definir una ventana corta y real, acorde a la cadencia de revisión; el valor largo de `.env.example` es solo para local y CI | La aplicación **no arranca** |
| **Cron del scheduler** | `* * * * * cd /ruta && php artisan schedule:run >> /dev/null 2>&1`, cada minuto | `holds:prune` nunca corre: las reservas vencidas dejan de contar igual, pero sus filas se acumulan |
| **`TRUSTED_PROXIES`** | Dirección del proxy que termina TLS (por defecto loopback, que cubre nginx en el mismo host) | Detrás de nginx la aplicación genera URLs `http://` y las cookies `Secure` no funcionan |
| **`APP_DEBUG=false` y `APP_ENV=production`** | Valores del entorno; el código ya usa `false` por defecto | Con `true` se exponen trazas y configuración |
| **`APP_KEY`** | Secreto propio y respaldado; nunca regenerar sobre una instalación con datos | Sesiones y datos cifrados quedan ilegibles |
| **Cookies y sesión** | `SESSION_SECURE_COOKIE=true` y `SESSION_ENCRYPT=true` | Cookies viajan sin protección de transporte |
| **Base de datos** | MySQL con usuario de permisos mínimos y respaldo verificado antes de migrar | Sin respaldo no hay retorno tras una migración |
| **Redis** | Instancia aislada con credenciales y prefijos propios para caché, sesión y colas | Un entorno puede leer o borrar datos de otro |
| **Workers de cola** | Proceso supervisado y `queue:restart` en cada release | Los trabajos en cola no se procesan |
| **Correo** | Servicio real aprobado; hasta entonces, buzón controlado | Correos de verificación y recuperación no llegan |
| **Imágenes** | Almacenamiento persistente para `storage/app/catalog`, con respaldo | Las imágenes del catálogo se pierden entre despliegues |
| **Logs** | Nivel `info`/`error`, rotación, retención y acceso restringido | Disco lleno o datos expuestos |
| **Cachés de release** | `config:cache`, `route:cache` y `view:cache` en el host ya configurado | Arranque más lento; además `config:cache` con `.env` incompleto congela valores erróneos |
| **Tarifas de envío** | Ejecutar una vez `php artisan db:seed --class=DeliveryZonesSeeder --force`; es idempotente, siembra configuración y no toca catálogo ni pedidos | El checkout bloquea la confirmación: sin tarifas no hay total final |
| **Contacto corporativo** | `STOREFRONT_WHATSAPP` y `STOREFRONT_CORPORATE_EMAIL`; no son secretos, llegan al navegador | `/venta-corporativa` publica el aviso de canal en habilitación en vez del contacto |
| **Indexación** | Sigue `noindex` en código; habilitarla es V1-H/J | — |

Una advertencia operativa: el catálogo público solo muestra productos con oferta preferida y dato vigente. Con un TTL corto, alguien debe volver a registrar la observación de cada oferta dentro de esa ventana, o la tienda se vacía. Mientras no exista la API del mayorista, ese trabajo es manual y la herramienta para hacerlo cómodo quedó fuera de alcance en V1-B (§6).

## Release normal propuesto (no ejecutado en V1-A)

1. CI verde para el commit exacto y reporte aprobado. Crear artefacto desde lockfiles y entorno de build aislado; `composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader`, `npm ci --ignore-scripts`, `npm run check`. Builder requiere dependencias frontend de desarrollo, aunque runtime no sirva node_modules.
2. Verificar extensiones PHP, incluyendo pdo_mysql y GD/WebP, permisos de storage/bootstrap/cache y conectividad a servicios del entorno. El check-platform no sustituye comprobar GD mientras no sea requisito declarado del paquete.
3. Aportar configuración/secretos del host sin copiarlos al artefacto. Backup verificable previo a cambios de esquema. Revisar diff de migraciones: solo aditivas y compatibles con la versión anterior durante despliegue.
4. Ejecutar únicamente migraciones revisadas pendientes (`php artisan migrate --force --no-interaction`) con responsable y respaldo; **nunca** `migrate:fresh`, `migrate:refresh`, `--seed` o `db:seed` en el flujo normal. No editar migraciones aplicadas.
5. Regenerar configuración/rutas/vistas en el host configurado (`config:cache`, `route:cache`, `view:cache`), comprobar resultado antes de activación. Reiniciar workers de forma controlada (`queue:restart`) y configurar supervisión/scheduler según las funciones habilitadas. V1-A no instala servicios.
6. Activación y smoke: HTTP/asset, auth/permisos, catálogo sin cambios, colas habilitadas y errores redactados. Staging no envía correos a clientes ni utiliza credenciales productivas.
7. Rollback de aplicación al artefacto anterior compatible. No revertir schema/datos financieros a ciegas; para migraciones futuras definir corrección hacia adelante o restauración previamente ensayada. V1-I/J realizará ensayo completo y validará runbooks/RPO/RTO.

No ejecutar `scripts/provision-local.php` en producción ni `key:generate` sobre una aplicación existente. `composer setup` es una comodidad de desarrollo, no un script de despliegue.

## Protección de seeders y datos

Inspección V1-A: `DatabaseSeeder` vacío; Composer no ejecuta seeders; no existía automatización de deployment. Por ello no se agrega un bloqueo invasivo a seeders utilizados por tests. El pipeline nuevo solo prueba una BD efímera. Las instrucciones de inicialización en README están separadas del release normal.

`CommercialSetupSeeder`, `CommercialTaxonomySeeder` y `CatalogDemoSeeder` solo se ejecutan con intención explícita en el entorno adecuado. En particular el seeder de taxonomía puede volver categorías a borrador: jamás programarlo ni incorporarlo al deployment. La base existente y su único producto de prueba indicado por el usuario se conservan; V1-A no crea productos, tarifas, stock ni ofertas.

## Puertas aún pendientes

Staging/producción requieren infraestructura, DNS/TLS, secretos, backups y supervisión reales. La base V1-A no implementa toda V1-I. Mantener noindex y no abrir ventas antes de disponibilidad, total fiscal/logístico aprobado, pagos verificables y políticas de cancelación/comprobantes. Fiscalidad y política financiera pendientes están expresas en ADR-008/009.

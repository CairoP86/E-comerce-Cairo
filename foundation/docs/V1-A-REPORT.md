# V1-A · Contratos internos y base de release

Fecha: 2026-09-18. Estado: implementada para revisión; no autoriza iniciar V1-B. Documento rector: [V1-ROADMAP.md](V1-ROADMAP.md).

## Estado inicial

- HEAD `1adf939` — Complete Phase 2C.
- Único archivo pendiente al empezar: `foundation/docs/V1-ROADMAP.md`, generado por la auditoría anterior y todavía no versionado. Se conservó y se agregó una actualización aprobada, sin borrar la auditoría histórica.
- Baseline ejecutado antes de la implementación: **116 tests / 2678 assertions, sin fallos**.
- Sin workflow CI ni automatización de deployment existentes. Composer no ejecuta seeders y `DatabaseSeeder` está vacío.
- Configuración `commerce.suppliers.eurocom` sin consumidores encontrados; seeder comercial con código `eurocomp`. No se presupuso una inconsistencia en filas de base de datos.

## Decisiones congeladas

Se documentaron los diez ADRs solicitados, con estado real, consecuencia y fase responsable. [Índice](adr/README.md):

1. Monolito modular; migraciones futuras aditivas.
2. CRC operativo V1, campos de moneda extensibles, sin conversión implícita.
3. Invitado principal y cuenta opcional, referenciando la decisión existente.
4. Costo privado, sugerencia y precio público independientes; snapshots históricos.
5. Editorial separado de vendible; desconocido/vencido/error nunca implica disponible; reservas internas/externas distintas.
6. Eurocomp/Dataformas/CQ y contratos pequeños según capacidades verificadas.
7. direct_supplier y via_operation, tarifa configurable y costo logístico separado de cobro.
8. Total final persistido por servidor, sin decisiones fiscales inventadas.
9. Pedido/pago/fulfillment separados, pending_payment conservado y cancelación distinta de reembolso.
10. APIs/TiloPay exclusivamente con documentación y sandbox reales; retorno del navegador no prueba pago.

**Diferencia entre contrato y código vigente:** el sistema heredado sigue aceptando CRC/USD y el checkout actual no valida stock del proveedor ni cotiza transporte. V1-A no oculta estos límites ni rompe su compatibilidad. Aplicar CRC a la operativa nueva corresponde a V1-C/E; disponibilidad a V1-B. No se presenta la aprobación del contrato como implementación de esas funciones.

Fiscalidad queda expresamente pendiente: IVA, inclusión en precio público, comprobantes, responsable de emisión y base fiscal de costos/márgenes. También quedan pendientes la política final de cancelaciones/reembolsos, TTL de disponibilidad y tratamiento operativo de pagos tardíos/duplicados. Son puertas previas al lanzamiento, no valores por defecto en código.

## Cambios ejecutables y compatibilidad

Único cambio de configuración de aplicación: `foundation/config/commerce.php`, clave `eurocom` → `eurocomp`. Se preservan `enabled=false`, transporte previsto y capabilities vacías. No se agregan adapters, clientes HTTP ni capacidades. La búsqueda de referencias no encontró consumidores activos de la clave vieja; no se mantiene un alias duplicado innecesario.

No se renombra ningún identificador persistido. Si existiesen instalaciones ajenas con referencias antiguas, deberán revisarse antes de su actualización; el reporte no certifica contenido de una BD inaccesible. Se documenta regenerar configuración cacheada durante release.

No cambian modelos, requests, servicios, controladores, rutas de negocio, frontend, tests ni migraciones. No se crean tablas ni datos. El documento Eurocomp se actualizó para reconocer las tablas manuales 2C y sustituir el flujo ambiguo de precio automático por revisión/aplicación explícita.

## CI

Nuevo `.github/workflows/ci.yml` en la raíz del repositorio:

- GitHub Actions para push, pull_request y ejecución manual; solo validación, sin despliegue.
- Ubuntu 24.04, PHP 8.5, Composer 2 y Node 22; acciones fijadas por SHA, permisos de lectura, sin credenciales persistidas de checkout.
- Instalación con `composer install` y `npm ci --ignore-scripts`, usando ambos lockfiles sin modificarlos.
- `.env` y clave efímeros exclusivamente en el runner, SQLite en memoria, cache/sesión array, queue sync, mail array.
- Suite PHP completa, validación Composer/plataforma/Pint y TypeScript/build. Comprobación GD/WebP explícita.
- Sin MySQL/Redis, secretos productivos, seeders de inicialización o instrucciones de deploy. Fixtures de tests solo dentro de BD aislada.

Se revisó el workflow y se ejecutaron las comprobaciones de aplicación localmente. **No se ha ejecutado el workflow en GitHub**, pues no se hizo commit/push; su primera ejecución remota sigue pendiente. Esto no certifica concurrencia productiva. Fuentes de las acciones y procedimiento están en [ENVIRONMENTS-RELEASE.md](ENVIRONMENTS-RELEASE.md).

## Entornos, secretos y seeders

Guía nueva para local/testing/staging/production: APP_ENV, APP_DEBUG, URL, secretos, BD, Redis, colas, correo, almacenamiento, cookies, indexación y proceso de release. Staging no se desplegó: no hay infraestructura configurada para esta tarea.

`.env.example` mantiene solo valores locales/placeholder y secretos vacíos. Se establece APP_DEBUG=false por defecto, cookies explícitas y prefijos locales de caché/Redis; la guía exige configuración separada y HTTPS/Secure en staging/production. `.env` real no se modifica ni se imprime. La indexación sigue deshabilitada en código; no se introduce una variable que simule habilitar SEO todavía.

Se verificó que no había automatización peligrosa de seeders. No se modifica su comportamiento ni los tests que los usan en aislamiento. La guía y README separan inicialización de deployment: prohibidos `db:seed`, `--seed`, `migrate:fresh` y `migrate:refresh` en el release normal. El workflow tampoco los ejecuta. No se ejecutó `CommercialTaxonomySeeder` ni equivalente contra la base actual.

La guía no declara resuelta V1-I: backups/restore, infraestructura productiva, TLS, supervisión y observabilidad todavía requieren implementación y ensayos. Las recomendaciones de comandos de release no se ejecutaron en el entorno local.

## MySQL local y preservación de datos

Se comprobaron listeners locales y se intentó una conexión de solo lectura usando la configuración existente, con error redactado. **MySQL sigue no disponible en 127.0.0.1:3307.** Por ello no se pudieron consultar migraciones aplicadas ni contar registros actuales. No se alteraron puertos, configuración, procesos, servicios ni credenciales para esta comprobación.

No hubo operaciones de escritura contra MySQL, migraciones o seeders. Producto/catálogo/pedidos existentes quedan sin intervención de esta tarea; la ausencia de acceso impide afirmar una comparación de hashes antes/después. Los tests usan SQLite en memoria conforme a phpunit.xml, no la base comercial.

## Verificación final

| Verificación | Antes | Después |
| --- | --- | --- |
| `php artisan test` (con reporte JUnit adicional) | 116 tests / 2678 assertions | **116 tests / 2678 assertions** |
| Failures / errors / skipped | 0 / 0 / 0 | **0 / 0 / 0** |
| `npm run check` | Baseline de auditoría previo aprobado | **PASS: vue-tsc y build**, 649 módulos, build 5.83 s |
| `composer validate --strict` | — | PASS |
| `php vendor/bin/pint --test` | — | PASS |
| `git diff --check` | — | PASS |
| Secretos en diff y archivos nuevos | — | Sin hallazgos en revisión manual y búsqueda de patrones/valores sensibles de plantilla |
| Links documentales y SHA de acciones | — | Referencias locales válidas; 3 acciones fijadas por SHA |

Se preservan las 116 pruebas originales sin quitar cobertura ni agregar pruebas que solo reflejen este cambio de clave/documentación. Los reportes JUnit confirman los recuentos. Composer emite únicamente su aviso conocido de deprecación `$http_response_header` en una dependencia interna con PHP 8.5; el comando termina correctamente.

La revisión de secretos cubrió los 20 archivos modificados/nuevos, incluyendo el roadmap preexistente; no imprime valores y no constituye una auditoría histórica de todo Git. `.env` no está versionado. Migraciones, tests, composer.lock y package-lock.json no tienen cambios. No se instaló ninguna dependencia adicional.

Estado final `git status --short`:

```text
 M foundation/.env.example
 M foundation/README.md
 M foundation/config/commerce.php
 M foundation/docs/EUROCOMP-ARCHITECTURE.md
 M foundation/docs/GUEST-CHECKOUT-DECISION.md
?? .github/
?? foundation/docs/ENVIRONMENTS-RELEASE.md
?? foundation/docs/V1-A-REPORT.md
?? foundation/docs/V1-ROADMAP.md
?? foundation/docs/adr/
```

El diff rastreado incluye 5 archivos (21 inserciones / 5 sustituciones o eliminaciones de líneas reemplazadas); los 15 archivos no rastreados adicionales están enumerados abajo. No hay borrados de archivos ni cambios destructivos. Los avisos de normalización CRLF/LF de Git no son errores de `diff --check`.

## Inventario de archivos

| Archivo | Cambio |
| --- | --- |
| `.github/workflows/ci.yml` | Nuevo pipeline de validación. |
| `foundation/config/commerce.php` | Normalización de clave Eurocomp; sigue deshabilitado. |
| `foundation/.env.example` | Defaults de ejemplo seguros y aclaraciones de entorno. |
| `foundation/README.md` | Enlaces V1-A y separación explícita de seeders/deploy. |
| `foundation/docs/ENVIRONMENTS-RELEASE.md` | Matriz de entornos y base documentada de release. |
| `foundation/docs/adr/README.md` | Índice de decisiones. |
| `foundation/docs/adr/001-modular-monolith.md` | ADR monolito/esquema aditivo. |
| `foundation/docs/adr/002-currency.md` | ADR CRC y compatibilidad histórica. |
| `foundation/docs/adr/003-guest-checkout.md` | Ratificación/referencia a decisión invitado existente. |
| `foundation/docs/adr/004-pricing.md` | ADR pricing separado. |
| `foundation/docs/adr/005-availability.md` | Contrato de disponibilidad, sin implementarlo. |
| `foundation/docs/adr/006-suppliers.md` | Identidad y capacidades de proveedores. |
| `foundation/docs/adr/007-delivery.md` | Modos logísticos futuros. |
| `foundation/docs/adr/008-final-total.md` | Total servidor y fiscalidad pendiente. |
| `foundation/docs/adr/009-order-payment-fulfillment.md` | Separación de estados y política financiera pendiente. |
| `foundation/docs/adr/010-external-integrations.md` | Contratos externos reales. |
| `foundation/docs/EUROCOMP-ARCHITECTURE.md` | Corregir restricciones históricas y flujo de precio. |
| `foundation/docs/GUEST-CHECKOUT-DECISION.md` | Ratificación, sin duplicar contrato. |
| `foundation/docs/V1-ROADMAP.md` | Conservar auditoría previa y agregar decisiones aprobadas. |
| `foundation/docs/V1-A-REPORT.md` | Este reporte. |

Los logs de verificación y la utilidad temporal de lectura MySQL están en `.local`, ignorados. No son dependencias nuevas ni scripts de despliegue. Ninguna migración fue modificada o agregada.

## Pendientes y entrada a V1-B

- Primera ejecución remota del CI y configuración de protección de rama por el responsable del repositorio, cuando se autorice publicar estos cambios.
- MySQL local disponible para comprobar esquema/datos y, en V1-B/I, pruebas reales MySQL/Redis; SQLite no sustituye esas pruebas.
- Definir TTL de disponibilidad manual, asignación/expiración interna, tratamiento de datos vencidos y revisión operativa para poder cobrar. No exigir API ficticia para avanzar.
- Implementar consumidor de disponibilidad con contratos pequeños y pruebas de concurrencia antes de llamar capacidad real de proveedor.
- Completar restricciones operativas CRC antes de habilitar cobros, sin alterar snapshots históricos.
- Resolver fiscalidad y política financiera antes de V1-E/J; obtener documentación externa cuando corresponda.

**No se implementó V1-B/C/D/E**, logística, pagos TiloPay, APIs reales/falsas de proveedores, stock ni catálogo ficticio. V1-F tampoco se inició. Sin commit ni push. Detenerse aquí para revisión del propietario.

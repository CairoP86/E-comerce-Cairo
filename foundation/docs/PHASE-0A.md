# Fase 0A — Fundación técnica

Documento histórico de la entrega 0A. La configuración y los pasos actuales de autenticación y migraciones están en [Fase 0B](PHASE-0B.md).

## Resultado verificado — 2026-09-15

- PHP 8.5.6, Laravel 13.31.0, MySQL 8.4.3, Redis local 5.0.14.1, Predis 3.6.0.
- Vue 3.5.42; Inertia cliente 3.7.1 / adaptador Laravel 3.3.4; Vite 7.3.6.
- `npm run check`: TypeScript y build correctos.
- `php artisan test`: 4 pruebas, 16 aserciones, todas correctas (incluye dos pruebas estándar del skeleton).
- `php artisan foundation:check`: MySQL, caché Redis y job Redis correctos.
- `composer validate --strict` y `composer check-platform-reqs`: correctos.
- Auditorías al instalar: Composer y npm sin vulnerabilidades reportadas en las versiones finales.
- `php vendor/bin/pint --test`: correcto.
- HTTP 200 en home, `/up`, recursos JS/CSS y respuesta Inertia con versión de assets vigente.
- Navegador integrado: conexión bloqueada por error de herramienta `missing field sandboxPolicy`; inspección visual e interacción real del botón pendientes. HTTP y compilación no sustituyen esa revisión.
- Servidor anterior del prototipo detenido porque servía la raíz. El nuevo servidor usa `foundation/public` en http://127.0.0.1:8086.

## Alcance

Laravel 13, PHP 8.5, Vue 3, Inertia 3, TypeScript, Tailwind 4 y Vite. MySQL conserva datos; Redis maneja sesiones, caché y colas. Sin endpoints de autenticación, catálogo, checkout o administración. Sin llamadas a Eurocom.

La aplicación está en `foundation/`. Los archivos de la raíz son el prototipo anterior. El document root debe ser **foundation/public**, nunca la raíz del repositorio o foundation completo.

## Instalación reproducible

Requisitos: PHP 8.5 con pdo_mysql, mbstring, openssl, curl, fileinfo, DOM/XML y zip; Composer 2; Node >=22.12; MySQL 8.4 y Redis. Versiones fijadas mediante composer.lock y package-lock.json.

Desde `foundation/`:

1. `composer install`
2. `npm ci --ignore-scripts`
3. Copiar `.env.example` a `.env` solo si no existe.
4. Configurar MySQL y Redis propios o iniciar los servicios locales descritos abajo.
5. `php artisan key:generate` solo al crear un entorno nuevo; no regenerar claves de entornos con datos cifrados.
6. `php artisan migrate --path=database/migrations/0001_01_01_000002_create_jobs_table.php`
7. `npm run check`
8. `php artisan test`
9. `php artisan foundation:check`
10. `php artisan serve --host=127.0.0.1 --port=8086`

Visitar http://127.0.0.1:8086. El botón de alcance verifica una interacción Vue. `npm run dev` activa Vite durante edición; la compilación estática no requiere ese proceso.

## Servicios aislados de este equipo

`scripts/start-local.ps1` inicia instancias independientes en loopback:

- MySQL 127.0.0.1:3307; datos en `../.local/mysql`.
- Redis 127.0.0.1:6380; datos en `../.local/redis`.

Después ejecutar `php scripts/provision-local.php`. Comprueba el directorio de datos antes de aprovisionar, crea tech_commerce y un usuario limitado a esa base. Genera contraseñas aleatorias en archivos ignorados y configura `.env` sin imprimirlas. MySQL comienza sin contraseña únicamente durante la inicialización local y el aprovisionamiento la establece. No usar este procedimiento para producción.

No se modifican las bases de Laragon. Los puertos deben estar libres o pertenecer a estas instancias. Los parámetros MySqlHome y RedisHome permiten indicar otros binarios. Redis 5 incluido en Laragon es solo para esta prueba local; producción requiere una versión mantenida en Linux/contenedor y una nueva validación de compatibilidad.

No ejecutar migraciones globales todavía: los archivos de usuarios/cache incluidos por Laravel permanecen sin aplicar. Autenticación y esquema de usuarios corresponden a 0B.

## Configuración

- `.env`, secretos, datos, vendor y node_modules se excluyen del control de versiones.
- Predis permite usar Redis sin extensión PHP adicional en Windows.
- Sesiones, caché y colas usan Redis con prefijos de aplicación.
- Trabajos después del commit. El chequeo utiliza una cola única y no consume trabajos de negocio.
- Los errores del chequeo no imprimen cadenas de conexión.
- Persistencia temporal en UTC; presentación comercial America/Costa_Rica; moneda CRC.
- `/up` comprueba arranque de Laravel. El comando CLI comprueba MySQL, escritura/lectura de caché y procesamiento de un trabajo Redis.
- Eurocom permanece deshabilitado, sin endpoints ni capacidades asumidas.
- Página no indexable. SEO comercial y SSR corresponden a fases posteriores.

## Límites

Base de desarrollo, no tienda lista para producción. TLS, cookies seguras, correo real, observabilidad, backups, SSR, scheduler y supervisión permanente de workers se completarán en sus fases. No se implementa 0B.

## Referencias

- https://github.com/laravel/laravel/tree/13.x
- https://github.com/inertiajs/inertia-laravel/tree/3.x
- https://github.com/laravel/vue-starter-kit

Se usa el skeleton sin instalar el starter kit de autenticación, para respetar 0A.

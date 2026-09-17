# Reporte de Fase 0B — Autenticación, roles, layouts y auditoría

Estado: implementada para revisión del propietario. No se inicia Fase 1A.

## 1. Alcance entregado

Autenticación propia de Laravel con guard web y sesiones Redis, integrada con Vue/Inertia. No se incorporaron dependencias de autenticación adicionales ni APIs de tokens para una aplicación que utiliza sesiones.

- Registro público, normalización de correo, validación y hash de contraseña.
- Login, recordar sesión, regeneración del identificador de sesión y logout mediante POST.
- Recuperación de contraseña mediante broker Laravel, expiración de 60 minutos, tokens de un uso y rotación de remember_token.
- Verificación de correo mediante URL firmada y con vencimiento; reenvío limitado.
- Sesiones autenticadas invalidadas al detectar cambio de hash de contraseña.
- Roles cerrados customer, operator y admin; customer por defecto en base de datos y modelo.
- Acceso a área privada con correo verificado; autorización comprobada en backend.
- Layout público, autenticación, cuenta y operaciones. Diseño responsive en carbón, acentos cálidos y superficies discretas, con controles accesibles.
- Pantallas de error 403/404/419/429, validaciones y correos en español.
- Auditoría persistente y vista paginada de solo lectura para admin.

No se crearon catálogo, proveedores, precios, checkout, pedidos ni métricas ficticias. El panel operativo es una base de navegación, no un dashboard comercial terminado. La cuenta muestra datos básicos; edición de perfil, direcciones y compras están fuera de esta entrega.

## 2. Matriz de permisos

| Acción | Invitado | Customer verificado | Operator verificado | Admin verificado |
|---|---|---|---|---|
| Registro / login / recuperación | Sí | Redirección a cuenta | Redirección a cuenta | Redirección a cuenta |
| Cuenta propia | Login requerido | Sí | Sí | Sí |
| Panel operativo `/admin` | Login requerido | 403 | Sí | Sí |
| Auditoría `/admin/audit` | Login requerido | 403 | 403 | Sí |
| Cambiar roles desde web | No existe | No existe | No existe | No existe |

Cualquier usuario sin verificar correo se dirige a verificación antes de entrar a áreas privadas. Los enlaces del frontend son solo presentación; las Gates del backend deciden el acceso.

## 3. Primer administrador y operadores

No se crean usuarios ni contraseñas predeterminadas. El seeder predeterminado dejó de crear una cuenta de prueba.

1. Abrir http://127.0.0.1:8086/register y registrar una cuenta.
2. En desarrollo, abrir http://127.0.0.1:8025 y seguir el enlace de verificación capturado por Mailpit.
3. Desde acceso confiable al servidor y dentro de `foundation/`, ejecutar:

```text
php artisan users:role correo@ejemplo.com admin
php artisan users:role otro-correo@ejemplo.com operator
```

El comando solo acepta cuentas existentes y verificadas y roles del enum. No verifica un correo por cuenta del usuario. Registra rol anterior/nuevo y origen console. Impide degradar al último administrador. Serializa cambios en una transacción con bloqueo de usuarios; adecuado al volumen inicial de 0B. El acceso al servidor es la frontera de confianza de este comando; no suplanta la identidad de un administrador web.

## 4. Auditoría

Tabla `audit_logs`: id, actor_id, subject_id, event, source, metadata y created_at (UTC). La UI presenta fechas en America/Costa_Rica.

Eventos: auth.registered, auth.login, auth.login_failed, auth.logout, auth.password_reset, auth.email_verified y user.role_changed.

- Registro, cambios de contraseña, verificación y cambios de rol guardan sus operaciones de base de datos y auditoría en transacciones.
- Login/logout usan eventos del guard Laravel.
- Intentos fallidos no guardan correo ni credenciales introducidas.
- La lista permitida de metadata solo admite from_role y to_role.
- No se almacenan contraseñas, hashes, tokens, cookies, cuerpos HTTP, direcciones IP ni respuestas de proveedor en auditoría.
- No hay rutas de edición o borrado. El modelo rechaza update/delete de registros existentes.
- Esta protección no es almacenamiento criptográficamente inmutable: un operador con acceso directo a SQL puede modificar datos. Retención, exportación externa y privilegios SQL más restringidos requieren política de operación antes de producción.

## 5. Seguridad verificada

- El registro ignora role y email_verified_at enviados por el cliente; role no es fillable.
- Login limitado por IP (20/minuto) y por combinación correo/IP (5/minuto); acciones de registro y recuperación limitadas por IP (5/minuto). Reenvío/verificación también limitados.
- Sesión regenerada al iniciar sesión; invalidación y nuevo token CSRF al salir.
- Middleware PreventRequestForgery de Laravel 13 sin exclusiones para estas rutas.
- Recuperación responde con el mismo mensaje para correos existentes y desconocidos. Esto evita divulgación en el contenido, sin afirmar igualdad exacta de tiempos SMTP.
- Contraseñas de 12 caracteres mínimo, letras y números, con límite explícito de 72 bytes de bcrypt para evitar truncamiento multibyte.
- Props Inertia con campos de usuario permitidos, nunca password ni remember_token.
- Vue escapa texto; no se usan v-html ni payloads de proveedor.
- URLs firmadas verifican expiración, usuario y hash del correo.
- Credenciales en `.env` ignorado; no hay credenciales de proveedores.

## 6. Migraciones y configuración

Se aplicaron las migraciones base de users/password_reset_tokens/sessions y cache, y la nueva migración de role/audit_logs en MySQL. Las tablas sessions/cache del scaffold existen, pero los drivers activos continúan siendo Redis. La migración de jobs de 0A se conserva.

Para reproducir con dependencias ya instaladas:

```text
powershell -File scripts/start-local.ps1
php artisan migrate
php artisan config:clear
npm run check
php artisan test
php artisan foundation:check
php artisan serve --host=127.0.0.1 --port=8086
```

En un equipo nuevo seguir primero la instalación de 0A y usar `php artisan migrate` para el esquema completo de 0B. No usar migrate:fresh en la base de desarrollo con cuentas reales.

Correo de desarrollo: SMTP en 127.0.0.1:1025; bandeja local Mailpit en 127.0.0.1:8025. Los mensajes no se envían a destinatarios externos. Los enlaces y tokens capturados deben tratarse como privados. Producción requiere transporte de correo real, dominio/remitente, HTTPS y configuración de cookies seguras; no desplegar Mailpit ni APP_DEBUG=true.

## 7. Verificación

Resultado final: **22 pruebas PHP correctas, 151 aserciones**. Incluyen dos pruebas estándar del skeleton. TypeScript, build Vite, estilo PHP y comprobación de infraestructura correctos.

- `npm run check`: TypeScript y compilación de producción Vite.
- Suite PHP de autenticación y roles: registro, validación, escalamiento bloqueado, sesión, logout, throttling, matriz de permisos, props, verificación firmada/expirada/ajena, recuperación genérica, reset inválido/expirado/reutilizado, límite multibyte, roles CLI y protección del último admin.
- Prueba CSRF activa el middleware real también dentro de PHPUnit y comprueba las seis rutas POST.
- PHPUnit usa SQLite en memoria; no destruye la base MySQL de desarrollo. MySQL se comprobó mediante migraciones reales y foundation:check, no se afirma equivalencia con una suite de concurrencia MySQL.
- `foundation:check`: conexión MySQL, escritura/lectura Redis y procesamiento de trabajo en cola.
- HTTP real: home, login, registro y recuperación responden 200; POST login sin CSRF responde 419.
- SMTP local: envío sintético recibido en Mailpit, sin crear cuentas reales de prueba ni administradores.
- `php vendor/bin/pint --test`: revisión de estilo PHP.

Limitación: navegador integrado no disponible por error de herramienta `missing field sandboxPolicy`. No se pudo verificar visualmente ni pulsar los formularios en navegador. La compilación y las pruebas HTTP/backend no sustituyen una revisión visual; queda pendiente revisar móvil y escritorio con navegador operativo.

## 8. Eurocomp y cierre de alcance

La [decisión Eurocomp](EUROCOMP-ARCHITECTURE.md) queda aprobada y reservada para Fase 2. No se implementaron cliente HTTP, endpoints, DTOs, tablas de proveedores ni capacidades simuladas. Una capacidad no documentada/verificada se considera no disponible.

Fase 1A no iniciada. La siguiente acción es revisar esta entrega.

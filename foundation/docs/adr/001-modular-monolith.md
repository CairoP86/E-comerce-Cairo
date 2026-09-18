# ADR-001 · Monolito modular

Estado: aceptado, 2026-09-18.

Contexto: Laravel/Vue ya resuelve catálogo, carrito, checkout y administración manual. Reemplazarlo no aporta una necesidad de V1.

Decisión: conservar un monolito modular, separando responsabilidades de catálogo, proveedores/disponibilidad, pricing, checkout, pedidos, pagos, logística y notificaciones mediante servicios de aplicación y proyecciones públicas explícitas. No introducir microservicios ni reorganizar masivamente carpetas en V1-A.

Consecuencias: conservar `Product`, `CartStore`, `CheckoutService`, snapshots y gates. Toda modificación futura de esquema será aditiva; nunca editar migraciones históricas aplicadas. Las integraciones no consultan APIs en cada visita pública ni guardan secretos en modelos públicos.

Verificación: suite de regresión y revisión de migraciones/diff. V1-A no agrega tablas ni contratos ejecutables sin consumidor.

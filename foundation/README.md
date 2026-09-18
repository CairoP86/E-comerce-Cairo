# TECH COMMERCE - Fase 2C

[Reporte completo de Fase 2C — Proveedores y precios](docs/PHASE-2C.md)

[Reporte completo de Fase 2B — Checkout y pedidos](docs/PHASE-2B.md)

[Reporte completo de Fase 2A — Carrito](docs/PHASE-2A.md)

[Reporte completo de Fase 1B](docs/PHASE-1B.md)

[Decisión: compra como invitado](docs/GUEST-CHECKOUT-DECISION.md)

[Reporte completo de Fase 1A](docs/PHASE-1A.md)

[Reporte completo de Fase 0B](docs/PHASE-0B.md)

[Instalacion y verificacion](docs/PHASE-0A.md)

[Arquitectura Eurocomp API](docs/EUROCOMP-ARCHITECTURE.md)

URL local: http://127.0.0.1:8086

Fase 2B aprobada y respaldada en Git (`506ddf4`). Fase 2C implementada para revisión: proveedores y ofertas manuales, reglas comerciales y aplicación explícita de precios. TECH COMMERCE es el nombre de trabajo. Pagos e integraciones API permanecen fuera de alcance.

Proveedores: http://127.0.0.1:8086/admin/commercial/suppliers · Reglas: http://127.0.0.1:8086/admin/commercial/rules.

Preparación inicial: `php artisan migrate --force`, `php artisan db:seed --class=CommercialSetupSeeder --force` y `php artisan db:seed --class=CommercialTaxonomySeeder --force`. Los seeders no crean productos ni ofertas. La taxonomía comercial tiene 24 categorías en borrador (3 raíces y 21 subcategorías), con reglas heredadas. El seeder de taxonomía es una inicialización explícita, no un sincronizador para ejecutar después de publicar.

Los productos DEMO locales fueron archivados sin borrado mediante `php artisan catalog:archive-demo`; la corrección de taxonomía también archivó las 12 categorías DEMO conservando sus relaciones. La carga comercial requiere datos confirmados.

Checkout invitado: http://127.0.0.1:8086/checkout (carrito válido). Pedidos administrativos: http://127.0.0.1:8086/admin/orders.

Carrito público: http://127.0.0.1:8086/cart · Persistencia en sesión/Redis, con cuenta opcional.

Storefront público: http://127.0.0.1:8086/ · Catálogo: http://127.0.0.1:8086/catalog

Identidad: `config/storefront.php`. Paleta editable: `resources/css/storefront-tokens.css`.

Administración: http://127.0.0.1:8086/admin/catalog/products (cuenta verificada; admin modifica, operator consulta).

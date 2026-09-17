# TECH COMMERCE - Fase 2B

[Reporte completo de Fase 2B — Checkout y pedidos](docs/PHASE-2B.md)

[Reporte completo de Fase 2A — Carrito](docs/PHASE-2A.md)

[Reporte completo de Fase 1B](docs/PHASE-1B.md)

[Decisión: compra como invitado](docs/GUEST-CHECKOUT-DECISION.md)

[Reporte completo de Fase 1A](docs/PHASE-1A.md)

[Reporte completo de Fase 0B](docs/PHASE-0B.md)

[Instalacion y verificacion](docs/PHASE-0A.md)

[Arquitectura Eurocomp API](docs/EUROCOMP-ARCHITECTURE.md)

URL local: http://127.0.0.1:8086

Fase 2A aprobada y respaldada en Git. Fase 2B implementada para revisión. No se inicia la fase siguiente. TECH COMMERCE es el nombre de trabajo.

Checkout invitado: http://127.0.0.1:8086/checkout (carrito válido). Pedidos administrativos: http://127.0.0.1:8086/admin/orders.

Carrito público: http://127.0.0.1:8086/cart · Persistencia en sesión/Redis, con cuenta opcional.

Storefront público: http://127.0.0.1:8086/ · Catálogo: http://127.0.0.1:8086/catalog

Identidad: `config/storefront.php`. Paleta editable: `resources/css/storefront-tokens.css`.

Administración: http://127.0.0.1:8086/admin/catalog/products (cuenta verificada; admin modifica, operator consulta).

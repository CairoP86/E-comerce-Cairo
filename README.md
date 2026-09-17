# Proyecto E-commerce

La aplicación está en [foundation](foundation/README.md). Fase 2A aprobada y respaldada en Git; Fase 2B implementada para revisión. No se inicia la fase siguiente.

- [Reporte completo de Fase 2B — Checkout y pedidos](foundation/docs/PHASE-2B.md)

- [Reporte completo de Fase 2A — Carrito](foundation/docs/PHASE-2A.md)
- Carrito local: http://127.0.0.1:8086/cart

- [Reporte completo de Fase 1B](foundation/docs/PHASE-1B.md)
- [Decisión: compra como invitado](foundation/docs/GUEST-CHECKOUT-DECISION.md)

- [Reporte completo de Fase 1A](foundation/docs/PHASE-1A.md)

- [Reporte completo de Fase 0B](foundation/docs/PHASE-0B.md)

- [Resultado y configuración de Fase 0A](foundation/docs/PHASE-0A.md)
- [Decisión Eurocomp API](foundation/docs/EUROCOMP-ARCHITECTURE.md)
- URL local actual: http://127.0.0.1:8086

El servidor antiguo del prototipo está detenido. No servir la raíz de este repositorio: contiene configuración y servicios locales privados. El document root correcto es `foundation/public`.

## Prototipo anterior — referencia histórica

ATELIER fue un nombre provisional, no la marca definitiva. Las siguientes instrucciones describen el prototipo anterior y no el entorno Laravel actual.

Primera versión en español para el flujo de e-commerce con Eurocomp. PHP sirve la página; la interfaz usa JavaScript y CSS sin compilación.

## Abrir

Vista local iniciada en esta sesión: `http://127.0.0.1:8085`.

En Laragon, si Apache sirve el puerto 80: `http://localhost/E-Comerce/`. En este equipo el puerto 80 actualmente responde con IIS.
Alternativa: ejecutar `php -S 127.0.0.1:8080` en esta carpeta y abrir `http://127.0.0.1:8080`.

## Incluye

- Catálogo ilustrativo con categorías, búsqueda y orden por precio.
- Bolsa persistente, cantidades limitadas a la existencia ilustrativa y eliminación de artículos.
- Confirmación de pedidos de demostración e historial local.
- Diseño adaptable a móvil, diálogos accesibles y formato de colones costarricenses.
- Ilustraciones SVG creadas para el prototipo; no son fotografías de los productos.

Los datos se guardan en localStorage del navegador. No existe servidor de pedidos, reserva de inventario, autenticación ni procesamiento de pagos. No ingresar información personal. Las fuentes de Google son opcionales y tienen alternativas locales.

## Integración pendiente

Obtener documentación y autorización de Eurocomp: catálogo, identificadores, existencias, precios, creación de órdenes y estados. Confirmar acceso y condiciones de Rapedido, tarifas, zonas y plazos. Seleccionar pasarela de pago, moneda, impuestos y política comercial.

La siguiente fase requiere backend y base de datos, credenciales solo en servidor, validación de precio/existencia al confirmar, webhooks verificados, idempotencia de pagos y órdenes, y conciliación de errores. Los pasos del proceso son una descripción del flujo previsto y no representan integraciones activas.

## Dirección visual

ATELIER es un nombre provisional pendiente de elección del propietario. La capa `premium.css` define la identidad editorial: carbón, marfil y champagne, tipografía serif y exposición de productos con más espacio.

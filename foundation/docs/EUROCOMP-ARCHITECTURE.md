# Eurocomp API — decisión confirmada, 2026-09-15

**Estado actualizado V1-A (2026-09-18):** Fase 2C ya implementó proveedores y ofertas manuales separados de Product. La restricción histórica de no crear tablas correspondía a 0B y fue reemplazada por esa autorización; no impide las tablas actuales. Se mantiene la prohibición de inventar clientes HTTP, endpoints, DTOs externos o capacidades antes de contar con documentación oficial. Una capacidad no documentada o no verificada se considera no disponible. Los nombres siguientes describen contratos previstos, no código implementado. Prevalecen [ADR-006](adr/006-suppliers.md), [ADR-004](adr/004-pricing.md) y [ADR-010](adr/010-external-integrations.md).

Eurocomp será el primer proveedor mediante un adaptador y cliente HTTP dedicados. La API está confirmada; endpoints, autenticación, payloads y capacidades no están documentados todavía en el proyecto. No se ejecutan solicitudes externas en Fase 0A.

## Contratos previstos para Fase 2

### Actualización aprobada al iniciar Fase 1A

Primero se construye el e-commerce independiente, con catálogo y precios públicos administrados manualmente. Eurocomp será la primera integración futura; Dataformas es una posibilidad posterior. Esta decisión conserva la arquitectura descrita a continuación como diseño para Fase 2, sin implementar contratos ejecutables ni simular capacidades.

Fase 1A crea `products` como producto comercial propio. No crea `suppliers` ni `supplier_products`: una migración aditiva futura podrá relacionar las ofertas externas con el ID estable del producto, sin reemplazarlo. Las visitas públicas leen exclusivamente el catálogo local. Una capacidad no documentada o no verificada se considera no disponible.

- SupplierAdapter: identificación y declaración de capacidades verificadas.
- CatalogSource: lectura paginada o incremental del catálogo y normalización de SKU, categorías, marcas, costo, moneda, disponibilidad, stock, imágenes y especificaciones cuando existan.
- AvailabilityChecker: consulta reciente de disponibilidad con fecha de observación y resultado explícito desconocido/error.
- InventoryReserver: reserva, vencimiento y liberación, solo si están soportados.
- PurchaseOrderSubmitter: creación y conciliación de órdenes de compra, solo si están soportadas.
- SupplierOrderStatusReader: seguimiento del pedido al proveedor, solo si está soportado.

El cliente Eurocomp maneja transporte, autenticación y serialización externa; el adaptador transforma respuestas a objetos internos. El dominio depende de contratos y nunca de payloads Eurocomp. Las firmas se concretan al revisar la documentación. Una capacidad desconocida se considera no disponible; nunca se simula como exitosa. La configuración actual mantiene el proveedor deshabilitado y sin capacidades.

## Datos y flujo

Flujo futuro condicionado a capacidades reales: API Eurocomp → sincronización de supplier_products → precio sugerido privado → revisión/aplicación manual al precio público de products → tienda/carrito local → validación reciente cuando esté soportada → pedido → pago verificado → orden Eurocomp solo si se verifica esa capacidad → despacho → transporte → cliente. La sincronización de costo nunca publica precios automáticamente. Hoy no se ejecuta ninguna de esas llamadas externas.

products permanece separado de supplier_products. Las páginas leen catálogo local; las visitas no disparan llamadas al proveedor. Se conservarán proveedor/SKU únicos, costo y moneda privados, stock reportado, fecha de observación, fecha de sincronización y vigencia. No crear tablas comerciales en 0A.

## Stock y pagos

Consultar no equivale a reservar. Con reservas verificadas, registrar referencia y vencimiento, revalidar antes de solicitar pago y gestionar vencimientos. Sin reservas, se conserva el riesgo de venta externa entre consulta, pago y orden: informar condiciones, reconfirmar y disponer de resolución operativa/reembolso. Un error de API o dato vencido impide confirmación automática; puede generar solicitud pendiente de revisión, nunca venta garantizada. Los bloqueos y asignaciones locales solo resuelven concurrencia interna.

Los costos no se serializan en props Inertia, endpoints públicos, HTML, caché pública ni analítica del navegador. Se utilizarán DTO/resources públicos con campos permitidos, además de autorización administrativa.

## Fiabilidad y registro

Registrar sync_runs y errores; timestamps, operación, identificador de correlación, resultado HTTP y campos de respuesta estrictamente necesarios. No registrar Authorization, tokens, contraseñas ni cuerpos íntegros por defecto. Redactar datos personales, restringir acceso y establecer retención.

Validar páginas/importaciones antes de publicar, distinguir feed completo y delta, impedir actualizaciones fuera de orden y duplicados. Usar timeouts, reintentos limitados y respeto a límites documentados. No reintentar automáticamente creación de órdenes sin idempotencia o consulta de conciliación verificadas. Jobs después del commit; reintentos operativos trazables.

## Pendiente de documentación

URL base y sandbox; autenticación/renovación; límites y paginación; esquema y moneda de costos; semántica de stock; horarios; reservas/vencimiento/liberación; creación/consulta/cancelación de órdenes e idempotencia; webhooks y firmas si existen; derechos de imágenes; reglas de reintento. No asumir ninguna de estas capacidades.

La Fase 0B fue aprobada. La autorización de Fase 1A no habilita la integración de proveedores ni la Fase 1B.

# ADR-005 · Publicación y disponibilidad

Estado: contrato aceptado, 2026-09-18. Implementación pendiente de V1-B.

Decisión: publicación editorial y disponibilidad vendible son conceptos distintos.

- Desconocido o vencido no equivale a disponible.
- Consulta de stock del proveedor no equivale a reserva.
- Reserva interna no equivale a reserva externa.
- Nunca asumir stock ilimitado ni disponibilidad por timeout/error del proveedor.
- Los límites técnicos 99 unidades/50 líneas no representan inventario.

Estado real: ofertas guardan stock manual y fecha observada; checkout actualmente valida publicación y precios, no inventario del proveedor. No se declara ese checkout apto para cobrar por aprobar este ADR.

V1-B definirá política de vigencia, cantidades vendibles, revisión manual, asignación y expiración. Un resultado deberá diferenciar desconocido, vencido, disponible y no disponible con procedencia/fecha; las firmas ejecutables se crearán junto al consumidor. TTL y resolución comercial siguen pendientes: no se inventan ahora.

Complemento: las decisiones de negocio confirmadas para V1-B (frescura/TTL configurable, estados, reserva local) están en [V1-B-SCOPE.md](../V1-B-SCOPE.md). Ese documento completa este ADR; no lo reemplaza.

# ADR-009 · Pedido, pago y fulfillment

Estado: contrato aceptado, 2026-09-18. Implementación operativa pendiente de V1-D/E/F.

Conservar `pending_payment` y los pedidos existentes. En fases posteriores separar estado comercial, estado financiero y estado logístico. V1-A no amplía el enum ni construye una máquina de estados prematura.

Cancelar pedido y reembolsar son operaciones distintas; una cancelación puede dejar una acción financiera pendiente. `refunded` será financiero, no reemplazo de shipped/delivered. Un intento fallido no debe destruir un pedido reintentable. Pagos tardíos o duplicados generan incidencia y conciliación, no reactivación automática ni segundo despacho.

Las transiciones futuras tendrán autorización, historial, precondiciones e idempotencia. El retorno del navegador no prueba pago. Política comercial de cancelación, vencimiento y reembolsos pendiente antes de V1-E/J; no se inventan ventanas ni reglas financieras.

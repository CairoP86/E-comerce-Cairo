# ADR-009 · Pedido, pago y fulfillment

Estado: contrato aceptado, 2026-09-18. Implementación operativa pendiente de V1-D/E/F.

Conservar `pending_payment` y los pedidos existentes. En fases posteriores separar estado comercial, estado financiero y estado logístico. V1-A no amplía el enum ni construye una máquina de estados prematura.

Cancelar pedido y reembolsar son operaciones distintas; una cancelación puede dejar una acción financiera pendiente. `refunded` será financiero, no reemplazo de shipped/delivered. Un intento fallido no debe destruir un pedido reintentable. Pagos tardíos o duplicados generan incidencia y conciliación, no reactivación automática ni segundo despacho.

Las transiciones futuras tendrán autorización, historial, precondiciones e idempotencia. El retorno del navegador no prueba pago. Política comercial de cancelación, vencimiento y reembolsos pendiente antes de V1-E/J; no se inventan ventanas ni reglas financieras.

**Estado (2026-09-21): registrar un pago manual es una venta permanente.** Marcar un pedido como pagado descuenta el stock de la oferta de la que salió cada unidad y retira la reserva del pedido, que queda como historial. Todo ocurre en una transacción que bloquea pedido, ofertas y reservas en ese orden, y es todo o nada. Se bloquea con un mensaje para el operador si la unidad ya no está: otro pedido la pagó, otro pedido pendiente la tiene dentro de su hora de reserva, el stock es desconocido, la oferta está marcada no disponible o está desactivada, o el pedido es anterior a las reservas. Un pago prevalece sobre la reserva de un carrito ajeno: esa reserva se anula y ese checkout falla con el mensaje habitual. El descuento es local; no se comunica a ningún proveedor. Sin reembolso ni cancelación implementados, un pago registrado no devuelve stock.

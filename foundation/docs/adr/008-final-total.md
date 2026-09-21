# ADR-008 · Total final del servidor

Estado: contrato aceptado, 2026-09-18. Desglose final pendiente de V1-C.

El servidor calculará y persistirá subtotal, descuentos si existen, envío, impuestos cuando correspondan y total final, con moneda, versión y política de redondeo. El total persistido autorizado será el monto enviado a TiloPay. El cliente no aporta importes válidos por sí mismo.

Estado actual (V1-C): `CheckoutService` calcula y persiste subtotal de productos, envío y total final, con la versión de tarifa que los produjo. El total persistido es el que se mostró antes de confirmar. No se persiste ningún importe de impuesto; el desglose de IVA descrito abajo es derivado. Los pedidos anteriores a V1-C conservan `shipping_minor` nulo: no son cotizaciones completas y no se recalculan retrospectivamente.

**Decisión fiscal, 2026-09-20, tomada por el propietario del negocio.** Los precios públicos son IVA incluido al 13%. El negocio está inscrito ante Hacienda y factura electrónicamente bajo MC InfraTech, de modo que el desglose mostrado tiene respaldo de comprobante. Esta decisión reemplaza, en lo que respecta a inclusión en precio público y responsable de emisión, la prohibición previa de asumir IVA incluido.

El desglose es **derivado y no persistido**: se calcula al leer como `subtotal_de_productos × 13/113`, redondeado a céntimos, y no altera ningún total ni el monto que se enviará al procesador de pago. No se agregaron columnas fiscales. El envío queda **fuera** de la base imponible mostrada hasta que se decida su tratamiento; por eso la línea dice explícitamente que el IVA corresponde a los productos. Los pedidos anteriores también muestran la línea, calculada sobre su subtotal almacenado: su total no se recalcula.

Sigue pendiente: base fiscal de costos y márgenes, y el tratamiento del IVA que el proveedor cobra al comprar, que es un asunto de costo interno y no afecta el precio público.

Cambios de carrito/destino/método/tarifa requerirán revisión actualizada. Montos cobrados e importes históricos no dependen de costos o tarifas posteriores. Sin política fiscal aprobada no se habilita venta real.

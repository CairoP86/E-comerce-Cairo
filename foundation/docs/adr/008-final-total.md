# ADR-008 · Total final del servidor

Estado: contrato aceptado, 2026-09-18. Desglose final pendiente de V1-C.

El servidor calculará y persistirá subtotal, descuentos si existen, envío, impuestos cuando correspondan y total final, con moneda, versión y política de redondeo. El total persistido autorizado será el monto enviado a TiloPay. El cliente no aporta importes válidos por sí mismo.

Estado actual (V1-C): `CheckoutService` calcula y persiste subtotal de productos, envío y total final, con la versión de tarifa que los produjo. El total persistido es el que se mostró antes de confirmar. Los impuestos siguen sin calcularse. Los pedidos anteriores a V1-C conservan `shipping_minor` nulo: no son cotizaciones completas y no se recalculan retrospectivamente.

Puerta pendiente de lanzamiento: aprobación del tratamiento de IVA, inclusión o exclusión en precio público, comprobantes/facturación, responsable de emisión y base fiscal de costos/márgenes. V1-A no define tasas, no asume IVA cero ni IVA incluido y no agrega columnas fiscales.

Cambios de carrito/destino/método/tarifa requerirán revisión actualizada. Montos cobrados e importes históricos no dependen de costos o tarifas posteriores. Sin política fiscal aprobada no se habilita venta real.

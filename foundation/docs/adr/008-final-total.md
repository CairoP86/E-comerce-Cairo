# ADR-008 · Total final del servidor

Estado: contrato aceptado, 2026-09-18. Desglose final pendiente de V1-C.

El servidor calculará y persistirá subtotal, descuentos si existen, envío, impuestos cuando correspondan y total final, con moneda, versión y política de redondeo. El total persistido autorizado será el monto enviado a TiloPay. El cliente no aporta importes válidos por sí mismo.

Estado actual: `CheckoutService` conserva subtotal y total de productos iguales; no hay envío ni impuestos calculados. No interpretar estos pedidos legacy como cotizaciones completas ni recalcularlos retrospectivamente.

Puerta pendiente de lanzamiento: aprobación del tratamiento de IVA, inclusión o exclusión en precio público, comprobantes/facturación, responsable de emisión y base fiscal de costos/márgenes. V1-A no define tasas, no asume IVA cero ni IVA incluido y no agrega columnas fiscales.

Cambios de carrito/destino/método/tarifa requerirán revisión actualizada. Montos cobrados e importes históricos no dependen de costos o tarifas posteriores. Sin política fiscal aprobada no se habilita venta real.

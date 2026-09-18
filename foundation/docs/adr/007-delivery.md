# ADR-007 · Modos de entrega

Estado: contrato aceptado, 2026-09-18. Motor pendiente de V1-C; operación en V1-D/F.

V1 deberá soportar `direct_supplier` (proveedor → cliente) y `via_operation` (proveedor → nuestra operación → cliente). Preparar origen, destino y tramos sin asumir un transportista ni una integración automatizada.

Tarifas configurables desde administración; no precios de transporte hardcodeados. Costo real privado de transporte y monto cobrado al cliente son conceptos distintos. Envío gratis al cliente no implica costo logístico cero. Cobertura, peso/dimensiones, reglas de gratuidad y tarifas reales se decidirán en V1-C con datos confirmados.

V1-A no crea tarifas, tablas logísticas, cotizador ni tracking. La entrega directa declarada por Eurocomp no demuestra capacidades API ni sustituye la definición de nuestros comprobantes fiscales.

# ADR-007 · Modos de entrega

Estado: contrato aceptado, 2026-09-18. Motor de cotización implementado en V1-C ([reporte](../V1-C-REPORT.md)); administración de tarifas y operación siguen pendientes de V1-D/F.

V1-C entrega zonas por cantón, tarifa plana por zona y umbral de envío gratis, versionados en base de datos. Dos exigencias de este ADR siguen sin cumplirse: las tarifas no se administran desde una pantalla, sino insertando un conjunto nuevo en la base; y el cotizador no distingue `direct_supplier` de `via_operation`.

V1 deberá soportar `direct_supplier` (proveedor → cliente) y `via_operation` (proveedor → nuestra operación → cliente). Preparar origen, destino y tramos sin asumir un transportista ni una integración automatizada.

Tarifas configurables desde administración; no precios de transporte hardcodeados. Costo real privado de transporte y monto cobrado al cliente son conceptos distintos. Envío gratis al cliente no implica costo logístico cero. Cobertura, peso/dimensiones, reglas de gratuidad y tarifas reales se decidirán en V1-C con datos confirmados.

V1-A no crea tarifas, tablas logísticas, cotizador ni tracking. La entrega directa declarada por Eurocomp no demuestra capacidades API ni sustituye la definición de nuestros comprobantes fiscales.

# ADR-004 · Costo, sugerencia y precio público

Estado: aceptado, 2026-09-18. Implementado en 2C.

Decisión: `supplier_products.cost_minor` es privado; `CommercialPricing` calcula una sugerencia; `products.price_minor` es el precio público autorizado. Editar costo, preferencia o regla no publica un precio. Aplicar sugerencia requiere acción explícita y revisión vigente.

Regla del ancestro más cercano, con excepción de producto prioritaria. Multiplicadores iniciales: Cómputo ×1.40, Redes ×1.50, Periféricos ×1.70. Son markup/multiplicadores; no porcentajes de margen bruto.

Pedidos conservan sus snapshots monetarios aunque cambien ofertas o precios. Recursos públicos mantienen allowlists y nunca incorporan costos, preferencias ni multiplicadores internos. Las futuras sincronizaciones actualizan ofertas, no precios publicados.

Desde el 2026-09-20, por decisión del propietario, `products.price_minor` es **IVA incluido al 13%** (ver ADR-008). El IVA que el proveedor cobra al comprar es un concepto distinto, de costo interno, y no se deduce del precio público ni se compara con él. Fiscalidad del margen sigue pendiente: no comparar importes con bases fiscales incompatibles. V1-G detallará rentabilidad cuando existan datos aprobados.

# ADR-010 · Contratos externos reales

Estado: aceptado, 2026-09-18.

TiloPay es la pasarela elegida para V1-E. Eurocomp, Dataformas y CQ se integrarán solo con documentación/credenciales y capacidades verificadas. Una cuenta o autorización comercial no define endpoints, payloads, autenticación ni soporte de webhooks/reservas.

No implementar clientes ficticios ni respuestas que aparenten una conexión real. No almacenar secretos en Git, props públicas o logs. Los dobles de tests futuros se identificarán como tales y se basarán en contratos reales; no certificarán por sí solos una integración.

Para pagos: monto desde total persistido; retorno solo orienta la UX. Autenticidad, consulta, idempotencia y conciliación se concretarán contra documentación y sandbox reales. Para proveedores: separar capacidades pequeñas; las no verificadas se consideran no disponibles.

V1-A no integra APIs ni TiloPay. La separación existente producto/oferta/costo/precio permite incorporarlas después sin reemplazar Product. Referencia actualizada: [EUROCOMP-ARCHITECTURE.md](../EUROCOMP-ARCHITECTURE.md).

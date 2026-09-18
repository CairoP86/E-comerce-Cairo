# ADR-003 · Checkout invitado

Estado: aceptado y ratificado, 2026-09-18.

La decisión canónica ya existe en [GUEST-CHECKOUT-DECISION.md](../GUEST-CHECKOUT-DECISION.md); se registra aquí su índice sin duplicar el contrato.

V1-A la ratifica: navegar y comprar no requiere registro. Cuenta customer opcional. Nombre, correo, teléfono y dirección del pedido son independientes de `user_id`. Vinculación posterior exige prueba de titularidad, no coincidencia de email.

Estado: invitado implementado en 2A/2B; recuperación e historial pertenecen a V1-F. Se conservan rutas públicas y tests de invitado; V1-A no cambia autenticación.

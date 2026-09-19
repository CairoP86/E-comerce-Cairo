# V1-B — Alcance: Disponibilidad comercial y preparación de abastecimiento

Estado: decisiones de negocio confirmadas por el propietario del producto, 2026-09-19.
Implementación pendiente. No autoriza aún inicio de codificación sin revisión técnica previa por parte de quien implemente (Claude Code / Codex).

Este documento resuelve las decisiones comerciales que ADR-005 dejó explícitamente pendientes ("TTL y resolución comercial siguen pendientes: no se inventan ahora"). No reemplaza ADR-005; lo completa.

## 1. Frescura del dato (TTL de disponibilidad)

- La disponibilidad de una oferta de proveedor se considera **fresca** solo dentro de una ventana de tiempo configurable desde su `fecha de observación`.
- Frecuencia de actualización esperada una vez conectadas las APIs reales: **varias veces al día** (polling activo), sujeto a los límites técnicos que cada proveedor documente.
- El valor exacto del TTL **no se fija en este documento**. Debe implementarse como parámetro de configuración (no hardcodeado), ajustable sin cambio de código, para poder calibrarlo cuando se conozca la cadencia real que permita cada proveedor.
- Al vencer el TTL sin una nueva observación, el dato pasa a estado `vencido`.

## 2. Estados de disponibilidad

Siguiendo el contrato de ADR-005, un producto debe poder distinguir, con procedencia y fecha:

| Estado | Significado | Tratamiento en storefront |
| --- | --- | --- |
| `disponible` | Oferta activa, stock > 0, dato dentro del TTL | Visible, comprable, con cantidad exacta mostrada |
| `no disponible` | Oferta activa, stock = 0, dato dentro del TTL | Visible como agotado (no oculto) |
| `vencido` | Dato fuera del TTL, sin nueva observación | **Oculto** del catálogo público |
| `desconocido` | Nunca observado, o proveedor sin capacidad de consulta | **Oculto** del catálogo público |

Nunca se debe inferir `disponible` a partir de un timeout, error o ausencia de respuesta del proveedor.

## 3. Comportamiento ante caída de proveedor

- Si un proveedor deja de responder y varios productos de una categoría quedan `vencido` simultáneamente, es **aceptable** que el catálogo público de esa categoría se muestre vacío o reducido.
- No se requiere un mecanismo de fallback especial para distinguir "caída masiva" de "vencimiento individual": la misma regla (ocultar) aplica en ambos casos.
- Justificación de negocio: se prioriza no vender sin certeza de stock por sobre la completitud del catálogo mostrado.

## 4. Visualización de cantidad

- Cuando un producto está `disponible`, el storefront muestra la **cantidad exacta** en stock (ej. "Quedan 3 unidades").
- No se usan rangos ni umbrales de "stock bajo" en V1-B; se decide mostrar el número real en todos los casos de disponibilidad positiva.

## 5. Reserva local (soft hold) contra doble venta

- Agregar un producto al carrito genera una **reserva temporal local** de esa unidad.
- Duración de la reserva: **1 hora**.
- Si el cliente no completa el pago dentro de ese plazo, la reserva se libera automáticamente y la unidad vuelve a estar disponible para otros clientes.
- Esta reserva es **exclusivamente interna**: no implica ni comunica una reserva en el sistema del proveedor (Eurocomp u otro). Coherente con ADR-005: "reserva interna no equivale a reserva externa".
- La liberación debe ser atómica/segura ante concurrencia (evitar condiciones de carrera cuando dos reservas expiran o se confirman casi simultáneamente).

## 6. Fuera de alcance de este documento

Quedan explícitamente fuera de esta ronda de decisiones (a resolver en su momento, no inventar ahora):

- Revisión manual de ofertas vencidas o en estado `desconocido` (flujo administrativo).
- Selección entre múltiples ofertas de distintos proveedores para un mismo producto (aplica cuando haya más de un proveedor activo).
- Capacidades específicas de cada proveedor (consulta de stock vs. reserva vs. entrega directa) — corresponde a integración real de cada API.
- Modelo logístico (`direct_supplier` vs `via_operation`) — corresponde a V1-C en adelante.
- Cotización de envío y total final — V1-C.

## 7. Siguiente paso

Este documento debe revisarse técnicamente (contra el esquema de datos existente: `Product`, `Supplier`, `Supplier Offer`, `Cart`) antes de implementar. No autoriza por sí solo inicio de codificación de V1-B; se recomienda que quien implemente proponga un plan corto (migraciones aditivas, contratos/interfaces, tests) antes de escribir código, siguiendo la disciplina de trabajo ya establecida en el proyecto.

# ADR-006 · Proveedores y capacidades

Estado: aceptado, 2026-09-18.

Identificadores canónicos: `eurocomp`, `dataformas`, `cq-international`. Eurocomp es el nombre correcto y probablemente será la primera API real. La configuración deshabilitada `eurocom` se normaliza a `eurocomp`: la inspección no encontró consumidores de la clave anterior en app/rutas/tests/scripts, y el seeder comercial ya usa `eurocomp`. No se modifica ningún registro persistido ni se necesita migración por este cambio de configuración.

Mantener producto interno y múltiples ofertas separados. Un proveedor declara únicamente capacidades documentadas/verificadas. Preferir contratos pequeños para catálogo, disponibilidad, reservas y órdenes, implementados por adapters cuando exista un consumidor real. No exigir todas las funciones en una interfaz grande ni retornar éxitos ficticios.

V1-A no crea interfaces, HTTP clients, DTOs externos, credenciales ni respuestas simuladas. Dataformas y CQ no reciben configuraciones API inventadas. Documentación y credenciales de Eurocomp siguen pendientes.

En instalaciones con configuración cacheada, regenerar caché al desplegar. Si una revisión de datos posterior encuentra otro código histórico, preparar compatibilidad o migración aditiva específica; no renombrar filas por inferencia. Ver [ADR-010](010-external-integrations.md).

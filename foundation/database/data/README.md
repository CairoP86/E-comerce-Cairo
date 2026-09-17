# Catálogo territorial de Costa Rica

`cr-territories-2026.json` es un catálogo local versionado: 7 provincias, 84 cantones y 494 distritos. No se consulta una API al usar el checkout.

Fuente principal: [Registro Nacional / IGN, División Territorial Administrativa 2026](https://www.snitcr.go.cr/pdfs/ign_repositorio/DTA-TABLA%20POR%20PROVINCIA-CANT%C3%93N-DISTRITO%202026.pdf), consultada el 17 de septiembre de 2026. Los códigos territoriales son strings, no IDs inventados; el código distrital contiene el cantonal y este contiene el provincial.

La tabla de áreas del PDF contiene 493 filas distritales aunque su portada indica 494: termina en `70604` y omite `70605`. Se incorporó **70605, Duacarí, cantón 706 Guácimo, provincia 7 Limón**, contrastado con el [catálogo del Ministerio de Salud](https://www.ministeriodesalud.go.cr/fhir/CodeSystem-distritos-cs.html) y la [tabla de poblados del IGN](https://www.snitcr.go.cr/pdfs/ign_repositorio/DTA-POBLADOS-2023.pdf). Esta corrección es explícita; no se inventó una fila para alcanzar el total.

La prueba territorial verifica códigos únicos, totales y jerarquía de todas las filas. El backend valida la combinación exacta de los tres códigos. Cada dirección de pedido conserva códigos, nombres y versión `IGN-2026`; una actualización futura del catálogo no modifica pedidos históricos.

Para actualizar: contrastar una nueva publicación oficial, revisar altas/cambios y ejecutar las pruebas antes de sustituir el JSON. La fuente es un catálogo administrativo, no una garantía de cobertura logística.

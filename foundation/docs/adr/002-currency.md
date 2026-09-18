# ADR-002 · Moneda operativa CRC

Estado: aceptado, 2026-09-18.

Decisión: V1 opera en CRC, sin conversión automática ni mezcla de monedas. Importes enteros en unidades menores y snapshots con moneda explícita. La arquitectura conserva el campo moneda para ampliaciones futuras.

Estado real: catálogo, ofertas y tests heredados admiten CRC/USD; carrito rechaza mezcla. `config/commerce.php` ya define CRC. V1-A no elimina USD, altera precios ni reinterpreta pedidos. CRC es la política de lanzamiento, no una afirmación de que ya exista un bloqueo global de USD.

Consecuencia: antes de habilitar cobros, V1-C/E deberá aplicar explícitamente CRC en el flujo operativo y cotizaciones nuevas, conservando lectura de históricos USD y rechazo de incompatibilidades. Las pruebas USD actuales siguen como protección de compatibilidad; no se borran para ocultar esta distinción.

No existe tipo de cambio implícito. Una oferta en otra moneda no produce sugerencia para un producto CRC sin una futura decisión de conversión expresamente aprobada.

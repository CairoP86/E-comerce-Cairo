# Decisión arquitectónica — compra como invitado

Estado: aprobada por el propietario durante Fase 1B.

- Toda la navegación pública, búsqueda, filtros, categorías y fichas funciona sin autenticación.
- La compra como invitado será el flujo principal cuando se implementen carrito y checkout. No se exigirá registro ni inicio de sesión para comprar.
- La cuenta `customer` será opcional. Historial de pedidos, direcciones guardadas y seguimiento asociado a la cuenta son beneficios futuros, no funciones activas de 1B.
- Un pedido futuro debe poder conservar nombre, correo, teléfono y dirección sin depender de que exista un `user`. El vínculo con una cuenta será opcional y no sustituirá los datos del pedido.
- Asociar posteriormente pedidos a cuentas requerirá verificar la titularidad; conocer un correo no bastará. Definir acceso al seguimiento de invitados, retención y protección de datos en la fase correspondiente.
- “Mi cuenta” es navegación secundaria, nunca una barrera para explorar o comprar. El storefront no redirige al acceso al seleccionar un producto.

En 1B solo se registra esta decisión y se mantiene navegación pública. No se crean tablas de pedidos, datos personales de invitados, carrito, checkout ni seguimiento.

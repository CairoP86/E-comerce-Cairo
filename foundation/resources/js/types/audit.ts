/** The activity log stores stable keys; these are the words the operator reads. */
export const auditEvents: Record<string, string> = {
    'auth.login': 'Inicio de sesión', 'auth.login_failed': 'Intento de inicio fallido', 'auth.logout': 'Cierre de sesión',
    'auth.registered': 'Cuenta creada', 'auth.email_verified': 'Correo verificado', 'auth.password_reset': 'Contraseña restablecida',
    'user.role_changed': 'Rol cambiado',
    'catalog.created': 'Registro creado', 'catalog.updated': 'Registro actualizado', 'catalog.publication_changed': 'Publicación cambiada',
    'catalog.demo_archived': 'Demostración archivada', 'catalog.image_added': 'Imagen agregada', 'catalog.images_updated': 'Imágenes actualizadas',
    'catalog.image_archived': 'Imagen retirada', 'category.pricing_assigned': 'Regla asignada a una categoría',
    'commercial.price_applied': 'Precio sugerido aplicado', 'product.commercial_settings_changed': 'Preferencia comercial cambiada',
    'order.created': 'Pedido creado', 'order.viewed': 'Pedido consultado', 'order.marked_paid': 'Pedido marcado como pagado',
    'order.stock_committed': 'Stock descontado',
};

/** An event the map does not know shows as itself, rather than as a blank. */
export const auditLabel = (event: string) => auditEvents[event] ?? event;

export const auditSource = (value: string) => ({ web: 'Web', cli: 'Automático (CLI)' }[value] ?? value);

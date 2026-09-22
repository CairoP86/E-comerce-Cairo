<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AccountLayout from '../../layouts/AccountLayout.vue';
import { ago, dateTime } from '../../types/time';
interface Person { id: number; name: string; email: string }
interface Entry { id: number; event: string; actor_id: number | null; subject_id: number | null; actor: Person | null; subject: Person | null; source: string; created_at: string; metadata: { from_role?: string; to_role?: string; entity_type?: string; entity_id?: number; from_status?: string; to_status?: string; changed_fields?: string[]; image_id?: number; quantity?: number; from_stock?: number; to_stock?: number } }
defineProps<{ entries: { data: Entry[]; prev_page_url: string | null; next_page_url: string | null; current_page: number } }>();

// The log stores stable keys; this is how the operator reads them. Unknown keys show as they are.
const events: Record<string, string> = {
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
const label = (event: string) => events[event] ?? event;
const source = (value: string) => ({ web: 'Web', cli: 'Automático (CLI)' }[value] ?? value);

/** Everything the entry knows beyond who and when, in the words of the admin. */
function details(entry: Entry): string[] {
    const m = entry.metadata ?? {}; const out: string[] = [];
    if (m.from_role || m.to_role) out.push(`Rol: ${m.from_role ?? '—'} → ${m.to_role ?? '—'}`);
    if (m.entity_type) out.push(`${m.entity_type} #${m.entity_id}`);
    if (m.from_status || m.to_status) out.push(`Estado: ${m.from_status ?? '—'} → ${m.to_status ?? '—'}`);
    if (m.from_stock !== undefined || m.to_stock !== undefined) out.push(`Stock: ${m.from_stock ?? '—'} → ${m.to_stock ?? '—'}`);
    if (m.quantity !== undefined) out.push(`Cantidad: ${m.quantity}`);
    if (m.image_id) out.push(`Imagen #${m.image_id}`);
    if (m.changed_fields?.length) out.push(`Campos: ${m.changed_fields.join(', ')}`);
    return out;
}
</script>
<template><Head title="Auditoría"/><AccountLayout title="Registro de actividad" section="admin">
    <p class="muted">Historial de seguridad. Horario de Costa Rica. Solo lectura.</p>

    <div class="table-wrap data-table audit-table"><table><thead><tr><th>Evento</th><th>Quién</th><th>Cuenta afectada</th><th>Detalle</th><th>Origen</th><th>Fecha</th></tr></thead><tbody>
        <tr v-for="entry in entries.data" :key="entry.id" :class="{ 'is-failure': entry.event === 'auth.login_failed' }">
            <td class="product-cell"><span class="product-name">{{ label(entry.event) }}</span><small class="block muted code">{{ entry.event }}</small></td>
            <td data-label="Quién">
                <template v-if="entry.actor">{{ entry.actor.name }}<small class="block muted">{{ entry.actor.email }}</small></template>
                <span v-else class="muted">{{ entry.source === 'cli' ? 'Proceso automático' : '—' }}</span>
            </td>
            <td data-label="Cuenta afectada">
                <!-- Signing in is about yourself: repeating the same person in both columns says nothing. -->
                <span v-if="entry.subject && entry.subject.id === entry.actor?.id" class="muted">La misma persona</span>
                <template v-else-if="entry.subject">{{ entry.subject.name }}<small class="block muted">{{ entry.subject.email }}</small></template>
                <span v-else class="muted">—</span>
            </td>
            <td data-label="Detalle" class="audit-detail">
                <template v-if="details(entry).length"><span v-for="line in details(entry)" :key="line" class="block">{{ line }}</span></template>
                <span v-else class="muted">—</span>
            </td>
            <td data-label="Origen">{{ source(entry.source) }}</td>
            <td data-label="Fecha" class="audit-when">{{ dateTime(entry.created_at) }}<small class="block muted">{{ ago(entry.created_at) }}</small></td>
        </tr>
        <tr v-if="!entries.data.length" class="empty-row"><td colspan="6">Todavía no hay actividad registrada.</td></tr>
    </tbody></table></div>

    <nav class="pagination" aria-label="Páginas del registro"><Link v-if="entries.prev_page_url" :href="entries.prev_page_url">← Anterior</Link><span>Página {{ entries.current_page }}</span><Link v-if="entries.next_page_url" :href="entries.next_page_url">Siguiente →</Link></nav>
</AccountLayout></template>

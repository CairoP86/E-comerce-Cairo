<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import AccountLayout from '../../../layouts/AccountLayout.vue';
import StatusBadge from '../../../components/StatusBadge.vue';
import { money } from '../../../types/catalog';
import { ago, dateTime } from '../../../types/time';
type Estado = 'pendientes' | 'pagados';
interface Row { number: string; created_at: string; customer: string; total_minor: number; currency: string; status: string; status_label: string }
const props = defineProps<{ orders: { data: Row[]; prev_page_url: string | null; next_page_url: string | null }; orderCounts: { pending: number; paid: number; total: number }; filters: { estado?: Estado } }>();
const estado = computed(() => props.filters.estado ?? '');
const filterBy = (value: Estado | '') => router.get('/admin/orders', value ? { estado: value } : {}, { preserveState: true });
</script>
<template><Head title="Pedidos"><meta name="robots" content="noindex, nofollow"/></Head><AccountLayout title="Pedidos" section="admin">
    <nav v-if="orderCounts.total" class="filter-strip" aria-label="Filtrar por estado del pedido">
        <span class="filter-strip-title">Pedidos</span>
        <button type="button" class="strip-segment" :aria-pressed="!estado" @click="filterBy('')"><span class="strip-count">{{ orderCounts.total }}</span> en total</button>
        <button type="button" class="strip-segment is-pending" :aria-pressed="estado === 'pendientes'" @click="filterBy(estado === 'pendientes' ? '' : 'pendientes')"><span class="strip-count">{{ orderCounts.pending }}</span> por confirmar pago</button>
        <button type="button" class="strip-segment is-paid" :aria-pressed="estado === 'pagados'" @click="filterBy(estado === 'pagados' ? '' : 'pagados')"><span class="strip-count">{{ orderCounts.paid }}</span> {{ orderCounts.paid === 1 ? 'pagado' : 'pagados' }}</button>
    </nav>

    <div class="table-wrap data-table"><table><thead><tr><th>Pedido</th><th>Fecha</th><th>Cliente</th><th class="num">Total</th><th>Estado</th><th><span class="visually-hidden">Acción</span></th></tr></thead><tbody>
        <tr v-for="order in orders.data" :key="order.number" :class="{ 'is-pending': order.status === 'pending_payment' }">
            <td class="product-cell"><Link :href="`/admin/orders/${order.number}`" class="product-name code">{{ order.number }}</Link></td>
            <td data-label="Fecha">{{ dateTime(order.created_at) }}<small v-if="order.status === 'pending_payment'" class="block muted">espera {{ ago(order.created_at) }}</small></td>
            <td data-label="Cliente">{{ order.customer }}</td>
            <td class="num" data-label="Total">{{ money(order.total_minor, order.currency) }}<small class="block muted">{{ order.currency }}</small></td>
            <td data-label="Estado"><StatusBadge :status="order.status" :label="order.status_label"/></td>
            <td class="action-cell"><Link :href="`/admin/orders/${order.number}`" class="quiet-link">Ver pedido</Link></td>
        </tr>
        <tr v-if="!orders.data.length" class="empty-row"><td colspan="6">
            <template v-if="estado">Ningún pedido {{ estado === 'pendientes' ? 'está por confirmar pago' : 'está pagado' }}. <button type="button" class="quiet-link" @click="filterBy('')">Ver todos</button></template>
            <template v-else>Todavía no hay pedidos.</template>
        </td></tr>
    </tbody></table></div>

    <p class="muted list-note">Consulta de pedidos. El contenido histórico no se puede editar.</p>
    <nav class="pagination" aria-label="Páginas de pedidos"><Link v-if="orders.prev_page_url" :href="orders.prev_page_url">← Anterior</Link><Link v-if="orders.next_page_url" :href="orders.next_page_url">Siguiente →</Link></nav>
</AccountLayout></template>

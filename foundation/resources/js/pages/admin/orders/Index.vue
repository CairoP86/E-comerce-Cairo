<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AccountLayout from '../../../layouts/AccountLayout.vue';
import { money } from '../../../types/catalog';
import StatusBadge from '../../../components/StatusBadge.vue';
defineProps<{ orders: { data: { number: string; created_at: string; customer: string; total_minor: number; currency: string; status: string; status_label: string }[]; prev_page_url: string | null; next_page_url: string | null } }>();
</script>
<template><Head title="Pedidos"><meta name="robots" content="noindex, nofollow"/></Head><AccountLayout title="Pedidos" section="admin"><p>Consulta de pedidos. El contenido histórico no se puede editar.</p><p v-if="!orders.data.length">Todavía no hay pedidos.</p><article v-for="order in orders.data" :key="order.number" class="info-card"><h2><Link :href="`/admin/orders/${order.number}`">{{ order.number }}</Link></h2><p>{{ new Date(order.created_at).toLocaleString('es-CR') }} · {{ order.customer }}</p><p>{{ money(order.total_minor, order.currency) }} · {{ order.currency }} · <StatusBadge :status="order.status" :label="order.status_label"/></p></article><nav aria-label="Páginas de pedidos"><Link v-if="orders.prev_page_url" :href="orders.prev_page_url">← Anterior</Link> <Link v-if="orders.next_page_url" :href="orders.next_page_url">Siguiente →</Link></nav></AccountLayout></template>

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AccountLayout from '../../../layouts/AccountLayout.vue';
import OrderDetails from '../../../components/storefront/OrderDetails.vue';
import type { Order } from '../../../types/order';
defineProps<{ order: Order }>();
</script>
<template><Head title="Detalle del pedido"><meta name="robots" content="noindex, nofollow"/></Head><AccountLayout title="Detalle del pedido" section="admin"><Link href="/admin/orders">← Pedidos</Link><div class="storefront st-admin-order"><h2 class="st-order-number">{{ order.number }}</h2><p>{{ order.status_label }} · {{ new Date(order.created_at).toLocaleString('es-CR') }}</p><OrderDetails :order="order"/></div></AccountLayout></template>

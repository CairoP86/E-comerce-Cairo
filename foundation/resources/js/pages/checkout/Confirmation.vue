<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import StoreLayout from '../../layouts/StoreLayout.vue';
import OrderDetails from '../../components/storefront/OrderDetails.vue';
import type { Order } from '../../types/order';
defineProps<{ order: Order }>();
</script>
<template><Head title="Pedido recibido"><meta name="robots" content="noindex, nofollow"/></Head><StoreLayout><header class="st-cart-heading"><div><p class="st-overline">GRACIAS POR ELEGIRNOS</p><h1>Pedido recibido.</h1><p class="st-order-number">{{ order.number }}</p><p role="status">{{ order.status_label }}. El pago todavía no ha sido procesado.</p><p>Conserva tu número de pedido. Esta confirmación está disponible en tu sesión actual.</p></div></header><OrderDetails :order="order"/><p class="st-cart-help">El transporte y los cargos adicionales no están calculados. No se ha reservado inventario ni confirmado una fecha de entrega.</p><Link href="/catalog" class="st-button">Volver al catálogo</Link></StoreLayout></template>

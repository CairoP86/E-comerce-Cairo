<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import StoreLayout from '../../layouts/StoreLayout.vue';
import CheckoutSteps from '../../components/storefront/CheckoutSteps.vue';
import OrderDetails from '../../components/storefront/OrderDetails.vue';
import type { Order } from '../../types/order';
defineProps<{ order: Order }>();
</script>
<template><Head title="Pedido recibido"><meta name="robots" content="noindex, nofollow"/></Head><StoreLayout><CheckoutSteps :current="3"/>
        <header class="st-cart-heading"><div><p class="st-overline">GRACIAS POR ELEGIRNOS</p><h1>Pedido recibido.</h1><p class="st-order-number">{{ order.number }}</p><p role="status">{{ order.status_label }}. El pago todavía no ha sido procesado.</p><p>Conserva tu número de pedido. Esta confirmación está disponible en tu sesión actual.</p></div></header><OrderDetails :order="order"/><p class="st-cart-help">Este total es el que pagarás; el envío ya está incluido. Aún no se ha procesado el pago ni se ha confirmado una fecha de entrega.</p><Link href="/catalog" class="st-button">Volver al catálogo</Link></StoreLayout></template>

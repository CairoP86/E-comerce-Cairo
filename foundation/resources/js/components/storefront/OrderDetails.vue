<script setup lang="ts">
import type { Order } from '../../types/order';
import OrderSummary from './OrderSummary.vue';
defineProps<{ order: Order }>();
</script>
<template><div class="st-checkout-grid"><div class="st-checkout-fields"><section class="st-checkout-section"><h2>Comprador</h2><p>{{ order.buyer.first_name }} {{ order.buyer.last_name }}</p><p>{{ order.buyer.email }}</p><p>{{ order.buyer.phone }}</p></section><section class="st-checkout-section"><h2>Dirección de entrega</h2><p>Costa Rica · {{ order.address.province }} · {{ order.address.canton }} · {{ order.address.district }}</p><p class="st-address-text">{{ order.address.exact_address }}</p><p v-if="order.address.additional" class="st-address-text">{{ order.address.additional }}</p></section></div><OrderSummary :items="order.items" :currency="order.currency" :subtotal="order.subtotal_minor" :shipping="order.shipping" :total="order.total_minor" :tax="order.tax"/></div></template>

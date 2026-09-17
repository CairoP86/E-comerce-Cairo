<script setup lang="ts">
import { money } from '../../types/catalog';
import type { OrderItem } from '../../types/order';
defineProps<{ items: OrderItem[]; currency: string; total: number }>();
</script>
<template><section class="st-order-summary" aria-label="Resumen de productos"><h2>Tu pedido, en claro.</h2><ul><li v-for="item in items" :key="item.sku"><div><strong>{{ item.name }}</strong><small>{{ item.sku }} · {{ item.quantity }} × {{ money(item.unit_price_minor, currency) }}</small><small v-if="item.is_demo">Producto de demostración</small></div><strong>{{ money(item.subtotal_minor, currency) }}</strong></li></ul><dl><div><dt>Total de productos</dt><dd>{{ money(total, currency) }}</dd></div></dl><p class="st-cart-help">{{ currency }} · Importe de productos. Transporte y cargos adicionales no calculados. No se ha procesado ningún pago.</p></section></template>

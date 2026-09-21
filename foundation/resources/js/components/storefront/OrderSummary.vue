<script setup lang="ts">
import { money } from '../../types/catalog';
import type { OrderItem, OrderShipping, ShippingQuote } from '../../types/order';
// `shipping` is null for orders placed before delivery quoting existed, and in the checkout
// review until a canton is chosen. Both cases show the product amount without claiming more.
defineProps<{ items: OrderItem[]; currency: string; subtotal: number; shipping: ShippingQuote | OrderShipping | null; total: number }>();
</script>
<template><section class="st-order-summary" aria-label="Resumen del pedido"><h2>Tu pedido, en claro.</h2><ul><li v-for="item in items" :key="item.sku"><div><strong>{{ item.name }}</strong><small>{{ item.sku }} · {{ item.quantity }} × {{ money(item.unit_price_minor, currency) }}</small><small v-if="item.is_demo">Producto de demostración</small></div><strong>{{ money(item.subtotal_minor, currency) }}</strong></li></ul><dl>
    <div><dt>Productos</dt><dd>{{ money(subtotal, currency) }}</dd></div>
    <div v-if="shipping"><dt>Envío<span v-if="shipping.zone_label"> · {{ shipping.zone_label }}</span></dt><dd>{{ shipping.free ? 'Gratis' : money(shipping.amount_minor, currency) }}</dd></div>
    <div v-else><dt>Envío</dt><dd>Sin calcular</dd></div>
    <div class="st-summary-total"><dt>Total</dt><dd>{{ money(total, currency) }}</dd></div>
</dl><p class="st-cart-help">{{ currency }} · Total final del pedido. No incluye impuestos, que aún no se aplican. No se ha procesado ningún pago.</p></section></template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AccountLayout from '../../../layouts/AccountLayout.vue';
import { money } from '../../../types/catalog';
import StatusBadge from '../../../components/StatusBadge.vue';
import type { Order } from '../../../types/order';
const props = defineProps<{ order: Order; canMarkPaid: boolean }>();
const confirming = ref(false);
const form = useForm({});
// The payment refusal arrives on the `order` key; the form itself carries no fields.
const paymentError = computed(() => (form.errors as Partial<Record<'order', string>>).order);
function markPaid() {
    form.post(`/admin/orders/${props.order.number}/paid`, { preserveScroll: true, onFinish: () => { confirming.value = false; } });
}
</script>
<!-- Operator view. It does not reuse the storefront OrderSummary: that component speaks to the buyer. -->
<template><Head title="Detalle del pedido"><meta name="robots" content="noindex, nofollow"/></Head><AccountLayout title="Detalle del pedido" section="admin">
    <Link href="/admin/orders" class="quiet-link">← Pedidos</Link>
    <header class="admin-order-head">
        <h2 class="admin-order-number code">{{ order.number }}</h2>
        <p class="muted"><StatusBadge :status="order.status" :label="order.status_label"/> · {{ new Date(order.created_at).toLocaleString('es-CR') }}</p>
    </header>

    <section v-if="canMarkPaid && order.status === 'pending_payment'" class="admin-callout" aria-labelledby="mark-paid-title">
        <h3 id="mark-paid-title">Registrar pago confirmado</h3>
        <p>Úsalo solo cuando hayas verificado el pago fuera de la plataforma. Descuenta el stock de la oferta, queda registrado con tu usuario en el historial del pedido y no genera ningún cobro en línea.</p>
        <p v-if="paymentError" class="admin-callout-error" role="alert">{{ paymentError }}</p>
        <div v-if="!confirming" class="admin-callout-actions"><button type="button" class="button small" @click="confirming = true">Marcar como pagado</button></div>
        <template v-else>
            <p><strong>¿Confirmas que recibiste el pago de este pedido?</strong></p>
            <div class="admin-callout-actions">
                <button type="button" class="button small" :disabled="form.processing" @click="markPaid">{{ form.processing ? 'Registrando…' : 'Sí, marcar como pagado' }}</button>
                <button type="button" class="quiet-link" :disabled="form.processing" @click="confirming = false">Cancelar</button>
            </div>
        </template>
    </section>

    <div class="admin-order-grid">
        <section class="info-card" aria-labelledby="buyer-title">
            <p id="buyer-title" class="eyebrow">COMPRADOR</p>
            <p class="admin-strong">{{ order.buyer.first_name }} {{ order.buyer.last_name }}</p>
            <p>{{ order.buyer.email }}</p>
            <p>{{ order.buyer.phone }}</p>
        </section>
        <section class="info-card" aria-labelledby="address-title">
            <p id="address-title" class="eyebrow">DIRECCIÓN DE ENTREGA</p>
            <p class="admin-strong">{{ order.address.province }} · {{ order.address.canton }} · {{ order.address.district }}</p>
            <p class="admin-address">{{ order.address.exact_address }}</p>
            <p v-if="order.address.additional" class="admin-address">{{ order.address.additional }}</p>
        </section>
    </div>

    <section class="info-card admin-order-items" aria-labelledby="items-title">
        <p id="items-title" class="eyebrow">PRODUCTOS</p>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Producto</th><th>SKU</th><th class="num">Cantidad</th><th class="num">Precio unitario</th><th class="num">Subtotal</th></tr></thead>
                <tbody>
                    <tr v-for="item in order.items" :key="item.sku">
                        <td class="admin-item-name">{{ item.name }}<small v-if="item.is_demo"> · producto de demostración</small></td>
                        <td class="code">{{ item.sku }}</td>
                        <td class="num">{{ item.quantity }}</td>
                        <td class="num">{{ money(item.unit_price_minor, order.currency) }}</td>
                        <td class="num">{{ money(item.subtotal_minor, order.currency) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <dl class="admin-totals">
            <div><dt>Productos</dt><dd>{{ money(order.subtotal_minor, order.currency) }}</dd></div>
            <div v-if="order.shipping"><dt>Envío<span v-if="order.shipping.zone_label"> · {{ order.shipping.zone_label }}</span></dt><dd>{{ order.shipping.free ? 'Gratis' : money(order.shipping.amount_minor, order.currency) }}</dd></div>
            <div v-else><dt>Envío</dt><dd>Sin cotizar (pedido anterior al cálculo de envío)</dd></div>
            <div class="admin-total"><dt>Total</dt><dd>{{ money(order.total_minor, order.currency) }}</dd></div>
            <div v-if="order.tax.amount_minor" class="admin-tax"><dt>IVA incluido ({{ order.tax.rate_percent }}%) sobre los productos</dt><dd>{{ money(order.tax.amount_minor, order.currency) }}</dd></div>
        </dl>
    </section>
</AccountLayout></template>

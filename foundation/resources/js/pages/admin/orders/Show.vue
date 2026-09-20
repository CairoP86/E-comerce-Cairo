<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AccountLayout from '../../../layouts/AccountLayout.vue';
import OrderDetails from '../../../components/storefront/OrderDetails.vue';
import type { Order } from '../../../types/order';
const props = defineProps<{ order: Order; canMarkPaid: boolean }>();
const confirming = ref(false);
const form = useForm({});
function markPaid() {
    form.post(`/admin/orders/${props.order.number}/paid`, { preserveScroll: true, onFinish: () => { confirming.value = false; } });
}
</script>
<template><Head title="Detalle del pedido"><meta name="robots" content="noindex, nofollow"/></Head><AccountLayout title="Detalle del pedido" section="admin"><Link href="/admin/orders">← Pedidos</Link><div class="storefront st-admin-order"><h2 class="st-order-number">{{ order.number }}</h2><p>{{ order.status_label }} · {{ new Date(order.created_at).toLocaleString('es-CR') }}</p>
    <section v-if="canMarkPaid && order.status === 'pending_payment'" class="notice">
        <h3>Registrar pago confirmado</h3>
        <p>Úsalo solo cuando hayas verificado el pago fuera de la plataforma. Queda registrado con tu usuario en el historial del pedido y no genera ningún cobro en línea.</p>
        <button v-if="!confirming" type="button" class="button small" @click="confirming = true">Marcar como pagado</button>
        <div v-else>
            <p><strong>¿Confirmas que recibiste el pago de este pedido?</strong></p>
            <button type="button" class="button small" :disabled="form.processing" @click="markPaid">{{ form.processing ? 'Registrando…' : 'Sí, marcar como pagado' }}</button>
            <button type="button" class="quiet-link" :disabled="form.processing" @click="confirming = false">Cancelar</button>
        </div>
    </section>
    <OrderDetails :order="order"/></div></AccountLayout></template>

<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { LayoutDashboard } from '@lucide/vue';
import AccountLayout from '../../layouts/AccountLayout.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import { money } from '../../types/catalog';
import { ago, dateTime } from '../../types/time';
import type { SharedProps } from '../../types/auth';
interface OrderLine { number: string; created_at: string; total_minor: number; currency: string; status: string; status_label: string }
interface SignIn { id: number; created_at: string; source: string }
defineProps<{ context: { orders?: OrderLine[]; sign_ins?: SignIn[] } }>();
const page = usePage<SharedProps>();
const user = computed(() => page.props.auth.user);
const isStaff = computed(() => !!user.value && user.value.role !== 'customer');
const roleLabel = computed(() => ({ admin: 'Administrador', operator: 'Operador', customer: 'Cliente' })[user.value?.role ?? 'customer']);
// Two letters at most, like the brand mark: "Ana María Prueba" reads "AM".
const initials = computed(() => (user.value?.name ?? '').trim().split(/\s+/).slice(0, 2).map(w => w.charAt(0).toLocaleUpperCase('es')).join(''));
// For staff this page is a way through to the panel, so the shortcut already says what is waiting there.
const turn = computed(() => page.props.operatorTurn ?? { orders: 0, offers: 0 });
const waiting = computed(() => [
    turn.value.orders && `${turn.value.orders} ${turn.value.orders === 1 ? 'pedido por confirmar pago' : 'pedidos por confirmar pago'}`,
    turn.value.offers && `${turn.value.offers} ${turn.value.offers === 1 ? 'producto con oferta por actualizar' : 'productos con oferta por actualizar'}`,
].filter(Boolean).join(' · '));
</script>
<template><Head title="Mi cuenta"/><AccountLayout title="Tu espacio personal">
    <section class="identity" aria-label="Datos de la cuenta">
        <span class="identity-mark" aria-hidden="true">{{ initials }}</span>
        <div class="identity-text">
            <p class="identity-name">{{ user?.name }}</p>
            <p class="identity-mail">{{ user?.email }} <span class="verified">Correo verificado</span></p>
            <p v-if="isStaff" class="identity-role">{{ roleLabel }}</p>
        </div>
    </section>

    <Link v-if="isStaff" href="/admin" class="panel-shortcut" :class="{ 'is-turn': waiting }">
        <span class="panel-shortcut-icon"><LayoutDashboard :size="20" :stroke-width="1.75" aria-hidden="true"/></span>
        <span class="panel-shortcut-text"><strong>Ir al panel operativo</strong><small>{{ waiting || 'Todo al día: no hay pedidos por confirmar ni ofertas vencidas.' }}</small></span>
        <span class="panel-shortcut-arrow" aria-hidden="true">→</span>
    </Link>
    <section v-if="context.orders" class="account-context" aria-labelledby="orders-title">
        <p id="orders-title" class="eyebrow">TUS PEDIDOS RECIENTES</p>
        <ul v-if="context.orders.length" class="account-list">
            <li v-for="order in context.orders" :key="order.number">
                <span class="code account-strong">{{ order.number }}</span>
                <StatusBadge :status="order.status" :label="order.status_label"/>
                <span class="account-when muted">{{ dateTime(order.created_at) }}</span>
                <span class="account-amount">{{ money(order.total_minor, order.currency) }}</span>
            </li>
        </ul>
        <p v-else class="muted">Todavía no hiciste pedidos con esta cuenta.</p>
        <p class="muted account-note">Los pedidos que hagas con la sesión iniciada quedan asociados a tu cuenta, y tu correo se completa solo al finalizar la compra.</p>
    </section>

    <section v-if="context.sign_ins" class="account-context" aria-labelledby="signins-title">
        <p id="signins-title" class="eyebrow">TUS ÚLTIMOS ACCESOS</p>
        <ul v-if="context.sign_ins.length" class="account-list">
            <li v-for="entry in context.sign_ins" :key="entry.id">
                <span class="account-strong">{{ dateTime(entry.created_at) }}</span>
                <span class="account-when muted">{{ ago(entry.created_at) }}</span>
                <span class="muted">{{ entry.source === 'cli' ? 'Automático (CLI)' : 'Web' }}</span>
            </li>
        </ul>
        <p v-else class="muted">Este es tu primer acceso registrado.</p>
        <p class="muted account-note">Si ves un acceso que no reconocés, cambiá tu contraseña. El historial completo vive en Auditoría.</p>
    </section>
</AccountLayout></template>

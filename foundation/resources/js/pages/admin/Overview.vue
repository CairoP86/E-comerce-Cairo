<script setup lang="ts">
import { computed, type Component } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { CircleCheck, Clock, PackageX, ReceiptText, TimerOff } from '@lucide/vue';
import AccountLayout from '../../layouts/AccountLayout.vue';
import type { SharedProps } from '../../types/auth';
import type { FreshnessSummary } from '../../types/freshness';
import { ago } from '../../types/time';
const props = defineProps<{ pendingOrders: number; freshnessSummary: FreshnessSummary; oldestPendingOrder: { number: string; created_at: string } | null }>();
const page = usePage<SharedProps>();

interface TurnRow { key: string; tone: 'turn' | 'danger'; icon: Component; figure: number; label: string; note: string; href: string; action: string }
// Ordered by urgency: a customer waiting to pay, then products the store cannot sell because their data is broken, then routine refreshes.
const rows = computed<TurnRow[]>(() => {
    const { expired, invalid } = props.freshnessSummary; const orders = props.pendingOrders;
    return [
        orders && { key: 'orders', tone: 'turn', icon: ReceiptText, figure: orders, label: orders === 1 ? 'pedido por confirmar pago' : 'pedidos por confirmar pago', note: '', href: '/admin/orders', action: 'Revisar pedidos →' },
        invalid && { key: 'invalid', tone: 'danger', icon: PackageX, figure: invalid, label: invalid === 1 ? 'producto publicado sin oferta válida' : 'productos publicados sin oferta válida', note: `${invalid === 1 ? 'No aparece' : 'No aparecen'} en la tienda: falta una oferta con datos válidos.`, href: '/admin/catalog/products?vigencia=sin-dato', action: 'Revisar productos →' },
        expired && { key: 'expired', tone: 'turn', icon: TimerOff, figure: expired, label: expired === 1 ? 'oferta vencida' : 'ofertas vencidas', note: `${expired === 1 ? 'El producto ya no aparece' : 'Esos productos ya no aparecen'} en la tienda hasta que confirmes precio y stock con el proveedor.`, href: '/admin/catalog/products?vigencia=vencidas', action: 'Actualizar ofertas →' },
    ].filter(Boolean) as TurnRow[];
});
// Broken data outranks routine work: one product without a valid offer makes the whole card "blocked".
const state = computed(() => props.freshnessSummary.invalid ? 'blocked' : rows.value.length ? 'turn' : 'calm');

// Costa Rica writes "setiembre"; the browser's Spanish says "septiembre".
const today = new Intl.DateTimeFormat('es-CR', { weekday: 'long', day: 'numeric', month: 'long', timeZone: 'America/Costa_Rica' }).format(new Date()).replace('septiembre', 'setiembre');
const roleLabel = computed(() => ({ admin: 'Administrador', operator: 'Operador', customer: 'Cliente' })[page.props.auth.user?.role ?? 'customer']);
</script>
<template><Head title="Panel operativo"/><AccountLayout title="Panel operativo" section="admin">
    <section class="turn-card" :class="`is-${state}`" aria-labelledby="turn-title">
        <header class="turn-head">
            <p id="turn-title" class="eyebrow">{{ state === 'calm' ? 'TODO AL DÍA' : 'TU TURNO' }}</p>
            <p class="turn-date">{{ today }}</p>
        </header>

        <template v-if="state === 'calm'">
            <div class="turn-calm">
                <span class="turn-tile is-ok"><CircleCheck :size="20" :stroke-width="1.75" aria-hidden="true"/></span>
                <div>
                    <p class="turn-calm-title">No hay pedidos por confirmar ni ofertas vencidas.</p>
                    <p class="turn-note">Todos los productos publicados se pueden vender.</p>
                </div>
            </div>
        </template>

        <ul v-else class="turn-rows">
            <li v-for="row in rows" :key="row.key" class="turn-row" :class="`is-${row.tone}`">
                <span class="turn-tile"><component :is="row.icon" :size="20" :stroke-width="1.75" aria-hidden="true"/></span>
                <p class="turn-figure">{{ row.figure }}</p>
                <div class="turn-text">
                    <p class="turn-label">{{ row.label }}</p>
                    <p v-if="row.key === 'orders' && oldestPendingOrder" class="turn-note">
                        El que más espera: <Link :href="`/admin/orders/${oldestPendingOrder.number}`" class="quiet-link code">{{ oldestPendingOrder.number }}</Link>, {{ ago(oldestPendingOrder.created_at) }}.
                    </p>
                    <p v-else-if="row.note" class="turn-note">{{ row.note }}</p>
                </div>
                <Link :href="row.href" class="quiet-link turn-action">{{ row.action }}</Link>
            </li>
        </ul>

        <p v-if="freshnessSummary.expiring" class="turn-soon">
            <Clock :size="15" :stroke-width="1.75" aria-hidden="true"/>
            <span>Pronto: {{ freshnessSummary.expiring }} {{ freshnessSummary.expiring === 1 ? 'oferta está por vencer' : 'ofertas están por vencer' }}.</span>
            <Link href="/admin/catalog/products?vigencia=por-vencer" class="quiet-link">Ver cuáles →</Link>
        </p>
    </section>
    <p class="panel-foot muted">Sesión de {{ roleLabel }} · Horario de Costa Rica</p>
</AccountLayout></template>

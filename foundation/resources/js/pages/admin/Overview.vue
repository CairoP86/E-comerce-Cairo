<script setup lang="ts">
import { computed, type Component } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { CircleCheck, Clock, PackageX, ReceiptText, TimerOff } from '@lucide/vue';
import AccountLayout from '../../layouts/AccountLayout.vue';
import OrdersChart, { type Day } from '../../components/OrdersChart.vue';
import type { SharedProps } from '../../types/auth';
import type { FreshnessSummary } from '../../types/freshness';
import { money } from '../../types/catalog';
import { ago } from '../../types/time';
import { auditLabel } from '../../types/audit';
interface Activity { id: number; event: string; source: string; created_at: string; actor: { id: number; name: string; email: string } | null; metadata: { entity_type?: string } }
interface Snapshot { days: number; catalogue: { published: number; draft: number; archived: number; demo: number }; suppliers: { total: number; active: number }; series: Day[]; paid: { orders: number; totals: { currency: string; total_minor: number }[] }; activity: Activity[] | null }
const props = defineProps<{ pendingOrders: number; freshnessSummary: FreshnessSummary; oldestPendingOrder: { number: string; created_at: string } | null; snapshot: Snapshot }>();
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

const catalogue = computed(() => [
    { key: 'published', value: props.snapshot.catalogue.published, label: props.snapshot.catalogue.published === 1 ? 'producto publicado' : 'productos publicados', href: '/admin/catalog/products?status=published' },
    { key: 'draft', value: props.snapshot.catalogue.draft, label: props.snapshot.catalogue.draft === 1 ? 'borrador' : 'borradores', href: '/admin/catalog/products?status=draft' },
    { key: 'demo', value: props.snapshot.catalogue.demo, label: props.snapshot.catalogue.demo === 1 ? 'de demostración, oculto' : 'de demostración, ocultos', href: '/admin/catalog/products?demo=1' },
    { key: 'suppliers', value: props.snapshot.suppliers.active, label: props.snapshot.suppliers.active === 1 ? 'proveedor activo' : 'proveedores activos', href: '/admin/commercial/suppliers' },
]);
const setRange = (days: number) => router.get('/admin', days === 14 ? {} : { dias: days }, { preserveState: true, preserveScroll: true });
</script>
<template><Head title="Panel operativo"/><AccountLayout title="Panel operativo" section="admin">
    <div class="panel-grid">
        <section class="turn-card" :class="`is-${state}`" aria-labelledby="turn-title">
            <header class="turn-head">
                <p id="turn-title" class="eyebrow">{{ state === 'calm' ? 'TODO AL DÍA' : 'TU TURNO' }}</p>
                <p class="turn-date">{{ today }}</p>
            </header>

            <div v-if="state === 'calm'" class="turn-calm">
                <span class="turn-tile is-ok"><CircleCheck :size="20" :stroke-width="1.75" aria-hidden="true"/></span>
                <div>
                    <p class="turn-calm-title">No hay pedidos por confirmar ni ofertas vencidas.</p>
                    <p class="turn-note">Todos los productos publicados se pueden vender.</p>
                </div>
            </div>

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

        <section class="panel-card" aria-labelledby="catalogue-title">
            <p id="catalogue-title" class="eyebrow">CATÁLOGO HOY</p>
            <ul class="count-list">
                <li v-for="item in catalogue" :key="item.key">
                    <Link :href="item.href" class="count-row">
                        <span class="count-figure">{{ item.value }}</span>
                        <span class="count-label">{{ item.label }}</span>
                    </Link>
                </li>
            </ul>
        </section>

        <section class="panel-card is-chart" aria-labelledby="chart-title">
            <header class="panel-card-head">
                <p id="chart-title" class="eyebrow">PEDIDOS POR DÍA</p>
                <div class="range-switch" role="group" aria-label="Rango del gráfico">
                    <button v-for="days in [14, 30]" :key="days" type="button" class="strip-segment" :aria-pressed="snapshot.days === days" @click="setRange(days)">{{ days }} días</button>
                </div>
            </header>
            <OrdersChart :days="snapshot.series" :range="snapshot.days"/>
            <p class="panel-card-foot">
                <template v-if="snapshot.paid.totals.length">
                    Confirmado en {{ snapshot.days }} días:
                    <strong v-for="total in snapshot.paid.totals" :key="total.currency">{{ money(total.total_minor, total.currency) }}</strong>
                    · {{ snapshot.paid.orders }} {{ snapshot.paid.orders === 1 ? 'pedido' : 'pedidos' }}
                </template>
                <template v-else>Todavía no hay pedidos confirmados en {{ snapshot.days }} días.</template>
            </p>
        </section>

        <section v-if="snapshot.activity" class="panel-card" aria-labelledby="activity-title">
            <p id="activity-title" class="eyebrow">ACTIVIDAD RECIENTE</p>
            <ul v-if="snapshot.activity.length" class="activity-list">
                <li v-for="entry in snapshot.activity" :key="entry.id">
                    <p class="activity-what">{{ auditLabel(entry.event) }}</p>
                    <p class="activity-who muted">{{ entry.actor?.name ?? (entry.source === 'cli' ? 'Proceso automático' : 'Sin registro de quién') }} · {{ ago(entry.created_at) }}</p>
                </li>
            </ul>
            <p v-else class="muted">Todavía no hay actividad registrada.</p>
            <Link href="/admin/audit" class="quiet-link">Ver el registro completo →</Link>
        </section>
    </div>

    <p class="panel-foot muted">Sesión de {{ roleLabel }} · Horario de Costa Rica</p>
</AccountLayout></template>

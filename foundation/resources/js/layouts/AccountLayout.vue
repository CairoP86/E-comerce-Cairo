<script setup lang="ts">
import { computed, nextTick, onMounted, ref, type Component } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { CircleUser, FolderTree, LayoutDashboard, Package, Percent, ReceiptText, ScrollText, Tag, Truck } from '@lucide/vue';
import PublicLayout from './PublicLayout.vue';
import type { SharedProps } from '../types/auth';
defineProps<{ title: string; section?: 'account' | 'admin' }>();
const page = usePage<SharedProps>();
const role = computed(() => page.props.auth.user?.role);
const staff = computed(() => !!role.value && role.value !== 'customer');
const path = computed(() => new URL(page.url, 'http://local').pathname);
const turn = computed(() => page.props.operatorTurn ?? { orders: 0, offers: 0 });
const roleLabel = computed(() => ({ admin: 'Administrador', operator: 'Operador', customer: 'Cliente' })[role.value ?? 'customer']);

interface NavLink { href: string; label: string; icon: Component; current: (p: string) => boolean; count?: number; countLabel?: string; show?: boolean }
// Grouped by how often the operator opens them. Each entry also claims its sub-pages: a product's
// edit and offers pages keep "Productos" marked.
type NavGroup = { title: string; links: NavLink[] };
const groups = computed<NavGroup[]>(() => ([
    { title: 'Día a día', links: [
        { href: '/admin', label: 'Panel operativo', icon: LayoutDashboard, current: p => p === '/admin' },
        { href: '/admin/orders', label: 'Pedidos', icon: ReceiptText, current: p => p.startsWith('/admin/orders'), count: turn.value.orders, countLabel: 'por confirmar pago' },
        { href: '/admin/catalog/products', label: 'Productos', icon: Package, current: p => p.startsWith('/admin/catalog/products') || p.startsWith('/admin/commercial/products'), count: turn.value.offers, countLabel: 'con oferta por actualizar' },
    ] },
    { title: 'Catálogo', links: [
        { href: '/admin/catalog/categories', label: 'Categorías', icon: FolderTree, current: p => p.startsWith('/admin/catalog/categories') },
        { href: '/admin/catalog/brands', label: 'Marcas', icon: Tag, current: p => p.startsWith('/admin/catalog/brands') },
    ] },
    { title: 'Configuración', links: [
        { href: '/admin/commercial/suppliers', label: 'Proveedores', icon: Truck, current: p => p.startsWith('/admin/commercial/suppliers') },
        { href: '/admin/commercial/rules', label: 'Reglas comerciales', icon: Percent, current: p => p.startsWith('/admin/commercial/rules') },
        { href: '/admin/audit', label: 'Auditoría', icon: ScrollText, current: p => p.startsWith('/admin/audit'), show: role.value === 'admin' },
    ] },
] as NavGroup[]).map(group => ({ ...group, links: group.links.filter(link => link.show !== false) })));
const account: NavLink = { href: '/account', label: 'Mi cuenta', icon: CircleUser, current: p => p === '/account' };

// On a phone the menu is one scrolling row; start it with the current page in view. Only the row
// scrolls: the page keeps its own position (forms that preserve scroll come back where they were).
const nav = ref<HTMLElement | null>(null);
onMounted(() => nextTick(() => {
    const row = nav.value; const current = row?.querySelector<HTMLElement>('.is-current');
    if (row && current && row.scrollWidth > row.clientWidth) row.scrollLeft = current.offsetLeft - (row.clientWidth - current.offsetWidth) / 2;
}));
</script>
<template><PublicLayout><div class="workspace">
    <aside class="sidebar" :class="{ 'is-staff': staff }">
        <nav ref="nav" class="side-nav" aria-label="Área privada">
            <template v-if="staff">
                <div v-for="group in groups" :key="group.title" class="nav-group">
                    <p class="nav-group-title">{{ group.title }}</p>
                    <Link v-for="link in group.links" :key="link.href" :href="link.href" class="nav-link" :class="{ 'is-current': link.current(path) }" :aria-current="link.current(path) ? 'page' : undefined">
                        <component :is="link.icon" class="nav-icon" :size="18" :stroke-width="1.75" aria-hidden="true"/>
                        <span class="nav-label">{{ link.label }}</span>
                        <span v-if="link.count" class="turn-count">{{ link.count }}<span class="visually-hidden"> {{ link.countLabel }}</span></span>
                    </Link>
                </div>
            </template>
            <p v-else class="eyebrow">MI ESPACIO</p>
            <div class="nav-group nav-foot">
                <Link :href="account.href" class="nav-link" :class="{ 'is-current': account.current(path) }" :aria-current="account.current(path) ? 'page' : undefined">
                    <component :is="account.icon" class="nav-icon" :size="18" :stroke-width="1.75" aria-hidden="true"/>
                    <span class="nav-label">{{ account.label }}</span>
                </Link>
                <p v-if="staff" class="role-tag">{{ roleLabel }}</p>
            </div>
        </nav>
    </aside>
    <section class="workspace-content"><h1>{{ title }}</h1><p v-if="page.props.status" class="notice" role="status">{{ page.props.status }}</p><slot /></section>
</div></PublicLayout></template>

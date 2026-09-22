<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import PublicLayout from './PublicLayout.vue';
import type { SharedProps } from '../types/auth';
defineProps<{ title: string; section?: 'account' | 'admin' }>();
const page = usePage<SharedProps>();
const role = computed(() => page.props.auth.user?.role);
const staff = computed(() => !!role.value && role.value !== 'customer');
const path = computed(() => new URL(page.url, 'http://local').pathname);
// Each entry also claims its sub-pages: a product's edit and offers pages keep "Productos" marked.
const links = computed(() => [
    { href: '/account', label: 'Mi cuenta', show: true, current: (p: string) => p === '/account' },
    { href: '/admin', label: 'Panel operativo', show: staff.value, current: (p: string) => p === '/admin' },
    { href: '/admin/catalog/products', label: 'Productos', show: staff.value, current: (p: string) => p.startsWith('/admin/catalog/products') || p.startsWith('/admin/commercial/products') },
    { href: '/admin/catalog/categories', label: 'Categorías', show: staff.value, current: (p: string) => p.startsWith('/admin/catalog/categories') },
    { href: '/admin/catalog/brands', label: 'Marcas', show: staff.value, current: (p: string) => p.startsWith('/admin/catalog/brands') },
    { href: '/admin/orders', label: 'Pedidos', show: staff.value, current: (p: string) => p.startsWith('/admin/orders') },
    { href: '/admin/commercial/suppliers', label: 'Proveedores', show: staff.value, current: (p: string) => p.startsWith('/admin/commercial/suppliers') },
    { href: '/admin/commercial/rules', label: 'Reglas comerciales', show: staff.value, current: (p: string) => p.startsWith('/admin/commercial/rules') },
    { href: '/admin/audit', label: 'Auditoría', show: role.value === 'admin', current: (p: string) => p.startsWith('/admin/audit') },
].filter(link => link.show));
</script>
<template><PublicLayout><div class="workspace"><aside class="sidebar"><p class="eyebrow">{{ section === 'admin' ? 'OPERACIONES' : 'MI ESPACIO' }}</p><nav aria-label="Área privada"><Link v-for="link in links" :key="link.href" :href="link.href" :class="{ 'is-current': link.current(path) }" :aria-current="link.current(path) ? 'page' : undefined">{{ link.label }}</Link></nav><p class="role-tag">{{ page.props.auth.user?.role }}</p></aside><section class="workspace-content"><h1>{{ title }}</h1><p v-if="page.props.status" class="notice" role="status">{{ page.props.status }}</p><slot /></section></div></PublicLayout></template>

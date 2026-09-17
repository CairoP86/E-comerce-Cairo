<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import PublicLayout from './PublicLayout.vue';
import type { SharedProps } from '../types/auth';
defineProps<{ title: string; section?: 'account' | 'admin' }>();
const page = usePage<SharedProps>();
</script>
<template><PublicLayout><div class="workspace"><aside class="sidebar"><p class="eyebrow">{{ section === 'admin' ? 'OPERACIONES' : 'MI ESPACIO' }}</p><nav aria-label="Área privada"><Link href="/account">Mi cuenta</Link><Link v-if="page.props.auth.user?.role !== 'customer'" href="/admin">Panel operativo</Link><Link v-if="page.props.auth.user && page.props.auth.user.role !== 'customer'" href="/admin/catalog/products">Productos</Link><Link v-if="page.props.auth.user && page.props.auth.user.role !== 'customer'" href="/admin/catalog/categories">Categorías</Link><Link v-if="page.props.auth.user && page.props.auth.user.role !== 'customer'" href="/admin/catalog/brands">Marcas</Link><Link v-if="page.props.auth.user?.role === 'admin'" href="/admin/audit">Auditoría</Link></nav><p class="role-tag">{{ page.props.auth.user?.role }}</p></aside><section class="workspace-content"><h1>{{ title }}</h1><p v-if="page.props.status" class="notice" role="status">{{ page.props.status }}</p><slot /></section></div></PublicLayout></template>

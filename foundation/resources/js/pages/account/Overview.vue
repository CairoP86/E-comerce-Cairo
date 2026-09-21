<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AccountLayout from '../../layouts/AccountLayout.vue';
import type { SharedProps } from '../../types/auth';
const page = usePage<SharedProps>();
const isStaff = computed(() => !!page.props.auth.user && page.props.auth.user.role !== 'customer');
</script>
<template><Head title="Mi cuenta"/><AccountLayout title="Tu espacio personal"><p class="muted">Bienvenido, {{ page.props.auth.user?.name }}.</p><article class="info-card"><p class="eyebrow">DATOS DE LA CUENTA</p><h2>{{ page.props.auth.user?.name }}</h2><p>{{ page.props.auth.user?.email }}</p><span class="verified">Correo verificado</span></article><p v-if="isStaff" class="muted">Desde el menú gestionás el catálogo, los pedidos y la operación comercial. <Link href="/admin" class="quiet-link">Ir al panel operativo →</Link></p><p v-else class="muted">Los pedidos que hagas con la sesión iniciada quedan asociados a tu cuenta, y tu correo se completa solo al finalizar la compra.</p></AccountLayout></template>

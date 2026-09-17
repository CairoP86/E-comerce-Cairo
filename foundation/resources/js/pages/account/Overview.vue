<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import AccountLayout from '../../layouts/AccountLayout.vue';
import type { SharedProps } from '../../types/auth';
const page = usePage<SharedProps>();
</script>
<template><Head title="Mi cuenta"/><AccountLayout title="Tu espacio personal"><p class="muted">Bienvenido, {{ page.props.auth.user?.name }}.</p><article class="info-card"><p class="eyebrow">DATOS DE LA CUENTA</p><h2>{{ page.props.auth.user?.name }}</h2><p>{{ page.props.auth.user?.email }}</p><span class="verified">Correo verificado</span></article><p class="muted">Tu cuenta está lista. Las funciones de catálogo y pedidos se incorporarán en próximas etapas.</p></AccountLayout></template>

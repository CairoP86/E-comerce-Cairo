<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import AccountLayout from '../../layouts/AccountLayout.vue';
import type { SharedProps } from '../../types/auth';
const page = usePage<SharedProps>();
</script>
<template><Head title="Panel operativo"/><AccountLayout title="Panel operativo" section="admin"><p class="muted">Acceso autorizado como {{ page.props.auth.user?.role }}.</p><article class="info-card"><p class="eyebrow">FUNDACIÓN / 0B</p><h2>Un espacio para operar.</h2><p>Desde el menú gestionás productos, categorías, marcas, pedidos, proveedores y reglas comerciales. Cada cambio queda en el registro de auditoría.</p></article></AccountLayout></template>

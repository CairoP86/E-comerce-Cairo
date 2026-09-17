<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import AccountLayout from '../../layouts/AccountLayout.vue';
import type { SharedProps } from '../../types/auth';
const page = usePage<SharedProps>();
</script>
<template><Head title="Panel operativo"/><AccountLayout title="Panel operativo" section="admin"><p class="muted">Acceso autorizado como {{ page.props.auth.user?.role }}.</p><article class="info-card"><p class="eyebrow">FUNDACIÓN / 0B</p><h2>Un espacio para operar.</h2><p>La autenticación, los permisos y el registro de actividad están activos. Los módulos comerciales se habilitarán en sus fases correspondientes.</p></article></AccountLayout></template>

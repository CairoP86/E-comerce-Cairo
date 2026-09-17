<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import PublicLayout from './PublicLayout.vue';
import type { SharedProps } from '../types/auth';
defineProps<{ title: string; description: string }>();
const page = usePage<SharedProps>();
</script>
<template><PublicLayout><section class="auth-grid"><aside class="auth-story"><p class="eyebrow">TU ESPACIO PERSONAL</p><h1>Todo empieza<br>con una <em>conexión.</em></h1><p>Un lugar para descubrir lo que viene y acceder a tu cuenta con tranquilidad.</p><div class="orbit-art" aria-hidden="true"><span></span></div><small>01 / ACCESO</small></aside><div class="auth-panel"><p class="eyebrow">BIENVENIDO</p><h2>{{ title }}</h2><p class="muted">{{ description }}</p><p v-if="page.props.status" class="notice" role="status">{{ page.props.status }}</p><slot /></div></section></PublicLayout></template>

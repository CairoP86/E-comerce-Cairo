<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '../layouts/PublicLayout.vue';
const props = defineProps<{ status: number }>();
const messages: Record<number, [string, string]> = {
    403: ['Acceso restringido', 'Tu cuenta no tiene permiso para acceder a esta sección.'],
    404: ['Página no encontrada', 'Esta página no está disponible.'],
    419: ['Tu sesión ha vencido', 'Vuelve a abrir el formulario e inténtalo de nuevo.'],
    429: ['Un momento, por favor', 'Has realizado varios intentos. Espera un minuto antes de volver a intentarlo.'],
};
const message = messages[props.status] ?? ['No pudimos completar la solicitud', 'Inténtalo de nuevo más tarde.'];
</script>
<template><Head :title="message[0]"/><PublicLayout><section class="welcome"><p class="eyebrow">{{ status }}</p><h1>{{ message[0] }}</h1><p>{{ message[1] }}</p><Link href="/" class="button">Volver al inicio ↗</Link></section></PublicLayout></template>

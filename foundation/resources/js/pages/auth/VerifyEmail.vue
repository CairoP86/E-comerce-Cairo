<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '../../layouts/AuthLayout.vue';
const form = useForm({});
</script>
<template><Head title="Verifica tu correo"/><AuthLayout title="Un último paso." description="Abre el enlace que enviamos a tu correo para activar el acceso a tu cuenta."><form @submit.prevent="form.post('/email/verification-notification')"><button class="button full" :disabled="form.processing">Reenviar enlace de verificación ↗</button></form><p class="form-bottom">Revisa también tu carpeta de correo no deseado.</p><Link href="/logout" method="post" as="button" class="quiet-link">Cerrar sesión</Link></AuthLayout></template>

<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '../../layouts/AuthLayout.vue';
import FormField from '../../components/FormField.vue';
const form = useForm({ email: '' });
</script>
<template><Head title="Recuperar acceso"/><AuthLayout title="Volvamos a conectar." description="Te enviaremos un enlace para elegir una nueva contraseña."><form @submit.prevent="form.post('/forgot-password')"><FormField id="email" v-model="form.email" label="Correo electrónico" type="email" autocomplete="email" :error="form.errors.email"/><button class="button full" :disabled="form.processing">Enviar instrucciones ↗</button><p class="form-bottom"><Link href="/login">Volver a iniciar sesión</Link></p></form></AuthLayout></template>

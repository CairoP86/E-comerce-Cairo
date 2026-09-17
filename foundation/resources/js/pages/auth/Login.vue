<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '../../layouts/AuthLayout.vue';
import FormField from '../../components/FormField.vue';
const form = useForm({ email: '', password: '', remember: false });
const submit = () => form.post('/login', { onFinish: () => form.reset('password') });
</script>
<template><Head title="Iniciar sesión"/><AuthLayout title="Qué bueno verte." description="Ingresa a tu cuenta para continuar."><form @submit.prevent="submit"><FormField id="email" v-model="form.email" label="Correo electrónico" type="email" autocomplete="email" :error="form.errors.email"/><FormField id="password" v-model="form.password" label="Contraseña" type="password" autocomplete="current-password" :error="form.errors.password"/><div class="form-options"><label><input v-model="form.remember" type="checkbox"> Recordarme</label><Link href="/forgot-password">Olvidé mi contraseña</Link></div><button class="button full" :disabled="form.processing">{{ form.processing ? 'Ingresando…' : 'Iniciar sesión ↗' }}</button><p class="form-bottom">¿Primera vez aquí? <Link href="/register">Crea tu cuenta</Link></p></form></AuthLayout></template>

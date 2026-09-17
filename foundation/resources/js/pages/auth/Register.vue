<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '../../layouts/AuthLayout.vue';
import FormField from '../../components/FormField.vue';
const form = useForm({ name: '', email: '', password: '', password_confirmation: '' });
const submit = () => form.post('/register', { onFinish: () => form.reset('password', 'password_confirmation') });
</script>
<template><Head title="Crear cuenta"/><AuthLayout title="Hazlo tuyo." description="Crea tu cuenta. Después verificaremos tu correo."><form @submit.prevent="submit"><FormField id="name" v-model="form.name" label="Nombre completo" autocomplete="name" :maxlength="100" :error="form.errors.name"/><FormField id="email" v-model="form.email" label="Correo electrónico" type="email" autocomplete="email" :maxlength="254" :error="form.errors.email"/><FormField id="password" v-model="form.password" label="Contraseña" type="password" autocomplete="new-password" :minlength="12" :maxlength="72" :error="form.errors.password"/><p class="field-hint">Al menos 12 caracteres, con letras y números.</p><FormField id="password_confirmation" v-model="form.password_confirmation" label="Confirmar contraseña" type="password" autocomplete="new-password" :error="form.errors.password_confirmation"/><button class="button full" :disabled="form.processing">{{ form.processing ? 'Creando cuenta…' : 'Crear cuenta ↗' }}</button><p class="form-bottom">¿Ya tienes cuenta? <Link href="/login">Inicia sesión</Link></p></form></AuthLayout></template>

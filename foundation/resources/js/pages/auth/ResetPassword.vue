<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AuthLayout from '../../layouts/AuthLayout.vue';
import FormField from '../../components/FormField.vue';
const props = defineProps<{ token: string; email: string }>();
const form = useForm({ token: props.token, email: props.email, password: '', password_confirmation: '' });
const submit = () => form.post('/reset-password', { onFinish: () => form.reset('password', 'password_confirmation') });
</script>
<template><Head title="Nueva contraseña"/><AuthLayout title="Un nuevo comienzo." description="Elige una contraseña de al menos 12 caracteres, con letras y números."><form @submit.prevent="submit"><FormField id="email" v-model="form.email" label="Correo electrónico" type="email" autocomplete="email" :error="form.errors.email"/><FormField id="password" v-model="form.password" label="Nueva contraseña" type="password" autocomplete="new-password" :minlength="12" :maxlength="72" :error="form.errors.password"/><FormField id="password_confirmation" v-model="form.password_confirmation" label="Confirmar contraseña" type="password" autocomplete="new-password" :error="form.errors.password_confirmation"/><p v-if="form.errors.token" class="field-error" role="alert">El enlace no es válido. Solicita uno nuevo.</p><button class="button full" :disabled="form.processing">Guardar contraseña ↗</button></form></AuthLayout></template>

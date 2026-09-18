<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { nextTick } from 'vue';
import AccountLayout from '../../../layouts/AccountLayout.vue';
import CommercialErrors from '../../../components/CommercialErrors.vue';
import type { Supplier } from '../../../types/commercial';
defineProps<{ suppliers: Supplier[]; canManage: boolean }>();
const form=useForm({name:'',code:'',active:true,notes:''});
const save=()=>form.post('/admin/commercial/suppliers',{onSuccess:()=>form.reset(),onError:()=>nextTick(()=>document.getElementById('supplier-errors')?.focus())});
</script>
<template><Head title="Proveedores"><meta name="robots" content="noindex, nofollow"/></Head><AccountLayout title="Proveedores" section="admin"><p class="muted">Gestión manual. No hay conexiones API, sincronización ni compras automáticas.</p><div class="commercial-cards"><article v-for="supplier in suppliers" :key="supplier.id" class="info-card"><h2><Link :href="`/admin/commercial/suppliers/${supplier.id}`">{{ supplier.name }}</Link></h2><p>{{ supplier.active ? 'Activo' : 'Inactivo' }} · {{ supplier.offers_count ?? 0 }} ofertas</p><p class="muted">Integración no disponible</p></article></div><form v-if="canManage" class="info-card compact-form" @submit.prevent="save"><h2>Agregar proveedor</h2><fieldset :disabled="form.processing"><label>Nombre<input v-model="form.name" required maxlength="120" :aria-invalid="!!form.errors.name" aria-describedby="supplier-errors-name"></label><label>Código interno<input v-model="form.code" required maxlength="60" placeholder="nombre-del-proveedor" :aria-invalid="!!form.errors.code" aria-describedby="supplier-errors-code"></label><label>Notas privadas<textarea v-model="form.notes" maxlength="2000"/></label><label class="inline-choice"><input v-model="form.active" type="checkbox"> Activo</label><CommercialErrors :errors="form.errors" prefix="supplier-errors"/><button class="button">Guardar proveedor</button></fieldset></form></AccountLayout></template>

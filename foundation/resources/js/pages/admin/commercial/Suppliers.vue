<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { nextTick } from 'vue';
import AccountLayout from '../../../layouts/AccountLayout.vue';
import CommercialErrors from '../../../components/CommercialErrors.vue';
import StatusBadge from '../../../components/StatusBadge.vue';
import type { Supplier } from '../../../types/commercial';
defineProps<{ suppliers: Supplier[]; canManage: boolean }>();
const form=useForm({name:'',code:'',active:true,notes:''});
const save=()=>form.post('/admin/commercial/suppliers',{onSuccess:()=>form.reset(),onError:()=>nextTick(()=>document.getElementById('supplier-errors')?.focus())});
</script>
<template><Head title="Proveedores"><meta name="robots" content="noindex, nofollow"/></Head><AccountLayout title="Proveedores" section="admin">
    <p class="muted">{{ suppliers.length }} {{ suppliers.length === 1 ? 'proveedor' : 'proveedores' }} · Gestión manual: no hay conexiones API, sincronización ni compras automáticas.</p>

    <div class="table-wrap data-table"><table><thead><tr><th>Proveedor</th><th>Código</th><th>Estado</th><th class="num">Ofertas</th><th><span class="visually-hidden">Acción</span></th></tr></thead><tbody>
        <tr v-for="supplier in suppliers" :key="supplier.id">
            <td class="product-cell"><Link :href="`/admin/commercial/suppliers/${supplier.id}`" class="product-name">{{ supplier.name }}</Link></td>
            <td class="code" data-label="Código">{{ supplier.code }}</td>
            <td data-label="Estado"><StatusBadge :status="supplier.active ? 'active' : 'inactive'" :label="supplier.active ? 'Activo' : 'Inactivo'"/></td>
            <td class="num" data-label="Ofertas">{{ supplier.offers_count ?? 0 }}</td>
            <td class="action-cell"><Link :href="`/admin/commercial/suppliers/${supplier.id}`" class="quiet-link">{{ canManage ? 'Editar' : 'Consultar' }}</Link></td>
        </tr>
        <tr v-if="!suppliers.length" class="empty-row"><td colspan="5">Todavía no hay proveedores.</td></tr>
    </tbody></table></div>

    <form v-if="canManage" class="info-card is-work compact-form" @submit.prevent="save"><h2>Agregar proveedor</h2><fieldset :disabled="form.processing"><label>Nombre<input v-model="form.name" required maxlength="120" :aria-invalid="!!form.errors.name" aria-describedby="supplier-errors-name"></label><label>Código interno<input v-model="form.code" class="code" required maxlength="60" placeholder="nombre-del-proveedor" :aria-invalid="!!form.errors.code" aria-describedby="supplier-errors-code"></label><label>Notas privadas<textarea v-model="form.notes" maxlength="2000"/></label><label class="inline-choice"><input v-model="form.active" type="checkbox"> Activo</label><CommercialErrors :errors="form.errors" prefix="supplier-errors"/><button class="button">Guardar proveedor</button></fieldset></form>
</AccountLayout></template>

<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { nextTick } from 'vue';
import AccountLayout from '../../../layouts/AccountLayout.vue';
import CommercialErrors from '../../../components/CommercialErrors.vue';
import StatusBadge from '../../../components/StatusBadge.vue';
import { money, type Paginator } from '../../../types/catalog';
import { availabilityLabel, type Supplier, type SupplierOffer } from '../../../types/commercial';
import { ago, dateTime } from '../../../types/time';
const props=defineProps<{ supplier: Supplier; offers: Paginator<SupplierOffer>; canManage: boolean }>();
const form=useForm({name:props.supplier.name,code:props.supplier.code,active:props.supplier.active,notes:props.supplier.notes??''});
const save=()=>form.put(`/admin/commercial/suppliers/${props.supplier.id}`,{onError:()=>nextTick(()=>document.getElementById('supplier-errors')?.focus())});
</script>
<template><Head :title="supplier.name"><meta name="robots" content="noindex, nofollow"/></Head><AccountLayout :title="supplier.name" section="admin">
    <Link href="/admin/commercial/suppliers" class="quiet-link">← Proveedores</Link>
    <p class="muted supplier-line"><StatusBadge :status="supplier.active ? 'active' : 'inactive'" :label="supplier.active ? 'Activo' : 'Inactivo'"/> · <span class="code">{{ supplier.code }}</span> · {{ offers.total }} {{ offers.total === 1 ? 'oferta vinculada' : 'ofertas vinculadas' }}</p>

    <section class="info-card is-work is-wide" aria-labelledby="offers-title">
        <h2 id="offers-title">Ofertas vinculadas</h2>
        <p class="muted">La información se registra manualmente; no representa inventario en tiempo real.</p>
        <div v-if="offers.data.length" class="table-wrap data-table"><table><thead><tr><th>Producto</th><th>SKU del proveedor</th><th class="num">Costo</th><th>Disponibilidad</th><th>Estado</th><th>Observado</th></tr></thead><tbody>
            <tr v-for="offer in offers.data" :key="offer.id">
                <td class="product-cell"><Link :href="`/admin/commercial/products/${offer.product_id}`" class="product-name">{{ offer.product?.name }}</Link><small v-if="offer.product?.is_demo" class="block muted">Producto de demostración</small></td>
                <td class="code" data-label="SKU del proveedor">{{ offer.supplier_sku }}</td>
                <td class="num" data-label="Costo">{{ offer.cost_minor === null ? 'No confirmado' : money(offer.cost_minor, offer.currency) }}<small class="block muted">{{ offer.currency }}</small></td>
                <td data-label="Disponibilidad">{{ availabilityLabel(offer.availability) }}<small class="block muted">Stock: {{ offer.stock ?? 'Desconocido' }}</small></td>
                <td data-label="Estado"><StatusBadge :status="offer.active ? 'active' : 'inactive'" :label="offer.active ? 'Activa' : 'Inactiva'"/></td>
                <td data-label="Observado">{{ dateTime(offer.observed_at) }}<small class="block muted">{{ ago(offer.observed_at) }}</small></td>
            </tr>
        </tbody></table></div>
        <template v-else>
            <p>Sin ofertas. Abrí un producto del catálogo administrativo para asociar su SKU y sus datos confirmados.</p>
            <Link href="/admin/catalog/products" class="button">Abrir productos</Link>
        </template>
        <nav v-if="offers.prev_page_url || offers.next_page_url" class="pagination" aria-label="Páginas de ofertas"><Link v-if="offers.prev_page_url" :href="offers.prev_page_url">← Anterior</Link><Link v-if="offers.next_page_url" :href="offers.next_page_url">Siguiente →</Link></nav>
    </section>

    <form class="info-card is-work compact-form" @submit.prevent="save"><h2>Datos del proveedor</h2><fieldset :disabled="!canManage || form.processing"><label>Nombre<input v-model="form.name" required maxlength="120"></label><label>Código interno<input v-model="form.code" class="code" required maxlength="60"></label><label>Notas privadas<textarea v-model="form.notes" maxlength="2000"/></label><label class="inline-choice"><input v-model="form.active" type="checkbox"> Proveedor activo</label><CommercialErrors :errors="form.errors" prefix="supplier-errors"/><button v-if="canManage" class="button">Actualizar proveedor</button></fieldset></form>
</AccountLayout></template>

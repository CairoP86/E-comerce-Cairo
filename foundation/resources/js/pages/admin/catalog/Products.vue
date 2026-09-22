<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AccountLayout from '../../../layouts/AccountLayout.vue';
import StatusBadge from '../../../components/StatusBadge.vue';
import { money, type Paginator, type Product } from '../../../types/catalog';
import { freshnessLabel, needsAttention, stockLabel, type Freshness, type FreshnessSummary } from '../../../types/freshness';
const props = defineProps<{ products: Paginator<Product>; filters: { q?: string; status?: string; demo?: boolean | string }; canManage: boolean; freshness: Record<number, Freshness>; freshnessSummary: FreshnessSummary; hiddenDemo: number }>();
const q = ref(props.filters.q ?? ''); const status = ref(props.filters.status ?? '');
const demo = ref(props.filters.demo === true || props.filters.demo === '1');
const search = () => router.get('/admin/catalog/products', { q: q.value, status: status.value, ...(demo.value ? { demo: 1 } : {}) }, { preserveState: true });
const toggleDemo = () => { demo.value = !demo.value; search(); };
const attention = computed(() => props.freshnessSummary.expired + props.freshnessSummary.expiring + props.freshnessSummary.invalid);
</script>
<template><Head title="Productos"/><AccountLayout title="Catálogo comercial" section="admin">
    <div class="catalog-toolbar"><p class="muted">{{ products.total }} productos · {{ canManage ? 'Administración' : 'Solo consulta' }}</p><Link v-if="canManage" href="/admin/catalog/products/create" class="button">Crear producto ↗</Link><Link href="/catalog" class="quiet-link">Vista pública</Link></div>

    <p v-if="attention" class="freshness-summary" role="status">
        <strong>Ofertas de productos publicados que requieren atención:</strong>
        <span v-if="freshnessSummary.expired" class="freshness is-expired">{{ freshnessSummary.expired }} {{ freshnessSummary.expired === 1 ? 'vencida' : 'vencidas' }}</span>
        <span v-if="freshnessSummary.expiring" class="freshness is-expiring">{{ freshnessSummary.expiring }} por vencer</span>
        <span v-if="freshnessSummary.invalid" class="freshness is-invalid">{{ freshnessSummary.invalid }} sin dato válido</span>
    </p>
    <p v-else class="freshness-summary is-clear" role="status">Todas las ofertas de productos publicados están vigentes.</p>

    <form class="catalog-filters compact-form" @submit.prevent="search"><input v-model="q" placeholder="Nombre o SKU" aria-label="Buscar por nombre o SKU"><select v-model="status" aria-label="Estado"><option value="">Todos los estados</option><option value="draft">Borrador</option><option value="published">Publicado</option><option value="archived">Archivado</option></select><button class="button">Buscar</button></form>
    <p v-if="hiddenDemo || demo" class="demo-toggle muted">
        <template v-if="!demo">{{ hiddenDemo }} {{ hiddenDemo === 1 ? 'producto de demostración oculto' : 'productos de demostración ocultos' }}. <button type="button" class="quiet-link" @click="toggleDemo">Mostrar</button></template>
        <template v-else>Mostrando también productos de demostración. <button type="button" class="quiet-link" @click="toggleDemo">Ocultarlos</button></template>
    </p>

    <div class="table-wrap"><table><thead><tr><th>Producto</th><th>SKU</th><th>Precio</th><th>Estado</th><th>Disponibilidad</th><th>Acción</th></tr></thead><tbody>
        <tr v-for="product in products.data" :key="product.id">
            <td>{{ product.name }}<small class="block muted">{{ product.category?.name }} · {{ product.is_demo ? 'Demostración' : product.brand?.name }}</small></td>
            <td class="code">{{ product.sku }}</td>
            <td>{{ money(product.price_minor, product.currency) }}</td>
            <td><StatusBadge :status="product.status"/></td>
            <td>
                <template v-if="freshness[product.id]">
                    <span class="freshness" :class="`is-${freshness[product.id].level}`">{{ freshnessLabel(freshness[product.id]) }}</span>
                    <small v-if="stockLabel(freshness[product.id])" class="block muted">{{ stockLabel(freshness[product.id]) }}</small>
                    <Link v-if="needsAttention(freshness[product.id])" :href="`/admin/commercial/products/${product.id}`" class="quiet-link block">Actualizar oferta →</Link>
                </template>
            </td>
            <td><Link :href="`/admin/catalog/products/${product.id}/edit`" class="quiet-link">{{ canManage ? 'Editar' : 'Consultar' }}</Link></td>
        </tr>
        <tr v-if="!products.data.length"><td colspan="6">No hay productos que coincidan.</td></tr>
    </tbody></table></div>
    <nav class="pagination"><Link v-if="products.prev_page_url" :href="products.prev_page_url">← Anterior</Link><span>Página {{ products.current_page }}</span><Link v-if="products.next_page_url" :href="products.next_page_url">Siguiente →</Link></nav>
</AccountLayout></template>

<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { CircleCheck } from '@lucide/vue';
import AccountLayout from '../../../layouts/AccountLayout.vue';
import StatusBadge from '../../../components/StatusBadge.vue';
import { money, type Paginator, type Product } from '../../../types/catalog';
import { freshnessLabel, needsAttention, stockLabel, type Freshness, type FreshnessSummary } from '../../../types/freshness';
type Vigencia = 'vencidas' | 'por-vencer' | 'sin-dato';
const props = defineProps<{ products: Paginator<Product>; filters: { q?: string; status?: string; demo?: boolean | string; vigencia?: Vigencia }; canManage: boolean; freshness: Record<number, Freshness>; freshnessSummary: FreshnessSummary; hiddenDemo: number }>();
const q = ref(props.filters.q ?? ''); const status = ref(props.filters.status ?? '');
const demo = ref(props.filters.demo === true || props.filters.demo === '1');
const vigencia = ref<Vigencia | ''>(props.filters.vigencia ?? '');
const search = () => router.get('/admin/catalog/products', { q: q.value, status: status.value, ...(vigencia.value ? { vigencia: vigencia.value } : {}), ...(demo.value ? { demo: 1 } : {}) }, { preserveState: true });
const toggleDemo = () => { demo.value = !demo.value; search(); };
const filterBy = (value: Vigencia | '') => { vigencia.value = value; search(); };

// One segment per number of the summary; zero-count segments stay out unless they are the active filter.
const segments = computed(() => ([
    { value: 'vencidas', count: props.freshnessSummary.expired, label: (n: number) => n === 1 ? 'vencida' : 'vencidas', tone: 'is-expired' },
    { value: 'sin-dato', count: props.freshnessSummary.invalid, label: () => 'sin dato válido', tone: 'is-invalid' },
    { value: 'por-vencer', count: props.freshnessSummary.expiring, label: () => 'por vencer', tone: 'is-expiring' },
] as const).filter(s => s.count || vigencia.value === s.value));
const allFresh = computed(() => !segments.value.length);
</script>
<template><Head title="Productos"/><AccountLayout title="Catálogo comercial" section="admin">
    <div class="catalog-toolbar products-toolbar">
        <p class="muted">{{ products.total }} {{ products.total === 1 ? 'producto' : 'productos' }}{{ vigencia ? ' con este filtro' : '' }} · {{ canManage ? 'Administración' : 'Solo consulta' }}</p>
        <Link href="/catalog" class="quiet-link">Vista pública ↗</Link>
        <Link v-if="canManage" href="/admin/catalog/products/create" class="button">Crear producto</Link>
    </div>

    <nav v-if="!allFresh" class="filter-strip" aria-label="Filtrar por vigencia de la oferta">
        <span class="filter-strip-title">Ofertas publicadas</span>
        <button type="button" class="strip-segment" :aria-pressed="!vigencia" @click="filterBy('')">Todas</button>
        <button v-for="s in segments" :key="s.value" type="button" class="strip-segment" :class="s.tone" :aria-pressed="vigencia === s.value" @click="filterBy(vigencia === s.value ? '' : s.value)">
            <span class="strip-count">{{ s.count }}</span> {{ s.label(s.count) }}
        </button>
    </nav>
    <p v-else class="filter-strip is-clear" role="status"><CircleCheck :size="16" :stroke-width="1.75" aria-hidden="true"/> Todas las ofertas de productos publicados están vigentes.</p>

    <form class="catalog-filters compact-form" @submit.prevent="search"><input v-model="q" placeholder="Nombre o SKU" aria-label="Buscar por nombre o SKU"><select v-model="status" aria-label="Estado"><option value="">Todos los estados</option><option value="draft">Borrador</option><option value="published">Publicado</option><option value="archived">Archivado</option></select><button class="button">Buscar</button></form>
    <p v-if="hiddenDemo || demo" class="demo-toggle muted">
        <template v-if="!demo">{{ hiddenDemo }} {{ hiddenDemo === 1 ? 'producto de demostración oculto' : 'productos de demostración ocultos' }}. <button type="button" class="quiet-link" @click="toggleDemo">Mostrar</button></template>
        <template v-else>Mostrando también productos de demostración. <button type="button" class="quiet-link" @click="toggleDemo">Ocultarlos</button></template>
    </p>

    <div class="table-wrap data-table"><table><thead><tr><th>Producto</th><th>SKU</th><th class="num">Precio</th><th>Estado</th><th>Disponibilidad</th><th><span class="visually-hidden">Acción</span></th></tr></thead><tbody>
        <tr v-for="product in products.data" :key="product.id">
            <td class="product-cell"><Link :href="`/admin/catalog/products/${product.id}/edit`" class="product-name">{{ product.name }}</Link><small class="block muted">{{ product.category?.name }} · {{ product.is_demo ? 'Demostración' : product.brand?.name }}</small></td>
            <td class="code" data-label="SKU">{{ product.sku }}</td>
            <td class="num" data-label="Precio">{{ money(product.price_minor, product.currency) }}</td>
            <td data-label="Estado"><StatusBadge :status="product.status"/></td>
            <td data-label="Disponibilidad">
                <template v-if="freshness[product.id]">
                    <span class="freshness" :class="`is-${freshness[product.id].level}`">{{ freshnessLabel(freshness[product.id]) }}</span>
                    <small v-if="stockLabel(freshness[product.id])" class="block muted">{{ stockLabel(freshness[product.id]) }}</small>
                    <Link v-if="needsAttention(freshness[product.id])" :href="`/admin/commercial/products/${product.id}`" class="quiet-link block">Actualizar oferta →</Link>
                </template>
            </td>
            <td class="action-cell"><Link :href="`/admin/catalog/products/${product.id}/edit`" class="quiet-link">{{ canManage ? 'Editar' : 'Consultar' }}</Link></td>
        </tr>
        <tr v-if="!products.data.length" class="empty-row"><td colspan="6">No hay productos que coincidan. <button v-if="vigencia" type="button" class="quiet-link" @click="filterBy('')">Ver todos</button></td></tr>
    </tbody></table></div>
    <nav class="pagination"><Link v-if="products.prev_page_url" :href="products.prev_page_url">← Anterior</Link><span>Página {{ products.current_page }}</span><Link v-if="products.next_page_url" :href="products.next_page_url">Siguiente →</Link></nav>
</AccountLayout></template>

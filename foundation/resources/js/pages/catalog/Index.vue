<script setup lang="ts">
import { computed, nextTick, reactive, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import StoreLayout from '../../layouts/StoreLayout.vue';
import StoreSeo from '../../components/storefront/StoreSeo.vue';
import ProductCard from '../../components/storefront/ProductCard.vue';
import type { AvailabilityMap, Filters, PublicTaxonomy, ProductPage, Seo } from '../../types/storefront';
const props = defineProps<{ products: ProductPage; availability: AvailabilityMap; filters: Filters; categories: PublicTaxonomy[]; brands: PublicTaxonomy[]; activeCategory: { name: string; description: string | null } | null; seo: Seo }>();
const form = reactive<Filters>({});
watch(() => props.filters, filters => { Object.keys(form).forEach(key => delete form[key as keyof Filters]); Object.assign(form, { q: '', category: '', brand: '', min: '', max: '', editorial: '', featured: '0', offers: '0' }, filters); }, { immediate: true });
const page = usePage();
const open = ref(false);
const pending = ref(false);
const clientErrors = ref<Record<string, string>>({});
const errors = computed(() => ({ ...((page.props.errors ?? {}) as Record<string, string>), ...clientErrors.value }));
const orderedCategories = computed(() => {
    const rows: { category: PublicTaxonomy; depth: number }[] = [];
    const append = (parent: string | null, depth: number) => props.categories.filter(c => (c.parent_slug ?? null) === parent).forEach(c => { rows.push({ category: c, depth }); append(c.slug, depth + 1); });
    append(null, 0); return rows;
});
const activeCount = computed(() => ['q', 'category', 'brand', 'min', 'max', 'editorial', 'featured', 'offers'].filter(key => props.filters[key as keyof Filters] && props.filters[key as keyof Filters] !== '0').length);
function apply() {
    clientErrors.value = {};
    const minor = (value: string) => { const [whole, fraction = ''] = value.split('.'); return Number(whole) * 100 + Number(fraction.padEnd(2, '0')); };
    if (form.min && form.max && minor(form.min) > minor(form.max)) {
        clientErrors.value.max = 'El precio máximo debe ser mayor o igual al mínimo.';
        open.value = true;
        nextTick(() => document.getElementById('filter-errors')?.focus());
        return;
    }
    const query = Object.fromEntries(Object.entries(form).filter(([key, value]) => key !== 'page' && value !== '' && value !== undefined && value !== '0'));
    pending.value = true;
    router.get('/catalog', query, { preserveState: true, preserveScroll: true, onSuccess: () => { open.value = false; document.getElementById('catalog-results')?.focus(); }, onError: () => { open.value = true; nextTick(() => document.getElementById('filter-errors')?.focus()); }, onFinish: () => { pending.value = false; } });
}
</script>
<template>
    <StoreSeo :seo="seo"/><StoreLayout>
        <nav class="st-breadcrumb" aria-label="Ruta de navegación"><Link href="/">Inicio</Link><span aria-hidden="true">/</span><span>Catálogo</span></nav>
        <header class="st-catalog-heading"><p class="st-overline">EXPLORA. COMPARA. DESCUBRE.</p><h1>{{ activeCategory?.name || 'Tu próxima idea empieza aquí.' }}</h1><p>{{ activeCategory?.description || 'Equipos, componentes y accesorios. Encuentra lo que encaja con tu próximo proyecto.' }}</p></header>
        <div class="st-catalog-layout">
            <aside class="st-filters" :class="{ 'is-open': open }"><div class="st-filter-heading"><h2>Filtrar catálogo</h2><Link href="/catalog" class="st-text-link">Limpiar</Link></div><button type="button" class="st-filter-toggle" :aria-expanded="open" aria-controls="catalog-filter-form" @click="open = !open">{{ open ? 'Cerrar filtros' : 'Mostrar filtros' }}<span>{{ activeCount ? `(${activeCount})` : '+' }}</span></button>
                <form id="catalog-filter-form" class="st-filter-form" @submit.prevent="apply">
                    <div v-if="Object.keys(errors).length" id="filter-errors" tabindex="-1" class="st-form-error" role="alert"><p v-for="(error, field) in errors" :key="field">{{ error }}</p></div>
                    <label>Nombre o SKU<input v-model="form.q" type="search" maxlength="100" placeholder="Ej. laptop o DEMO-001"></label>
                    <label>Categoría<select v-model="form.category"><option value="">Todas las categorías</option><option v-for="row in orderedCategories" :key="row.category.slug" :value="row.category.slug">{{ '— '.repeat(row.depth) }}{{ row.category.name }}</option></select></label>
                    <label>Marca<select v-model="form.brand"><option value="">Todas las marcas</option><option v-for="brand in brands" :key="brand.slug" :value="brand.slug">{{ brand.name }}</option></select></label>
                    <fieldset><legend>Rango de precio</legend><label>Moneda<select v-model="form.currency"><option value="CRC">Colones · CRC</option><option value="USD">Dólares · USD</option></select></label><div class="st-price-fields"><label>Desde<input v-model="form.min" inputmode="decimal" placeholder="0.00" pattern="[0-9]+([.][0-9]{1,2})?" :aria-invalid="!!errors.min"></label><label>Hasta<input v-model="form.max" inputmode="decimal" placeholder="Sin límite" pattern="[0-9]+([.][0-9]{1,2})?" :aria-invalid="!!errors.max"></label></div><p class="st-filter-hint">Los importes se comparan en la moneda elegida.</p></fieldset>
                    <p class="st-filter-hint">Solo mostramos productos con disponibilidad vigente.</p>
                    <label class="st-check"><input v-model="form.featured" type="checkbox" true-value="1" false-value="0">Solo destacados</label><label class="st-check"><input v-model="form.offers" type="checkbox" true-value="1" false-value="0">Con precio anterior</label>
                    <button class="st-button st-button-dark" :disabled="pending" type="submit">{{ pending ? 'Aplicando…' : 'Aplicar filtros' }} <span aria-hidden="true">→</span></button><Link href="/catalog" class="st-clear-filters">Limpiar todos los filtros</Link>
                </form>
            </aside>
            <section id="catalog-results" tabindex="-1" class="st-results" :aria-busy="pending" aria-label="Resultados del catálogo"><h2 class="st-sr-only">Productos del catálogo</h2><div class="st-results-toolbar"><p role="status" aria-live="polite"><strong>{{ products.total }}</strong> {{ products.total === 1 ? 'producto' : 'productos' }}<span v-if="activeCount"> · {{ activeCount }} filtros</span></p><label>Ordenar por<select v-model="form.sort" @change="apply"><option value="newest">Novedades</option><option value="featured">Destacados primero</option><option value="price_asc">Precio: menor a mayor</option><option value="price_desc">Precio: mayor a menor</option><option value="name_asc">Nombre: A–Z</option><option value="name_desc">Nombre: Z–A</option></select></label></div><p v-if="filters.q" class="st-search-summary">Resultados para «{{ filters.q }}»</p>
                <div v-if="products.data.length" class="st-product-grid"><ProductCard v-for="product in products.data" :key="product.slug" :product="product" :availability="availability[product.slug]"/></div><div v-else class="st-empty"><span aria-hidden="true">⌕</span><h2>No encontramos coincidencias.</h2><p>Prueba otro nombre o SKU, amplía el precio o limpia los filtros.</p><Link href="/catalog" class="st-button st-button-dark">Ver todo el catálogo</Link></div>
                <nav v-if="products.last_page > 1" class="st-pagination" aria-label="Paginación del catálogo"><Link v-if="products.prev_page_url" :href="products.prev_page_url" rel="prev">← Anterior</Link><span v-else class="st-subtle">← Anterior</span><span aria-current="page">{{ products.current_page }} / {{ products.last_page }}</span><Link v-if="products.next_page_url" :href="products.next_page_url" rel="next">Siguiente →</Link><span v-else class="st-subtle">Siguiente →</span></nav>
            </section>
        </div>
    </StoreLayout>
</template>

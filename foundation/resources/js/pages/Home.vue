<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Coins, Timer, Truck } from '@lucide/vue';
import StoreLayout from '../layouts/StoreLayout.vue';
import StoreSeo from '../components/storefront/StoreSeo.vue';
import ProductSection from '../components/storefront/ProductSection.vue';
import { catalogUrl, type AvailabilityMap, type PublicProduct, type PublicTaxonomy, type Seo, type Identity } from '../types/storefront';
const props = defineProps<{ featured: PublicProduct[]; recent: PublicProduct[]; offers: PublicProduct[]; availability: AvailabilityMap; categories: PublicTaxonomy[]; brands: PublicTaxonomy[]; seo: Seo }>();
const page = usePage<{ identity: Identity }>();
const mainCategories = computed(() => props.categories.filter(item => !props.categories.some(child => child.parent_slug === item.slug) && (item.products ?? 0) > 0).slice(0, 6));
</script>
<template>
    <StoreSeo :seo="seo"/><StoreLayout>
        <section class="st-hero">
            <div class="st-hero-copy"><p class="st-overline"><span class="st-live-dot"/> EXPLORA TU SIGUIENTE NIVEL</p><h1>Tecnología para<br>lo que <span>sigue.</span></h1><p>Ideas más grandes. Un espacio mejor conectado. Encuentra tecnología para crear, trabajar y hacer lo que te mueve.</p><div class="st-hero-actions"><Link href="/catalog" class="st-button">Explorar catálogo <span aria-hidden="true">↗</span></Link><Link href="/catalog?featured=1" class="st-hero-secondary">Ver destacados →</Link></div><div class="st-hero-note"><span aria-hidden="true">01 —</span> Una nueva perspectiva de la tecnología</div></div>
            <div class="st-hero-visual" aria-hidden="true"><div class="st-hero-grid"/></div>
        </section>
        <!-- Three things that are true today, each linking to where it happens. -->
        <div class="st-trust-strip">
            <Link href="/catalog"><Coins :size="18" :stroke-width="1.75" aria-hidden="true"/> Precios en colones, con IVA incluido</Link>
            <Link href="/metodos-de-pago"><Timer :size="18" :stroke-width="1.75" aria-hidden="true"/> Apartamos las unidades de tu carrito por un tiempo limitado</Link>
            <Link href="/metodos-de-pago"><Truck :size="18" :stroke-width="1.75" aria-hidden="true"/> El envío se calcula por cantón al finalizar la compra</Link>
        </div>
        <section id="categories" class="st-section"><div class="st-section-heading"><div><p class="st-overline">ENCUENTRA TU PUNTO DE PARTIDA</p><h2>Un mundo de posibilidades.</h2></div><Link href="/catalog" class="st-text-link">Todas las categorías ↗</Link></div><div class="st-category-grid"><Link v-for="(category, index) in mainCategories" :key="category.slug" :href="catalogUrl({ category: category.slug })" class="st-category-tile"><span class="st-category-symbol" aria-hidden="true">{{ ['⌘', '▤', '▣', '▱', '▥', '◫'][index] }}</span><span>{{ category.name }}</span><span class="st-category-count">{{ category.products }} {{ category.products === 1 ? 'producto' : 'productos' }} <span aria-hidden="true">↗</span></span></Link></div><p v-if="!mainCategories.length" class="st-subtle">Estamos preparando las categorías del catálogo.</p></section>
        <ProductSection title="El centro de tu próximo proyecto." eyebrow="SELECCIÓN DESTACADA" :products="featured" :availability="availability" href="/catalog?featured=1"/>
        <section class="st-editorial-banner"><div><p class="st-overline">TU ESPACIO. TUS IDEAS.</p><h2>El próximo paso<br>empieza con curiosidad.</h2><p>Explora equipos, componentes y accesorios. Compara sus características y descubre qué encaja contigo.</p><Link href="/catalog" class="st-button st-button-dark">Encuentra tu tecnología ↗</Link></div><div class="st-banner-art" aria-hidden="true"><span>CREATE.</span><span>CONNECT.</span><span>EXPLORE.</span><i>↗</i></div></section>
        <ProductSection title="Lo último en el catálogo." eyebrow="NUEVAS INCORPORACIONES" :products="recent" :availability="availability" href="/catalog?sort=newest"/>
        <ProductSection title="Una mirada a los precios especiales." eyebrow="PRECIOS ESPECIALES" :products="offers" :availability="availability" href="/catalog?offers=1"/>
        <section id="brands" class="st-section st-brands"><div class="st-section-heading"><div><p class="st-overline">EXPLORA POR MARCA</p><h2>Diferentes formas de crear.</h2></div><p class="st-subtle">Marcas ficticias del catálogo demo</p></div><div class="st-brand-grid"><Link v-for="brand in brands" :key="brand.slug" :href="catalogUrl({ brand: brand.slug })">{{ brand.name }} <span aria-hidden="true">↗</span></Link></div><p v-if="!brands.length" class="st-subtle">Próximamente encontrarás marcas publicadas aquí.</p></section>
        <section class="st-benefits"><div><p class="st-overline">CONOCE ANTES DE ELEGIR</p><h2>La claridad<br>también es tecnología.</h2></div><article><span aria-hidden="true">01 /</span><h3>Todos los detalles</h3><p>Consulta especificaciones, imágenes e información de garantía en cada ficha.</p></article><article><span aria-hidden="true">02 /</span><h3>A tu manera</h3><p>Explora por categoría, marca y precio, desde cualquier pantalla.</p></article><article><span aria-hidden="true">03 /</span><h3>Sin suposiciones</h3><p>Mostramos la cantidad disponible de cada producto. Si no tenemos un dato vigente, el producto no aparece en el catálogo.</p></article></section>
        <div v-if="!recent.length" class="st-empty"><h2>Estamos preparando {{ page.props.identity.name }}.</h2><p>Los productos publicados aparecerán aquí.</p><Link href="/catalog" class="st-button">Explorar catálogo</Link></div>
    </StoreLayout>
</template>

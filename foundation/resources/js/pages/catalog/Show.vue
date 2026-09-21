<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import StoreLayout from '../../layouts/StoreLayout.vue';
import StoreSeo from '../../components/storefront/StoreSeo.vue';
import ProductSection from '../../components/storefront/ProductSection.vue';
import AddToCart from '../../components/storefront/AddToCart.vue';
import StockStatus from '../../components/storefront/StockStatus.vue';
import { money, type Specification } from '../../types/catalog';
import { catalogUrl, type AvailabilityMap, type PublicProduct, type Seo } from '../../types/storefront';
const props = defineProps<{ product: PublicProduct; related: PublicProduct[]; availability: AvailabilityMap; seo: Seo }>();
const selectedId = ref<number>();
watch(() => props.product.slug, () => { selectedId.value = (props.product.images.find(i => i.is_primary) ?? props.product.images[0])?.id; }, { immediate: true });
const image = computed(() => props.product.images.find(i => i.id === selectedId.value));
const groups = computed(() => {
    const result = new Map<string, Specification[]>();
    props.product.specifications.forEach(spec => { const key = spec.group || 'General'; result.set(key, [...(result.get(key) ?? []), spec]); });
    return Array.from(result, ([name, specs]) => ({ name, specs }));
});
</script>
<template>
    <StoreSeo :seo="seo"/><StoreLayout>
        <nav class="st-breadcrumb" aria-label="Ruta de navegación"><Link href="/">Inicio</Link><span aria-hidden="true">/</span><Link href="/catalog">Catálogo</Link><span aria-hidden="true">/</span><Link :href="catalogUrl({ category: product.category.slug })">{{ product.category.name }}</Link></nav>
        <div class="st-detail">
            <section class="st-gallery" aria-label="Galería de producto"><div class="st-main-image"><span v-if="product.is_demo" class="st-badge">DEMOSTRACIÓN</span><img v-if="image" :src="image.url" :alt="image.alt" width="800" height="600" fetchpriority="high"><p v-else>Imagen por incorporar</p></div><div class="st-thumbnails"><button v-for="(item, index) in product.images" :key="item.id" :aria-label="`Ver imagen ${index + 1}: ${item.alt}`" :aria-pressed="selectedId === item.id" @click="selectedId = item.id"><img :src="item.url" :alt="item.alt" width="100" height="75" loading="lazy"></button></div><p class="st-image-note" v-if="product.is_demo">Ilustraciones de demostración. No representan un equipo comercial.</p></section>
            <section class="st-product-summary"><Link class="st-overline st-brand-link" :href="catalogUrl({ brand: product.brand.slug })">{{ product.brand.name }} ↗</Link><h1>{{ product.name }}</h1><p class="st-sku">SKU {{ product.sku }} <span>·</span> {{ product.is_demo ? 'Demostración' : 'Publicado en catálogo' }}</p><p class="st-summary-description">{{ product.short_description }}</p><div class="st-detail-price"><del v-if="product.previous_price_minor">{{ money(product.previous_price_minor, product.currency) }}</del><strong>{{ money(product.price_minor, product.currency) }}</strong><span>{{ product.currency }} · {{ product.is_demo ? 'Precio ilustrativo' : 'Precio publicado' }}</span></div><StockStatus :availability="availability[product.slug]" size="detail"/><AddToCart :slug="product.slug" :availability="availability[product.slug]"/><div class="st-summary-links"><a v-if="product.warranty?.trim()" href="#warranty">Información de garantía ↗</a><Link :href="catalogUrl({ category: product.category.slug })">Más en {{ product.category.name }} ↗</Link></div></section>
        </div>
        <div class="st-detail-information"><article><p class="st-overline">CONOCE EL PRODUCTO</p><h2>Una mirada más de cerca.</h2><p class="st-preserve">{{ product.description }}</p><section v-if="product.warranty?.trim()" id="warranty" class="st-warranty"><h3>Información de garantía</h3><p class="st-preserve">{{ product.warranty }}</p></section><p v-if="product.is_demo" class="st-demo-note">Este producto, su precio y su garantía son ilustrativos. No representan stock, una oferta real ni acuerdos con proveedores.</p></article><section id="specifications" class="st-specs"><p class="st-overline">CADA DETALLE CUENTA</p><h2>Especificaciones técnicas</h2><div v-for="group in groups" :key="group.name" class="st-spec-group"><h3>{{ group.name }}</h3><dl><div v-for="spec in group.specs" :key="spec.key"><dt>{{ spec.label }}</dt><dd>{{ spec.value }} {{ spec.unit }}</dd></div></dl></div><p v-if="!groups.length" class="st-subtle">Todavía no se han incorporado especificaciones.</p></section></div>
        <ProductSection title="Sigue explorando." eyebrow="EN LA MISMA CATEGORÍA" :products="related" :availability="availability" :href="catalogUrl({ category: product.category.slug })"/>
    </StoreLayout>
</template>

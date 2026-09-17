<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { money } from '../../types/catalog';
import type { PublicProduct } from '../../types/storefront';
const props = defineProps<{ product: PublicProduct }>();
const image = computed(() => props.product.images.find(image => image.is_primary) ?? props.product.images[0]);
</script>
<template>
    <article class="st-product-card">
        <Link :href="`/catalog/${product.slug}`" class="st-product-link">
            <div class="st-product-art">
                <img v-if="image" :src="image.url" :alt="image.alt" width="800" height="600" loading="lazy" decoding="async">
                <span v-else class="st-no-image">Imagen por incorporar</span>
                <span class="st-badge" v-if="product.previous_price_minor">Precio especial<span v-if="product.is_demo"> · Demo</span></span>
                <span class="st-card-arrow" aria-hidden="true">↗</span>
            </div>
            <div class="st-product-copy">
                <p class="st-overline">{{ product.brand.name }}</p>
                <h3>{{ product.name }}</h3>
                <p class="st-card-category">{{ product.category.name }}</p>
                <div class="st-price"><strong>{{ money(product.price_minor, product.currency) }}</strong><del v-if="product.previous_price_minor">{{ money(product.previous_price_minor, product.currency) }}</del></div>
                <p class="st-editorial"><span aria-hidden="true">◌</span> {{ product.is_demo ? 'Producto de demostración' : 'Publicado en catálogo' }}</p>
            </div>
        </Link>
    </article>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import ProductCard from './ProductCard.vue';
import type { PublicProduct } from '../../types/storefront';
defineProps<{ title: string; eyebrow: string; products: PublicProduct[]; href?: string }>();
</script>
<template><section v-if="products.length" class="st-section"><div class="st-section-heading"><div><p class="st-overline">{{ eyebrow }}</p><h2>{{ title }}</h2></div><Link v-if="href" :href="href" class="st-text-link">Ver todos <span aria-hidden="true">↗</span></Link></div><div class="st-product-grid"><ProductCard v-for="product in products" :key="product.slug" :product="product" /></div></section></template>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { Seo } from '../../types/storefront';
defineProps<{ seo: Seo }>();
</script>
<template>
    <Head :title="seo.title">
        <meta head-key="description" name="description" :content="seo.description">
        <meta head-key="robots" name="robots" :content="seo.robots">
        <link head-key="canonical" rel="canonical" :href="seo.url">
        <meta head-key="og:title" property="og:title" :content="seo.title">
        <meta head-key="og:description" property="og:description" :content="seo.description">
        <meta head-key="og:url" property="og:url" :content="seo.url">
        <meta head-key="og:type" property="og:type" content="website">
        <meta head-key="og:site_name" property="og:site_name" :content="seo.site_name">
        <meta head-key="og:locale" property="og:locale" :content="seo.locale">
        <meta v-if="seo.image" head-key="og:image" property="og:image" :content="seo.image">
    </Head>
</template>

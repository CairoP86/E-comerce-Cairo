<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AccountLayout from '../../layouts/AccountLayout.vue';
import type { SharedProps } from '../../types/auth';
import type { FreshnessSummary } from '../../types/freshness';
const props = defineProps<{ pendingOrders: number; freshnessSummary: FreshnessSummary }>();
const page = usePage<SharedProps>();
const attention = computed(() => props.freshnessSummary.expired + props.freshnessSummary.expiring + props.freshnessSummary.invalid);
</script>
<template><Head title="Panel operativo"/><AccountLayout title="Panel operativo" section="admin">
    <p class="muted">Acceso autorizado como {{ page.props.auth.user?.role }}. Esto es lo que necesita tu atención hoy.</p>
    <div class="panel-grid">
        <article class="info-card panel-card" aria-labelledby="panel-orders">
            <p id="panel-orders" class="eyebrow">PEDIDOS</p>
            <p class="panel-figure">{{ pendingOrders }}</p>
            <p>{{ pendingOrders === 1 ? 'pedido pendiente de pago' : 'pedidos pendientes de pago' }}</p>
            <Link href="/admin/orders" class="quiet-link">{{ pendingOrders ? 'Revisar pedidos →' : 'Ver pedidos →' }}</Link>
        </article>
        <article class="info-card panel-card" aria-labelledby="panel-offers">
            <p id="panel-offers" class="eyebrow">OFERTAS DEL CATÁLOGO</p>
            <template v-if="attention">
                <p class="panel-figure">{{ attention }}</p>
                <p>{{ attention === 1 ? 'oferta publicada requiere atención' : 'ofertas publicadas requieren atención' }}</p>
                <p class="panel-badges">
                    <span v-if="freshnessSummary.expired" class="freshness is-expired">{{ freshnessSummary.expired }} {{ freshnessSummary.expired === 1 ? 'vencida' : 'vencidas' }}</span>
                    <span v-if="freshnessSummary.expiring" class="freshness is-expiring">{{ freshnessSummary.expiring }} por vencer</span>
                    <span v-if="freshnessSummary.invalid" class="freshness is-invalid">{{ freshnessSummary.invalid }} sin dato válido</span>
                </p>
                <Link href="/admin/catalog/products" class="quiet-link">Actualizar ofertas →</Link>
            </template>
            <template v-else>
                <p class="panel-figure is-clear">✓</p>
                <p>Todas las ofertas de productos publicados están vigentes.</p>
                <Link href="/admin/catalog/products" class="quiet-link">Ver productos →</Link>
            </template>
        </article>
    </div>
</AccountLayout></template>

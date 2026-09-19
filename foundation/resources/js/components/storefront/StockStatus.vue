<script setup lang="ts">
import type { PublicAvailability } from '../../types/storefront';
defineProps<{ availability?: PublicAvailability; size?: 'card' | 'detail' }>();
</script>
<template>
    <p v-if="availability" class="st-stock" :class="[availability.state === 'available' ? 'is-available' : 'is-sold-out', size === 'detail' ? 'is-detail' : '']">
        <svg class="st-stock-icon" viewBox="0 0 16 16" width="14" height="14" aria-hidden="true" focusable="false">
            <path v-if="availability.state === 'available'" d="M3.5 8.5l3 3 6-7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path v-else d="M4 8h8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span v-if="availability.state === 'available'">{{ availability.quantity === 1 ? 'Queda 1 unidad' : `Quedan ${availability.quantity} unidades` }}</span>
        <span v-else>Agotado</span>
    </p>
</template>

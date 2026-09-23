<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
const props = defineProps<{ expiresAt: string }>();

// The hold is real and it runs out: this counts the same minutes the server is counting.
const now = ref(Date.now());
let ticker: ReturnType<typeof setInterval> | undefined;
onMounted(() => { ticker = setInterval(() => { now.value = Date.now(); }, 1000); });
onBeforeUnmount(() => clearInterval(ticker));

const remaining = computed(() => Math.max(0, Math.floor((Date.parse(props.expiresAt) - now.value) / 1000)));
const expired = computed(() => remaining.value === 0);
const clock = computed(() => `${Math.floor(remaining.value / 60)}:${String(remaining.value % 60).padStart(2, '0')}`);
const until = computed(() => new Intl.DateTimeFormat('es-CR', { hour: 'numeric', minute: '2-digit' }).format(new Date(props.expiresAt)));
</script>
<template>
    <!-- A second-by-second aria-live region would talk over everything, so only the ending speaks. -->
    <p v-if="!expired" class="st-hold" aria-live="off">
        <span class="st-hold-clock">{{ clock }}</span>
        <span>Apartamos tus unidades hasta las {{ until }} — si no confirmás el pedido antes, vuelven al catálogo.</span>
    </p>
    <p v-else class="st-hold is-expired" role="status">
        <span>La reserva venció.</span>
        <span>Actualizá la página para ver el estado actual de tu carrito.</span>
    </p>
</template>

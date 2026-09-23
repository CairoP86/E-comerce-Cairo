<script setup lang="ts">
// Where the customer is in a flow that really has three pages: cart, checkout, confirmation.
defineProps<{ current: 1 | 2 | 3 }>();
const steps = [{ n: 1, label: 'Tu carrito' }, { n: 2, label: 'Datos y entrega' }, { n: 3, label: 'Pedido recibido' }];
</script>
<template>
    <ol class="st-steps" aria-label="Pasos de la compra">
        <li v-for="step in steps" :key="step.n" :class="{ 'is-current': step.n === current, 'is-done': step.n < current }" :aria-current="step.n === current ? 'step' : undefined">
            <span class="st-step-mark" aria-hidden="true">{{ step.n < current ? '✓' : step.n }}</span>
            <span>{{ step.label }}</span>
            <span v-if="step.n < current" class="st-sr-only">, completado</span>
        </li>
    </ol>
</template>

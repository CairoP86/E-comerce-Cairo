<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import { money } from '../../types/catalog';
import type { CartLine } from '../../types/cart';
const props = defineProps<{ line: CartLine; busy: boolean; max: number }>();
const emit = defineEmits<{ update: [quantity: number]; remove: [] }>();
const quantity = ref(props.line.quantity);
watch(() => props.line.quantity, value => { quantity.value = value; });
const editable = computed(() => props.line.available || props.line.reason === 'out_of_stock' || props.line.reason === 'hold_expired');
const message = computed(() => ({
    unpublished: 'Este producto ya no está disponible en el catálogo. Retíralo para continuar.',
    currency_changed: 'La moneda de este producto cambió. Retíralo para continuar.',
    out_of_stock: 'La cantidad disponible cambió. Ajusta la cantidad o retira el producto.',
    hold_expired: 'Tu reserva de este producto venció. Resérvalo de nuevo para continuar.',
}[props.line.reason ?? 'unpublished']));
</script>
<template>
    <article class="st-cart-line" :class="{ 'is-unavailable': !line.available }">
        <div class="st-cart-image"><img v-if="line.image" :src="line.image.url" :alt="line.image.alt" width="160" height="120" loading="lazy"><span v-else aria-hidden="true">—</span></div>
        <div class="st-cart-line-content">
            <p class="st-overline">{{ line.is_demo ? 'DEMOSTRACIÓN' : 'TU SELECCIÓN' }}</p>
            <h2><Link v-if="line.slug" :href="`/catalog/${line.slug}`">{{ line.name }}</Link><span v-else>{{ line.name }}</span></h2>
            <p v-if="line.available && line.currency && line.unit_price_minor !== null" class="st-cart-unit">{{ money(line.unit_price_minor, line.currency) }} <span>por unidad</span></p>
            <p v-else class="st-cart-unavailable" :class="{ 'is-hold': line.reason === 'hold_expired' }">{{ message }} No se incluye en el subtotal.</p>
            <button v-if="line.reason === 'hold_expired'" type="button" class="st-cart-rehold" :disabled="busy" @click="emit('update', line.quantity)">Reservar de nuevo</button>
            <form v-if="editable" class="st-cart-quantity-form" @submit.prevent="emit('update', quantity)">
                <label :for="`quantity-${line.id}`">Cantidad</label>
                <div class="st-quantity-stepper"><button type="button" :disabled="busy || line.quantity <= 1" :aria-label="`Disminuir cantidad de ${line.name}`" @click="emit('update', line.quantity - 1)">−</button><input :id="`quantity-${line.id}`" v-model.number="quantity" type="number" inputmode="numeric" min="1" :max="max" step="1" required :disabled="busy"><button type="button" :disabled="busy || line.quantity >= max" :aria-label="`Aumentar cantidad de ${line.name}`" @click="emit('update', line.quantity + 1)">+</button></div>
                <button class="st-cart-update" :disabled="busy" type="submit">Actualizar</button>
            </form>
            <p v-else class="st-cart-unit">Cantidad guardada: {{ line.quantity }}</p>
            <button class="st-cart-remove" type="button" :disabled="busy" :aria-label="`Eliminar ${line.name}`" @click="emit('remove')">Eliminar</button>
        </div>
        <div class="st-cart-line-total"><span>Subtotal</span><strong v-if="line.available && line.subtotal_minor !== null && line.currency">{{ money(line.subtotal_minor, line.currency) }}</strong><strong v-else>—</strong></div>
    </article>
</template>

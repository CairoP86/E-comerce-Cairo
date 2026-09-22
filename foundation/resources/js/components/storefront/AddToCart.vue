<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useCartActions } from '../../composables/useCartActions';
import type { PublicAvailability } from '../../types/storefront';
const props = defineProps<{ slug: string; availability?: PublicAvailability }>();
const quantity = ref(1);
const { page, busy, errors, change } = useCartActions();
const soldOut = computed(() => props.availability?.state !== 'available');
const max = computed(() => Math.min(99, props.availability?.quantity ?? 0));
watch(() => props.slug, () => { quantity.value = 1; errors.value = {}; });
</script>
<template>
    <section class="st-add-cart" aria-labelledby="add-cart-title">
        <h2 id="add-cart-title">{{ soldOut ? 'Producto agotado.' : 'Guárdalo en tu carrito.' }}</h2>
        <p v-if="soldOut">No hay unidades disponibles en este momento. Vuelve a consultar más tarde.</p>
        <p v-else>No necesitas una cuenta. Al agregarlo, apartamos las unidades por tiempo limitado mientras completas tu pedido.</p>
        <div v-if="Object.keys(errors).length" id="cart-errors" class="st-form-error" role="alert" tabindex="-1"><p v-for="(message, key) in errors" :key="key">{{ message }}</p><Link href="/cart">Revisar mi carrito →</Link></div>
        <form v-if="!soldOut" class="st-add-cart-form" @submit.prevent="change('post', '/cart/items', { product_slug: slug, quantity })">
            <label for="add-quantity">Cantidad<input id="add-quantity" v-model.number="quantity" type="number" inputmode="numeric" min="1" :max="max" step="1" required :disabled="busy" aria-describedby="cart-quantity-note"></label>
            <button class="st-button" type="submit" :disabled="busy">{{ busy ? 'Agregando…' : 'Agregar al carrito' }} <span aria-hidden="true">+</span></button>
        </form>
        <p v-if="!soldOut" id="cart-quantity-note" class="st-cart-help">Puedes agregar hasta {{ max }} {{ max === 1 ? 'unidad' : 'unidades' }}.</p>
        <div v-if="page.props.cartStatus" class="st-cart-feedback" role="status">{{ page.props.cartStatus }} <Link href="/cart">Ver carrito →</Link></div>
        <p v-if="!soldOut" class="st-cart-help">La reserva es interna de esta tienda y no genera ningún cobro. Puedes crear un pedido pendiente de pago.</p>
    </section>
</template>

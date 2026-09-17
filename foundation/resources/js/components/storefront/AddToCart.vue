<script setup lang="ts">
import { ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useCartActions } from '../../composables/useCartActions';
const props = defineProps<{ slug: string }>();
const quantity = ref(1);
const { page, busy, errors, change } = useCartActions();
watch(() => props.slug, () => { quantity.value = 1; errors.value = {}; });
</script>
<template>
    <section class="st-add-cart" aria-labelledby="add-cart-title">
        <h2 id="add-cart-title">Guárdalo en tu carrito.</h2>
        <p>No necesitas una cuenta. Puedes revisar y cambiar tus productos antes de comprar.</p>
        <div v-if="Object.keys(errors).length" id="cart-errors" class="st-form-error" role="alert" tabindex="-1"><p v-for="(message, key) in errors" :key="key">{{ message }}</p><Link href="/cart">Revisar mi carrito →</Link></div>
        <form class="st-add-cart-form" @submit.prevent="change('post', '/cart/items', { product_slug: slug, quantity })">
            <label for="add-quantity">Cantidad<input id="add-quantity" v-model.number="quantity" type="number" inputmode="numeric" min="1" max="99" step="1" required :disabled="busy" aria-describedby="cart-quantity-note"></label>
            <button class="st-button" type="submit" :disabled="busy">{{ busy ? 'Agregando…' : 'Agregar al carrito' }} <span aria-hidden="true">+</span></button>
        </form>
        <p id="cart-quantity-note" class="st-cart-help">Límite temporal: 99 unidades por producto. No indica inventario disponible.</p>
        <div v-if="page.props.cartStatus" class="st-cart-feedback" role="status">{{ page.props.cartStatus }} <Link href="/cart">Ver carrito →</Link></div>
        <p class="st-cart-help">El carrito no reserva productos. Puedes crear un pedido pendiente de pago; no se realizará ningún cobro.</p>
    </section>
</template>

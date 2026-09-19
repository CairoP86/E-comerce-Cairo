<script setup lang="ts">
import { computed, nextTick } from 'vue';
import { Link } from '@inertiajs/vue3';
import StoreLayout from '../../layouts/StoreLayout.vue';
import StoreSeo from '../../components/storefront/StoreSeo.vue';
import CartLine from '../../components/storefront/CartLine.vue';
import { useCartActions } from '../../composables/useCartActions';
import { money } from '../../types/catalog';
import type { Cart } from '../../types/cart';
import type { Seo } from '../../types/storefront';
const props = defineProps<{ cart: Cart; seo: Seo }>();
const { page, busy, errors, change } = useCartActions();
const focusCart = () => nextTick(() => document.getElementById('cart-heading')?.focus());
const holdUntil = computed(() => props.cart.hold_expires_at ? new Intl.DateTimeFormat('es-CR', { hour: 'numeric', minute: '2-digit' }).format(new Date(props.cart.hold_expires_at)) : null);
</script>
<template>
    <StoreSeo :seo="seo"/><StoreLayout>
        <nav class="st-breadcrumb" aria-label="Ruta de navegación"><Link href="/">Inicio</Link><span aria-hidden="true">/</span><span>Tu carrito</span></nav>
        <header class="st-cart-heading"><div><p class="st-overline">TU PRÓXIMA ELECCIÓN</p><h1 id="cart-heading" tabindex="-1">Tu carrito.</h1><p>Revisa tu selección a tu ritmo. No necesitas crear una cuenta.</p></div><Link href="/catalog" class="st-text-link">← Continuar comprando</Link></header>
        <p v-if="page.props.cartStatus" class="st-cart-feedback" role="status">{{ page.props.cartStatus }}</p>
        <p v-if="page.props.errors?.checkout" class="st-form-error" role="alert">{{ page.props.errors.checkout }}</p>
        <div v-if="Object.keys(errors).length" id="cart-errors" class="st-form-error" role="alert" tabindex="-1"><p v-for="(message, key) in errors" :key="key">{{ message }}</p></div>
        <section v-if="!cart.lines.length" class="st-empty st-cart-empty"><svg viewBox="0 0 80 80" width="80" height="80" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M10 14h9l9 37h33l9-27H23M28 58h34"/><circle cx="32" cy="66" r="4"/><circle cx="59" cy="66" r="4"/></svg><p class="st-overline">HAY MUCHO POR DESCUBRIR</p><h2>Tu carrito espera una buena idea.</h2><p>Explora equipos, componentes y accesorios. Agrega lo que te interese y vuelve aquí cuando quieras.</p><Link href="/catalog" class="st-button">Explorar catálogo ↗</Link></section>
        <div v-else class="st-cart-grid" :aria-busy="busy">
            <section class="st-cart-items" aria-label="Productos del carrito"><div class="st-cart-items-heading"><h2>{{ cart.units }} {{ cart.units === 1 ? 'unidad' : 'unidades' }} en tu selección</h2><button type="button" class="st-cart-remove" :disabled="busy" @click="change('delete', '/cart', {}, focusCart)">Vaciar carrito</button></div><CartLine v-for="line in cart.lines" :key="line.id" :line="line" :busy="busy" :max="cart.max_quantity" @update="quantity => change('patch', `/cart/items/${line.id}`, { quantity })" @remove="change('delete', `/cart/items/${line.id}`, {}, focusCart)"/><p class="st-cart-help">Hasta {{ cart.max_quantity }} unidades por producto y {{ cart.max_lines }} productos distintos como límite temporal del carrito. No representa inventario.</p></section>
            <aside class="st-cart-summary" aria-labelledby="cart-summary-title"><p class="st-overline">TU SELECCIÓN, EN CLARO</p><h2 id="cart-summary-title">Resumen del carrito</h2><dl><div><dt>{{ cart.has_unavailable ? 'Subtotal de productos incluidos' : 'Subtotal de productos' }}</dt><dd>{{ money(cart.subtotal_minor, cart.currency!) }}</dd></div><div class="st-cart-total"><dt>Total provisional</dt><dd v-if="cart.total_minor !== null">{{ money(cart.total_minor, cart.currency!) }}</dd><dd v-else>Por revisar</dd></div></dl><p v-if="cart.has_unavailable" class="st-cart-unavailable" role="status">Hay productos que cambiaron. Retíralos para volver a calcular el total provisional.</p><p class="st-cart-summary-note">{{ cart.currency }} · Importes de productos únicamente. Transporte y cargos adicionales no calculados. El pago todavía no se procesa.</p><p v-if="holdUntil" class="st-hold-notice" role="status"><svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false"><circle cx="8" cy="8" r="6.5" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M8 4.5V8l2.5 1.5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg><span>Reservamos tus productos hasta las <strong>{{ holdUntil }}</strong>. Si no confirmas el pedido antes, las unidades se liberan para otros clientes.</span></p><Link v-if="!cart.has_unavailable" href="/checkout" class="st-button">Continuar con la compra</Link><Link href="/catalog" class="st-text-link">Seguir explorando ↗</Link><p class="st-cart-help">Los precios se actualizan al consultar tu carrito. La reserva es interna de esta tienda: no confirma la compra ni genera cobros.</p></aside>
        </div>
    </StoreLayout>
</template>

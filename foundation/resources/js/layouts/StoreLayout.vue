<script setup lang="ts">
import { Link, usePage, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import type { SharedProps } from '../types/auth';
import type { Identity } from '../types/storefront';
import type { CartSummary } from '../types/cart';
const page = usePage<SharedProps & { identity: Identity; cartSummary: CartSummary }>();
const menu = ref(false);
const menuButton = ref<HTMLButtonElement>();
const query = ref('');
watch(() => page.url, () => { menu.value = false; query.value = new URL(page.url, 'http://local').searchParams.get('q') ?? ''; }, { immediate: true });
function closeMenu() { menu.value = false; menuButton.value?.focus(); }
function search() { router.get('/catalog', query.value.trim() ? { q: query.value.trim() } : {}); }
</script>
<template>
    <div class="storefront">
        <a class="st-skip" href="#main-content">Saltar al contenido</a>
        <div class="st-announcement">Catálogo de demostración <span aria-hidden="true">·</span> Pedidos sin cobro</div>
        <header class="st-header" @keydown.esc="closeMenu">
            <div class="st-container st-header-row">
                <Link href="/" class="st-logo" :aria-label="`${page.props.identity.name}, inicio`"><span class="st-logo-mark" aria-hidden="true">{{ page.props.identity.mark }}</span><span>{{ page.props.identity.name }}</span></Link>
                <form role="search" class="st-search" @submit.prevent="search"><label class="st-sr-only" for="global-search">Buscar productos</label><input id="global-search" v-model="query" type="search" maxlength="100" placeholder="¿Qué tecnología estás buscando?"><button type="submit" aria-label="Buscar"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/></svg></button></form>
                <Link :href="page.props.auth.user ? '/account' : '/login'" class="st-account" :aria-label="page.props.auth.user ? 'Mi cuenta' : 'Ingresar a mi cuenta opcional'"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="7" r="4"/><path d="M4 22v-3a8 8 0 0 1 16 0v3"/></svg><span>{{ page.props.auth.user ? 'Mi cuenta' : 'Ingresar' }}</span></Link>
                <Link href="/cart" class="st-cart-link" :aria-label="`Carrito, ${page.props.cartSummary.units} unidades`"><svg viewBox="0 0 24 24" width="23" height="23" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M2 3h3l3 13h11l3-10H6M8 19h11"/><circle cx="9" cy="22" r="1"/><circle cx="18" cy="22" r="1"/></svg><span class="st-cart-link-text">Carrito</span><span class="st-cart-count">{{ page.props.cartSummary.units }}</span></Link>
                <button ref="menuButton" class="st-menu-toggle" :aria-expanded="menu" aria-controls="store-navigation" @click="menu = !menu">{{ menu ? 'Cerrar' : 'Menú' }} <span aria-hidden="true">{{ menu ? '×' : '☰' }}</span></button>
            </div>
            <nav id="store-navigation" class="st-container st-nav" :class="{ 'is-open': menu }" aria-label="Navegación principal"><Link href="/catalog">Explorar catálogo <span aria-hidden="true">↗</span></Link><Link href="/catalog?featured=1">Destacados</Link><Link href="/catalog?sort=newest">Novedades</Link><Link href="/catalog?offers=1">Ofertas demo</Link><a href="/#categories">Categorías</a><a href="/#brands">Marcas</a><span class="st-nav-region">{{ page.props.identity.region }}</span></nav>
        </header>
        <main id="main-content" tabindex="-1" class="st-container"><slot /></main>
        <footer class="st-footer"><div class="st-container"><div class="st-footer-grid"><div class="st-footer-brand"><Link href="/" class="st-logo"><span class="st-logo-mark" aria-hidden="true">{{ page.props.identity.mark }}</span>{{ page.props.identity.name }}</Link><p>{{ page.props.identity.tagline }}</p><p>Un espacio para explorar, conocer y elegir tu próximo equipo.</p></div><nav aria-label="Explorar"><h2>Explora</h2><Link href="/catalog">Todo el catálogo</Link><Link href="/catalog?featured=1">Destacados</Link><Link href="/catalog?sort=newest">Novedades</Link><a href="/#brands">Marcas</a></nav><nav aria-label="Cuenta"><h2>Tu espacio</h2><Link href="/login">Iniciar sesión</Link><Link href="/register">Crear cuenta</Link><Link href="/account">Mi cuenta</Link></nav><div><h2>Información del catálogo</h2><p>Productos y precios de demostración. Los pedidos quedan pendientes de pago; no se realizan cobros ni despachos.</p><p>Las condiciones de venta, entrega y atención se publicarán antes de la apertura comercial.</p></div></div><div class="st-footer-bottom"><span>{{ page.props.identity.name }} · {{ page.props.identity.region }}</span><span>Catálogo de demostración · Sin cobros</span><a href="#main-content">Volver arriba ↑</a></div></div></footer>
    </div>
</template>

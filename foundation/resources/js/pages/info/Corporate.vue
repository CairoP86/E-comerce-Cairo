<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import StoreLayout from '../../layouts/StoreLayout.vue';
import StoreSeo from '../../components/storefront/StoreSeo.vue';
import type { Contact, Seo } from '../../types/storefront';
const props = defineProps<{ contact: Contact; seo: Seo }>();
const hasChannel = computed(() => !!(props.contact.whatsapp || props.contact.email));
const cases = [
    { title: 'Compras por volumen', body: 'Varias unidades del mismo equipo para una oficina, un laboratorio o un aula.' },
    { title: 'Cotización formal', body: 'Documento con precios, plazos y condiciones para presentar en una proveeduría o junta.' },
    { title: 'Precios en dólares', body: 'Cotizaciones en USD para empresas que manejan su presupuesto en esa moneda.' },
    { title: 'Equipamiento a medida', body: 'Configuraciones específicas que no aparecen publicadas en el catálogo.' },
];
</script>
<template>
    <StoreSeo :seo="seo"/>
    <StoreLayout>
        <nav class="st-breadcrumb" aria-label="Ruta de navegación"><Link href="/">Inicio</Link><span aria-hidden="true">/</span><span>Venta corporativa</span></nav>
        <header class="st-catalog-heading">
            <p class="st-overline">EMPRESAS E INSTITUCIONES</p>
            <h1>Compras corporativas, atendidas por una persona.</h1>
            <p>El carrito está pensado para compras individuales en colones. Si necesitás una cotización formal, varias unidades o precios en dólares, lo vemos directamente con vos: es más rápido y podemos ajustar condiciones que el carrito no contempla.</p>
        </header>
        <section class="st-section" aria-labelledby="contact-heading">
            <div class="st-section-heading"><div><p class="st-overline">ESCRIBINOS</p><h2 id="contact-heading">Contanos qué necesitás.</h2></div></div>
            <div v-if="hasChannel" class="st-info-cards">
                <article v-if="contact.whatsapp" class="st-info-card"><h3>WhatsApp</h3><p>La vía más rápida para una consulta inicial y para coordinar una cotización.</p><p class="st-info-value">{{ contact.whatsapp }}</p><a :href="contact.whatsapp_url" class="st-button" rel="noopener noreferrer" target="_blank">Escribir por WhatsApp</a></article>
                <article v-if="contact.email" class="st-info-card"><h3>Correo electrónico</h3><p>Para solicitudes formales, órdenes de compra y documentación que necesite respaldo escrito.</p><a :href="`mailto:${contact.email}`" class="st-text-link">{{ contact.email }}</a></article>
            </div>
            <p v-else class="st-info-pending" role="status">Estamos habilitando el canal de atención corporativa. En cuanto esté disponible, publicamos aquí el WhatsApp y el correo de contacto.</p>
        </section>
        <section class="st-section" aria-labelledby="cases-heading">
            <div class="st-section-heading"><div><p class="st-overline">EN QUÉ TE PODEMOS AYUDAR</p><h2 id="cases-heading">Casos que atendemos aparte.</h2></div></div>
            <div class="st-info-cards">
                <article v-for="item in cases" :key="item.title" class="st-info-card"><h3>{{ item.title }}</h3><p>{{ item.body }}</p></article>
            </div>
        </section>
        <section class="st-section">
            <div class="st-info-note">
                <h2>Para una compra normal, el carrito es más rápido</h2>
                <p>Si vas a comprar una o dos unidades en colones, no hace falta escribirnos: podés hacer el pedido en línea y pagarlo por SINPE Móvil o transferencia.</p>
                <Link href="/metodos-de-pago" class="st-text-link">Ver cómo funciona el pago <span aria-hidden="true">↗</span></Link>
            </div>
        </section>
    </StoreLayout>
</template>

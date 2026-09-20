<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import StoreLayout from '../../layouts/StoreLayout.vue';
import StoreSeo from '../../components/storefront/StoreSeo.vue';
import type { Seo } from '../../types/storefront';
defineProps<{ seo: Seo }>();
const steps = [
    { title: 'Confirmás tu pedido', body: 'Al finalizar la compra el pedido queda registrado como pendiente de pago y recibís su número. Todavía no se cobra nada.' },
    { title: 'Te contactamos', body: 'Verificamos la disponibilidad con nuestro proveedor y te enviamos los datos para pagar: número de SINPE Móvil o cuenta bancaria, junto con el monto exacto.' },
    { title: 'Realizás el pago', body: 'Por SINPE Móvil o por transferencia, desde tu banco, por el monto total que aparece en tu pedido.' },
    { title: 'Nos enviás el comprobante', body: 'Respondés por el mismo canal con la captura o el comprobante, indicando tu número de pedido.' },
    { title: 'Confirmamos y preparamos', body: 'Registramos el pago en tu pedido, que cambia a pagado, y coordinamos la entrega contigo.' },
];
const methods = [
    { name: 'SINPE Móvil', body: 'Transferencia inmediata entre cuentas costarricenses desde tu banco o su aplicación. Te damos el número y el nombre del titular al confirmar el pedido.' },
    { name: 'Transferencia bancaria', body: 'Transferencia normal o SINPE a la cuenta que te indiquemos. Útil para montos mayores a los límites de SINPE Móvil.' },
];
</script>
<template>
    <StoreSeo :seo="seo"/>
    <StoreLayout>
        <nav class="st-breadcrumb" aria-label="Ruta de navegación"><Link href="/">Inicio</Link><span aria-hidden="true">/</span><span>Métodos de pago</span></nav>
        <header class="st-catalog-heading">
            <p class="st-overline">CÓMO PAGAR</p>
            <h1>Pago manual, verificado por nosotros.</h1>
            <p>Por ahora el pago no se procesa en línea. Hacés tu pedido, te enviamos los datos y confirmás la transferencia con nosotros. Así sabés exactamente a quién le estás pagando y nosotros verificamos cada pago antes de despachar.</p>
        </header>
        <section class="st-section" aria-labelledby="methods-heading">
            <div class="st-section-heading"><div><p class="st-overline">FORMAS DISPONIBLES</p><h2 id="methods-heading">Dos maneras de pagar.</h2></div></div>
            <div class="st-info-cards">
                <article v-for="method in methods" :key="method.name" class="st-info-card"><h3>{{ method.name }}</h3><p>{{ method.body }}</p></article>
            </div>
            <p class="st-cart-help">Todavía no aceptamos tarjeta en línea. Cuando esa opción esté disponible y verificada, aparecerá aquí.</p>
        </section>
        <section class="st-section" aria-labelledby="steps-heading">
            <div class="st-section-heading"><div><p class="st-overline">DESPUÉS DE TU PEDIDO</p><h2 id="steps-heading">Qué pasa, paso a paso.</h2></div></div>
            <ol class="st-steps">
                <li v-for="(step, index) in steps" :key="step.title"><span class="st-step-index" aria-hidden="true">0{{ index + 1 }}</span><div><h3>{{ step.title }}</h3><p>{{ step.body }}</p></div></li>
            </ol>
        </section>
        <section class="st-section">
            <div class="st-info-note">
                <h2>Mientras tanto, tu pedido te espera</h2>
                <p>Un pedido pendiente de pago no reserva inventario de forma indefinida ni establece una fecha de entrega. Si algo cambia con la disponibilidad antes de que pagues, te avisamos antes de cobrarte.</p>
                <p>Si tenés dudas sobre un pedido ya hecho, escribinos con su número a mano.</p>
                <Link href="/catalog" class="st-button">Seguir explorando el catálogo</Link>
            </div>
        </section>
    </StoreLayout>
</template>

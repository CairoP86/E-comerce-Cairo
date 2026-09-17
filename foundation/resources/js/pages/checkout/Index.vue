<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import StoreLayout from '../../layouts/StoreLayout.vue';
import OrderSummary from '../../components/storefront/OrderSummary.vue';
import { money } from '../../types/catalog';
import type { Review, Territory } from '../../types/order';
const props = defineProps<{ review: Review; territories: Territory[]; prefill: { email: string } }>();
const form = useForm({ token: props.review.token, first_name: '', last_name: '', email: props.prefill.email, phone: '', province_code: '', canton_code: '', district_code: '', exact_address: '', additional: '' });
const generalError = ref('');
for (const key of ['first_name', 'last_name', 'email', 'phone', 'province_code', 'canton_code', 'district_code', 'exact_address', 'additional'] as const) {
    watch(() => form[key], () => form.clearErrors(key));
}
watch(() => props.review.token, token => { form.token = token; });
const provinces = computed(() => [...new Map(props.territories.map(t => [t.province_code, { code: t.province_code, name: t.province }])).values()]);
const cantons = computed(() => [...new Map(props.territories.filter(t => t.province_code === form.province_code).map(t => [t.canton_code, { code: t.canton_code, name: t.canton }])).values()]);
const districts = computed(() => props.territories.filter(t => t.canton_code === form.canton_code));
watch(() => form.province_code, () => { form.canton_code = ''; form.district_code = ''; });
watch(() => form.canton_code, () => { form.district_code = ''; });
const fields = [
    { key: 'first_name' as const, label: 'Nombre', type: 'text', autocomplete: 'given-name', max: 100 },
    { key: 'last_name' as const, label: 'Apellidos', type: 'text', autocomplete: 'family-name', max: 150 },
    { key: 'email' as const, label: 'Correo electrónico', type: 'email', autocomplete: 'email', max: 254 },
    { key: 'phone' as const, label: 'Teléfono', type: 'tel', autocomplete: 'tel', max: 25 },
];
function submit() {
    generalError.value = '';
    form.post('/checkout', { preserveScroll: true, onError: async errors => {
        generalError.value = errors.checkout || errors.token || '';
        await nextTick();
        const first = Object.keys(errors).find(key => document.getElementById(key));
        document.getElementById(first || 'checkout-errors')?.focus();
    } });
}
</script>
<template><Head title="Revisa y confirma tu pedido"><meta name="robots" content="noindex, nofollow"/></Head><StoreLayout>
    <nav class="st-breadcrumb" aria-label="Ruta de navegación"><Link href="/cart">Tu carrito</Link><span aria-hidden="true">/</span><span>Finalizar pedido</span></nav>
    <header class="st-cart-heading"><div><p class="st-overline">CONTACTO · ENTREGA · REVISIÓN</p><h1>Un último vistazo.</h1><p>Crea tu pedido sin registrarte. El pago todavía no se procesa.</p></div></header>
    <div v-if="generalError" id="checkout-errors" tabindex="-1" class="st-form-error" role="alert">{{ generalError }}</div>
    <div class="st-checkout-mobile-summary"><span>Total de productos <strong>{{ money(review.total_minor, review.currency) }}</strong></span><a href="#checkout-review">Ver resumen ↓</a></div>
    <form class="st-checkout-grid" novalidate @submit.prevent="submit" :aria-busy="form.processing">
        <div class="st-checkout-fields">
            <fieldset class="st-checkout-section"><legend>1. Contacto</legend><div class="st-checkout-inputs"><div v-for="field in fields" :key="field.key" class="st-checkout-field"><label :for="field.key">{{ field.label }}</label><input :id="field.key" v-model="form[field.key]" :type="field.type" :autocomplete="field.autocomplete" :maxlength="field.max" required :aria-invalid="!!form.errors[field.key]" :aria-describedby="form.errors[field.key] ? `${field.key}-error` : field.key === 'phone' ? 'phone-help' : undefined"/><small v-if="field.key === 'phone'" id="phone-help">Costa Rica: 8 dígitos, con +506 opcional.</small><p v-if="form.errors[field.key]" :id="`${field.key}-error`" class="st-field-error">{{ form.errors[field.key] }}</p></div></div></fieldset>
            <fieldset class="st-checkout-section"><legend>2. Entrega en Costa Rica</legend><div class="st-checkout-inputs">
                <div class="st-checkout-field"><label for="province_code">Provincia</label><select id="province_code" v-model="form.province_code" required autocomplete="address-level1" :aria-invalid="!!form.errors.province_code" aria-describedby="province-error"><option value="">Selecciona provincia</option><option v-for="p in provinces" :key="p.code" :value="p.code">{{ p.name }}</option></select><p id="province-error" class="st-field-error">{{ form.errors.province_code }}</p></div>
                <div class="st-checkout-field"><label for="canton_code">Cantón</label><select id="canton_code" v-model="form.canton_code" required autocomplete="address-level2" :disabled="!form.province_code" :aria-invalid="!!form.errors.canton_code" aria-describedby="canton-error"><option value="">Selecciona cantón</option><option v-for="c in cantons" :key="c.code" :value="c.code">{{ c.name }}</option></select><p id="canton-error" class="st-field-error">{{ form.errors.canton_code }}</p></div>
                <div class="st-checkout-field st-checkout-wide"><label for="district_code">Distrito</label><select id="district_code" v-model="form.district_code" required autocomplete="address-level3" :disabled="!form.canton_code" :aria-invalid="!!form.errors.district_code" aria-describedby="district-error"><option value="">Selecciona distrito</option><option v-for="d in districts" :key="d.code" :value="d.code">{{ d.name }}</option></select><p id="district-error" class="st-field-error">{{ form.errors.district_code }}</p></div>
                <div class="st-checkout-field st-checkout-wide"><label for="exact_address">Dirección exacta / señas</label><textarea id="exact_address" v-model="form.exact_address" autocomplete="street-address" rows="3" maxlength="1000" required :aria-invalid="!!form.errors.exact_address" aria-describedby="address-error"/><p id="address-error" class="st-field-error">{{ form.errors.exact_address }}</p></div>
                <div class="st-checkout-field st-checkout-wide"><label for="additional">Información adicional <span>(opcional)</span></label><textarea id="additional" v-model="form.additional" rows="2" maxlength="500" :aria-invalid="!!form.errors.additional" aria-describedby="additional-error"/><p id="additional-error" class="st-field-error">{{ form.errors.additional }}</p></div>
            </div><p class="st-cart-help">El transporte aún no se calcula. Este pedido no establece una fecha de entrega.</p></fieldset>
        </div>
        <aside id="checkout-review" class="st-checkout-review" tabindex="-1"><p class="st-overline">3. REVISIÓN</p><OrderSummary :items="review.items" :currency="review.currency" :total="review.total_minor"/><p>Revisa tus datos y los productos antes de confirmar.</p><button class="st-button" type="submit" :disabled="form.processing">{{ form.processing ? 'Creando pedido…' : 'Confirmar pedido' }}</button><p class="st-cart-help" role="status">El pedido quedará pendiente de pago. No se realizará ningún cobro ni reserva de inventario.</p><Link href="/cart" class="st-text-link">Volver al carrito</Link></aside>
    </form>
</StoreLayout></template>

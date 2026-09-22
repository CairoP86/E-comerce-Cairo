<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import AccountLayout from '../../../layouts/AccountLayout.vue';
import CommercialErrors from '../../../components/CommercialErrors.vue';
interface Rule { id:number; name:string; multiplier:string }
const props=defineProps<{ rules:Rule[]; categories:{id:number;name:string;parent_id:number|null;status:string}[];assignments:{category_id:number;pricing_rule_id:number}[];canManage:boolean }>();
const selected=ref<number|null>(null),form=useForm({name:'',multiplier:''});
const assign=useForm({category_id:'' as number|string,pricing_rule_id:null as number|null});
function edit(rule:Rule){selected.value=rule.id;form.name=rule.name;form.multiplier=rule.multiplier;document.getElementById('rule-name')?.focus();}
function save(){const options={onError:()=>nextTick(()=>document.getElementById('rule-errors')?.focus())};if(selected.value)form.put(`/admin/commercial/rules/${selected.value}`,options);else form.post('/admin/commercial/rules',options);}
function saveAssignment(){assign.put('/admin/commercial/category-rule',{onError:()=>nextTick(()=>document.getElementById('assignment-errors')?.focus())});}
// Same shape as the categories tree: "×1,40", never "× 1.4000".
const multiplier=(value:string)=>'×'+Number(value).toLocaleString('es-CR',{minimumFractionDigits:2,maximumFractionDigits:4});
// Names carry their multiplier ("Estándar ×1,40"); the figure below says it, so the title drops it.
const shortName=(name:string)=>name.replace(/\s*×\s*[\d.,]+\s*$/,'').trim()||name;
// Each rule shows what it actually affects; the tree in Categorías stays the full picture.
const used=computed(()=>Object.fromEntries(props.rules.map(rule=>[rule.id,props.assignments.filter(a=>a.pricing_rule_id===rule.id).map(a=>props.categories.find(c=>c.id===a.category_id)?.name).filter(Boolean).sort()])));
</script>
<template><Head title="Reglas comerciales"><meta name="robots" content="noindex, nofollow"/></Head><AccountLayout title="Reglas comerciales" section="admin">
    <p class="muted">Son multiplicadores sobre el costo, no porcentajes de margen. No cambian el precio público automáticamente. Prioridad: excepción del producto → regla de la categoría más cercana. Sin regla, no se propone un precio.</p>

    <div class="rule-grid">
        <article v-for="rule in rules" :key="rule.id" class="info-card is-consult rule-card">
            <p class="eyebrow">{{ shortName(rule.name).toLocaleUpperCase('es') }}</p>
            <p class="rule-figure">{{ multiplier(rule.multiplier) }}</p>
            <p class="rule-used">
                <template v-if="used[rule.id].length">Aplica a {{ used[rule.id].length }} {{ used[rule.id].length === 1 ? 'categoría' : 'categorías' }}: {{ used[rule.id].join(' · ') }}</template>
                <template v-else>Todavía no la usa ninguna categoría.</template>
            </p>
            <button v-if="canManage" type="button" class="quiet-link" @click="edit(rule)">Editar {{ shortName(rule.name) }}</button>
        </article>
        <p v-if="!rules.length" class="muted">Todavía no hay reglas.</p>
    </div>
    <p class="muted rules-tree-link">Las subcategorías heredan la regla de su superior más cercano. <Link href="/admin/catalog/categories" class="quiet-link">Ver el árbol de categorías →</Link></p>

    <form v-if="canManage" class="info-card is-work compact-form" @submit.prevent="save"><h2>{{ selected ? 'Editar regla' : 'Nueva regla' }}</h2><fieldset :disabled="form.processing"><label for="rule-name">Nombre<input id="rule-name" v-model="form.name" required maxlength="120"></label><label>Multiplicador<input v-model="form.multiplier" inputmode="decimal" required maxlength="8" placeholder="1.4000" :aria-invalid="!!form.errors.multiplier" aria-describedby="rule-errors-multiplier"></label><CommercialErrors :errors="form.errors" prefix="rule-errors"/><button class="button">Guardar regla</button><button type="button" class="quiet-link" @click="selected=null;form.reset()">Nueva regla</button></fieldset></form>

    <form v-if="canManage" class="info-card is-work compact-form" @submit.prevent="saveAssignment"><h2>Asignar una regla a una categoría</h2><fieldset :disabled="assign.processing"><label>Categoría<select v-model="assign.category_id" required><option value="" disabled>Seleccionar categoría</option><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option></select></label><label>Regla<select v-model="assign.pricing_rule_id"><option :value="null">Sin regla propia / heredar</option><option v-for="rule in rules" :key="rule.id" :value="rule.id">{{ shortName(rule.name) }} {{ multiplier(rule.multiplier) }}</option></select></label><CommercialErrors :errors="assign.errors" prefix="assignment-errors"/><button class="button">Guardar asignación</button></fieldset></form>
</AccountLayout></template>

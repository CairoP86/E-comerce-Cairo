<script setup lang="ts">
import { computed } from 'vue';
export interface Day { date: string; label: string; created: number; paid: number }
const props = defineProps<{ days: Day[]; range: number }>();

// Below four days with movement a chart says less than a sentence does (UI Pro Max: use a stat, not a chart).
const withMovement = computed(() => props.days.filter(day => day.created || day.paid).length);
const peak = computed(() => Math.max(1, ...props.days.map(day => Math.max(day.created, day.paid))));
const height = (value: number) => `${Math.round((value / peak.value) * 100)}%`;
const totals = computed(() => ({ created: props.days.reduce((sum, d) => sum + d.created, 0), paid: props.days.reduce((sum, d) => sum + d.paid, 0) }));
// The first, middle and last day carry the axis; the rest would collide at 30 days.
const ticks = computed(() => [props.days[0], props.days[Math.floor(props.days.length / 2)], props.days[props.days.length - 1]].filter(Boolean));
const summary = computed(() => `Pedidos por día en los últimos ${props.range} días: ${totals.value.created} creados y ${totals.value.paid} confirmados.`);
const dayLabel = (day: Day) => `${day.label}: ${day.created} ${day.created === 1 ? 'creado' : 'creados'}, ${day.paid} ${day.paid === 1 ? 'confirmado' : 'confirmados'}`;
</script>
<template>
    <template v-if="withMovement >= 4">
        <div class="chart" role="img" :aria-label="summary">
            <div class="chart-plot">
                <div v-for="day in days" :key="day.date" class="chart-day" :title="dayLabel(day)">
                    <span class="chart-bar is-created" :style="{ height: height(day.created) }" :class="{ 'is-zero': !day.created }"></span>
                    <span class="chart-bar is-paid" :style="{ height: height(day.paid) }" :class="{ 'is-zero': !day.paid }"></span>
                </div>
            </div>
            <p class="chart-axis"><span v-for="tick in ticks" :key="tick.date">{{ tick.label }}</span></p>
        </div>
        <p class="chart-legend">
            <span class="chart-key is-created"></span> creados
            <span class="chart-key is-paid"></span> confirmados <small>(rayado)</small>
            <span class="chart-peak">Máximo diario: {{ peak }}</span>
        </p>
        <!-- The same numbers as text: a chart alone is not readable by a screen reader. The wrapper
             does the hiding, because a table sizes itself and ignores a 1px width. -->
        <div class="visually-hidden"><table>
            <caption>{{ summary }}</caption>
            <thead><tr><th>Día</th><th>Creados</th><th>Confirmados</th></tr></thead>
            <tbody><tr v-for="day in days" :key="day.date"><th>{{ day.label }}</th><td>{{ day.created }}</td><td>{{ day.paid }}</td></tr></tbody>
        </table></div>
    </template>
    <p v-else class="chart-empty muted">
        Todavía no hay suficientes pedidos para dibujar una tendencia: {{ withMovement }} {{ withMovement === 1 ? 'día' : 'días' }} con movimiento en {{ range }}.
        <span class="block">En total: {{ totals.created }} {{ totals.created === 1 ? 'pedido creado' : 'pedidos creados' }} y {{ totals.paid }} {{ totals.paid === 1 ? 'confirmado' : 'confirmados' }}.</span>
    </p>
</template>

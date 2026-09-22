<script setup lang="ts">
import { computed } from 'vue';
import { statusLabel, type CatalogStatus } from '../types/catalog';
// One badge for every status the admin shows. Order labels come from the server (`label`); catalogue
// labels have their own helper. Tones resolve to the --admin-* state tokens.
const props = defineProps<{ status: string; label?: string }>();
const tones: Record<string, string> = { published: 'ok', paid: 'ok', active: 'ok', draft: 'info', pending_payment: 'warn', archived: 'neutral', inactive: 'neutral' };
const tone = computed(() => tones[props.status] ?? 'neutral');
const text = computed(() => props.label ?? (['draft', 'published', 'archived'].includes(props.status) ? statusLabel(props.status as CatalogStatus) : props.status));
</script>
<template><span class="status-badge" :class="`is-${tone}`">{{ text }}</span></template>

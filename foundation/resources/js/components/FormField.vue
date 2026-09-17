<script setup lang="ts">
withDefaults(defineProps<{ id: string; label: string; type?: string; autocomplete?: string; error?: string; required?: boolean; minlength?: number; maxlength?: number }>(), { type: 'text', required: true });
const value = defineModel<string>({ required: true });
</script>
<template><div class="field"><label :for="id">{{ label }}</label><input :id="id" v-model="value" :name="id" :type="type" :autocomplete="autocomplete" :required="required" :minlength="minlength" :maxlength="maxlength" :aria-invalid="Boolean(error)" :aria-describedby="error ? `${id}-error` : undefined"><p v-if="error" :id="`${id}-error`" class="field-error" role="alert">{{ error }}</p></div></template>

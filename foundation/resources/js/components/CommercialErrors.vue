<script setup lang="ts">
defineProps<{ errors: Record<string,string>; prefix: string }>();
</script>
<template><div v-if="Object.keys(errors).length" :id="prefix" tabindex="-1" role="alert"><p v-for="(error,key) in errors" :key="key" :id="`${prefix}-${key}`" class="field-error">{{ error }}</p></div></template>

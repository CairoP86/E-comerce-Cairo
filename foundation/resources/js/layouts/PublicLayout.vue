<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import type { SharedProps } from '../types/auth';
import type { Identity } from '../types/storefront';
const page = usePage<SharedProps & { identity: Identity }>();
</script>
<template>
    <div class="shell">
        <header class="site-header"><Link href="/" class="brand" :aria-label="`${page.props.identity.name}, inicio`"><span class="brand-mark" aria-hidden="true">{{ page.props.identity.mark }}</span><span>{{ page.props.identity.name }}</span></Link><nav aria-label="Navegación principal"><Link :href="page.props.auth.user ? '/account' : '/login'">{{ page.props.auth.user ? 'Mi cuenta' : 'Iniciar sesión' }}</Link><Link v-if="!page.props.auth.user" href="/register" class="button small">Crear cuenta ↗</Link><Link v-else href="/logout" method="post" as="button">Cerrar sesión</Link></nav></header>
        <main><slot /></main>
        <footer class="site-footer"><span>{{ page.props.identity.tagline }}</span><span>{{ page.props.identity.name }} · {{ page.props.identity.region }}</span></footer>
    </div>
</template>

import { nextTick, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import type { CartSummary } from '../types/cart';

export function useCartActions() {
    const page = usePage<{ cartSummary: CartSummary; cartStatus: string | null }>();
    const busy = ref(false);
    const errors = ref<Record<string, string>>({});
    function change(method: 'post' | 'patch' | 'delete', url: string, data: Record<string, string | number> = {}, after?: () => void) {
        if (busy.value) return;
        errors.value = {};
        busy.value = true;
        router.visit(url, {
            method, data: { ...data, mutation_id: crypto.randomUUID(), revision: page.props.cartSummary.revision },
            preserveScroll: true, preserveState: true,
            onSuccess: () => { after?.(); },
            onError: returned => { errors.value = returned; nextTick(() => document.getElementById('cart-errors')?.focus()); },
            onFinish: () => { busy.value = false; },
        });
    }
    return { page, busy, errors, change };
}

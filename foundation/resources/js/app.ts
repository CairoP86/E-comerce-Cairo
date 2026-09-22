import '../css/app.css';
import '../css/catalog.css';
import '../css/storefront-tokens.css';
import '../css/storefront.css';
import '../css/cart.css';
import '../css/checkout.css';
import '../css/commercial.css';
import { createApp, h, type DefineComponent } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

// The server already painted the right theme; this keeps it right while navigating without reloads.
// Same rule as App\Support\AdminTheme: the private area is light, the storefront stays dark.
const isLight = (component: string) => component.startsWith('admin/') || component.startsWith('account/');
const applyTheme = (component: string) => document.body.classList.toggle('theme-light', isLight(component));
router.on('navigate', event => applyTheme(event.detail.page.component));

createInertiaApp({
    title: (title) => title,
    resolve: (name) => resolvePageComponent(
        `./pages/${name}.vue`,
        import.meta.glob<DefineComponent>('./pages/**/*.vue'),
    ),
    setup({ el, App, props, plugin }) {
        applyTheme(props.initialPage.component);
        createApp({ render: () => h(App, props) }).use(plugin).mount(el);
    },
});

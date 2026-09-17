import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({ input: ['resources/js/app.ts'], refresh: true }),
        vue(),
        tailwindcss(),
    ],
    server: { host: '127.0.0.1' },
});

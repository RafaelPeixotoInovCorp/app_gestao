import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    resolve: {
        dedupe: ['axios'],
    },
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
            // Usa certificados do Laravel Herd / Valet para `npm run dev` em https://*.test
            detectTls: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
});

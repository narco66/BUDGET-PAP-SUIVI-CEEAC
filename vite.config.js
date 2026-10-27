import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/ceeac.css',
                'resources/js/app.js',
                'resources/js/charts.js',
                'resources/js/echo.js',
                'resources/js/cockpit-live.js',
            ],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        // IPv4 explicite : sous Windows, « localhost » se résout en [::1], adresse qu'une politique CSP
        // ne peut pas autoriser — les pages perdaient alors ceeac.css (fond bleu, menu).
        host: '127.0.0.1',
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});

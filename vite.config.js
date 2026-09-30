import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                // Dipisah agar tiap halaman hanya memuat pustaka yang benar-benar
                // dipakainya; peta dan grafik tidak ikut terunduh di halaman publik.
                'resources/js/map.js',
                'resources/js/chart.js',
                'resources/js/scanner.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});

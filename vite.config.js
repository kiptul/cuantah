import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                // Entry terpisah: Leaflet dan Chart.js hanya dipakai di sebagian
                // halaman, jadi memasukkannya ke app.js akan membebani setiap
                // halaman publik dengan berkas yang tidak pernah dipakai.
                'resources/js/leaflet.js',
                'resources/js/chart.js',
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

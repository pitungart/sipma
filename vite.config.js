import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                // Tema panel Filament (shell mengambang, kepadatan tabel) — lihat §4.1 design rules
                'resources/css/filament/admin/theme.css',
            ],
            refresh: true,
        }),
    ],
});

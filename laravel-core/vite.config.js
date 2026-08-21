import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            publicDirectory: '../public_html',
            input: [
                'resources/css/app.css',
                'resources/css/filament/cork-admin-shell.css',
                'resources/css/filament/cork-admin-layouts.css',
                'resources/css/filament/cork-admin-forms.css',
                'resources/css/filament/cork-admin-tables.css',
                'resources/css/filament/cork-admin-widgets.css',
                'resources/css/filament/cork-admin-personnel.css',
                'resources/css/filament/cork-admin-ecommerce-web.css',
                'resources/css/filament/cork-admin-offers.css',
                'resources/css/filament/cork-admin-restaurant.css',
                'resources/css/filament/cork-admin-accounting.css',
                'resources/css/filament/cork-admin-technical-service.css',
                'resources/css/filament/cork-admin-actions.css',
                'resources/css/filament/cork-admin-overlays.css',
                'resources/css/filament/cork-admin-sales-operations.css',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
    ],
});

import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/shared.css',
                'resources/css/shared-layout.css',
                'resources/css/components/footer.css',
                'resources/css/admin/admin-layout.css',
                'resources/css/admin/dashboard.css',
                'resources/css/seller/seller-layout.css',
                'resources/css/seller/dashboard.css',
                'resources/css/seller/products.css',
                'resources/css/buyer/home.css',
                'resources/css/auth/login.css',
                'resources/css/auth/social-role.css',
                'resources/css/logistics/logistics-layout.css',
                'resources/css/logistics/dashboard.css',
                'resources/css/rider/rider-layout.css',
                'resources/css/shipments-label.css',
                'resources/js/app.js',
                'resources/js/buyer/home.js',
                'resources/js/buyer/products.js',
                'resources/js/buyer/checkout.js',
                'resources/js/seller/layout.js',
                'resources/js/seller/dashboard.js',
                'resources/js/seller/reports.js',
                'resources/js/seller/account.js',
                'resources/js/seller/products/create.js',
                'resources/js/seller/products/edit.js',
                'resources/js/logistics/layout.js',
                'resources/js/logistics/dashboard.js',
                'resources/js/logistics/riders.js',
                'resources/js/logistics/shipments.js',
                'resources/js/rider/layout.js',
                'resources/js/rider/dashboard.js',
                'resources/js/rider/deliveries.js',
                'resources/js/rider/pickups.js',
                'resources/js/rider/profit.js',
                'resources/js/rider/account.js',
                'resources/js/auth/login.js',
                'resources/js/auth/register.js',
                'resources/js/auth/social-register.js',
                'resources/js/auth/apply-rider.js',
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
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});

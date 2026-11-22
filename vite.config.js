import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                "resources/css/app.css",
                "resources/css/storefront.css",
                "resources/css/pages/admin-dashboard.css",
                "resources/css/pages/track-order.css",
                "resources/css/pages/customer-cart.css",
                "resources/js/app.js",
            ],
            refresh: true,
        }),
    ],
});

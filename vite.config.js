import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                "resources/css/app.css",
                "resources/css/storefront.css",
                "resources/css/pages/admin-dashboard.css",
                "resources/css/pages/admin-orders.css",
                "resources/css/pages/track-order.css",
                "resources/css/pages/customer-cart.css",
                "resources/css/pages/customer-checkout.css",
                "resources/css/pages/customer-orders.css",
                "resources/css/pages/customer-history.css",
                "resources/css/pages/welcome.css",
                "resources/js/app.js",
                "resources/css/pages/admin-product-form.css",
            ],
            refresh: true,
        }),
    ],
});

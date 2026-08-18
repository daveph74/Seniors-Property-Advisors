import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.jsx'],
            refresh: true,
        }),
        react(),
    ],
    server: {
        /*
         * Named, because Vite otherwise binds whatever the machine offers and writes that into
         * `public/hot` — here `http://[::1]:5173`, the IPv6 loopback. A content policy cannot express
         * an IPv6 literal at all: Chrome discards the source as invalid, every script and stylesheet
         * on the page is then refused, and the site renders blank with the reason only in the console.
         */
        host: 'localhost',
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});

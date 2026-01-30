import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const isProduction = mode === 'production';
    const appUrl = isProduction ? 'https://marketing.interprima.co.id' : 'http://localhost';

    return {
        // base: isProduction ? '/build/' : '/', // Tetap pertahankan ini

        plugins: [
            laravel({
                input: 'resources/js/app.js',
                refresh: !isProduction,
            }),
            vue({
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                },
            }),
            VitePWA({
                registerType: 'autoUpdate',
                injectRegister: 'auto',
                base: '/', 
                devOptions: {
                    enabled: !isProduction
                },
                workbox: {
                    // TAMBAHKAN INI: Mengatasi error "non-precached-url"
                    navigateFallback: '/',
                    navigateFallbackAllowlist: [/^(?!\/__).*/],
                    
                    cleanupOutdatedCaches: true,
                    globPatterns: ['**/*.{js,css,html,ico,png,svg,json,woff2}'],
                    skipWaiting: isProduction,
                    clientsClaim: isProduction,
                },
                manifest: {
                    name: 'Planning Monitoring System',
                    short_name: 'PlanlyApp',
                    description: 'Planning and Monitoring Application',
                    theme_color: '#2563eb',
                    background_color: '#ffffff',
                    display: 'standalone',
                    orientation: 'portrait',
                    start_url: '/',
                    scope: '/',
                    icons: [
                        {
                            src: '/logo/logo.png',
                            sizes: '192x192',
                            type: 'image/png',
                            purpose: 'any maskable'
                        },
                        {
                            src: '/logo/logo.png',
                            sizes: '512x512',
                            type: 'image/png',
                            purpose: 'any maskable'
                        }
                    ]
                }
            })
        ],

        build: {
            outDir: 'public/build',
            minify: isProduction ? 'terser' : false,
            // ... (terserOptions tetap sama)
            rollupOptions: {
                output: {
                    manualChunks: {
                        vendor: ['vue', '@inertiajs/vue3', 'axios'],
                        ui: ['@headlessui/vue', 'sweetalert2'],
                    },
                },
            },
            sourcemap: !isProduction,
            chunkSizeWarningLimit: 1000,
        },

        server: {
            host: '0.0.0.0',
            port: 5174,
            hmr: {
                host: isProduction ? 'marketing.interprima.co.id' : 'localhost',
                protocol: isProduction ? 'wss' : 'ws', 
            },
        },

        define: {
            'process.env.VITE_APP_URL': JSON.stringify(appUrl),
        },

        resolve: {
            alias: {
                '@': '/resources/js',
            },
        },
    };
});

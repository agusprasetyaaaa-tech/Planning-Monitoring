import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const isProduction = mode === 'production';
    
    // Pastikan ini menggunakan HTTPS untuk production
    const appUrl = isProduction ? 'https://marketing.interprima.co.id' : 'http://localhost';

    return {
        // PENTING: Memastikan base path aset selalu relatif atau benar
        base: isProduction ? '/build/' : '/',

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
                // PENTING: Gunakan 'build' sebagai base untuk service worker di production
                base: '/', 
                devOptions: {
                    enabled: !isProduction
                },
                workbox: {
                    cleanupOutdatedCaches: true,
                    // Pastikan service worker meng-cache aset di folder build
                    globPatterns: ['**/*.{js,css,html,ico,png,svg,json,woff2}'],
                    skipWaiting: isProduction,
                    clientsClaim: isProduction,
                },
                manifest: {
                    name: 'Planning Monitoring System',
                    short_name: 'PlanlyApp',
                    description: 'Planning and Monitoring Application',
                    theme_color: '#2563eb', // Disamakan dengan app.blade.php
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
                            purpose: 'any maskable' // Tambahkan ini agar PWA lebih kompatibel
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
            terserOptions: isProduction ? {
                compress: {
                    drop_console: true,
                    drop_debugger: true,
                },
            } : {},
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
                // PENTING: Agar Hot Module Replacement jalan via HTTPS
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

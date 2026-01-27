import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig(({ mode }) => {
    // Load env file based on `mode` in the current working directory.
    const env = loadEnv(mode, process.cwd(), '');

    const isProduction = mode === 'production';
    const appUrl = isProduction ? 'http://103.56.148.34:8082' : 'http://localhost';

    return {
        plugins: [
            laravel({
                input: 'resources/js/app.js',
                refresh: !isProduction, // Disable refresh in production
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
                devOptions: {
                    enabled: !isProduction // Only enable in development
                },
                workbox: {
                    cleanupOutdatedCaches: true,
                    globPatterns: ['**/*.{js,css,html,ico,png,svg,json,vue,txt,woff2}'],
                    // Skip waiting in production for immediate updates
                    skipWaiting: isProduction,
                    clientsClaim: isProduction,
                },
                manifest: {
                    name: 'Planning Monitoring System',
                    short_name: 'PlanlyApp',
                    description: 'Planning and Monitoring Application',
                    theme_color: '#009688',
                    background_color: '#ffffff',
                    display: 'standalone',
                    orientation: 'portrait',
                    start_url: '/',
                    scope: '/',
                    icons: [
                        {
                            src: '/logo/logo.png',
                            sizes: '192x192',
                            type: 'image/png'
                        },
                        {
                            src: '/logo/logo.png',
                            sizes: '512x512',
                            type: 'image/png'
                        }
                    ]
                }
            })
        ],

        // Build configuration
        build: {
            // Output directory
            outDir: 'public/build',

            // Production optimizations
            minify: isProduction ? 'terser' : false,
            terserOptions: isProduction ? {
                compress: {
                    drop_console: true,
                    drop_debugger: true,
                },
            } : {},

            // Chunk splitting for better caching
            rollupOptions: {
                output: {
                    manualChunks: {
                        vendor: ['vue', '@inertiajs/vue3', 'axios'],
                        ui: ['@headlessui/vue', 'sweetalert2'],
                    },
                },
            },

            // Source maps only in development
            sourcemap: !isProduction,

            // Asset size warnings
            chunkSizeWarningLimit: 1000,
        },

        // Server configuration (Development only - not used in production build)
        // In production, 'npm run build' creates static assets, no dev server needed
        server: {
            host: '0.0.0.0',
            port: 5174,
            hmr: {
                host: 'localhost',
            },
        },

        // Define environment variables for client
        define: {
            'process.env.VITE_APP_URL': JSON.stringify(appUrl),
        },

        // Resolve configuration
        resolve: {
            alias: {
                '@': '/resources/js',
            },
        },

        // Enable esbuild optimizations
        esbuild: {
            drop: isProduction ? ['console', 'debugger'] : [],
        },
    };
});
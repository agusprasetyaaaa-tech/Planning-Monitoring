import '../css/app.css';
import './bootstrap';
import { registerSW } from 'virtual:pwa-register';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h, ref, onMounted } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import SplashScreen from './Components/SplashScreen.vue';

// UPDATE: Ganti 'Laravel' menjadi 'Planly App' sebagai default
const appName = import.meta.env.VITE_APP_NAME || 'Planning Monitoring System';

createInertiaApp({
    // Jika title halaman kosong, maka tampilkan hanya 'Planly App'
    title: (title) => title ? `${title} - Planning Monitoring System` : 'Planning Monitoring System',

    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({
            setup() {
                // Only show splash screen if starting on the Login page
                const isLoginPage = ['Auth/NexusLogin', 'Auth/Login'].includes(props.initialPage.component);
                const showSplash = ref(isLoginPage);

                onMounted(() => {
                    if (showSplash.value) {
                        setTimeout(() => {
                            showSplash.value = false;
                        }, 2000);
                    }
                });

                return () => [
                    h(SplashScreen, { visible: showSplash.value }),
                    h(App, props)
                ];
            }
        })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#2563eb', // Disamakan dengan theme color Anda
    },
});

registerSW({
    immediate: true,
});
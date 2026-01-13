import '../css/app.css';
import './bootstrap';
import { registerSW } from 'virtual:pwa-register';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h, ref, onMounted } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import SplashScreen from './Components/SplashScreen.vue';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
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
        color: '#4B5563',
    },
});

registerSW({
    immediate: true,
});

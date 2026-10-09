import './bootstrap';
import '../css/app.css';

import { createApp, h, type DefineComponent } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

// Gracefully handle 419 (CSRF token mismatch / expired page) by reloading with fresh token
router.on('httpException', (event: any) => {
    if (event.detail?.response?.status === 419) {
        window.location.reload();
        return false;
    }
});

// Refresh stale pages restored from browser back-forward cache (bfcache)
if (typeof window !== 'undefined') {
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            window.location.reload();
        }
    });
}

const appName = (import.meta.env.VITE_APP_NAME as string) || 'LifeLoop';

const appElement = document.getElementById('app');
const initialPage = appElement?.dataset.page
    ? JSON.parse(appElement.dataset.page)
    : undefined;

createInertiaApp({
    page: initialPage,
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./Pages/**/*.vue')
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#4F46E5',
    },
});

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

function getInitialPage(): any {
    // 1. Try div#app dataset.page (Inertia v1 / Laravel default)
    const appElement = document.getElementById('app');
    if (appElement?.dataset.page) {
        try {
            return JSON.parse(appElement.dataset.page);
        } catch (e) {
            console.error('Failed to parse #app dataset.page', e);
        }
    }

    // 2. Try JSON script tag (Inertia v2 format)
    const script = document.querySelector<HTMLScriptElement>('script[data-page="app"]');
    if (script?.textContent) {
        try {
            return JSON.parse(script.textContent);
        } catch (e) {
            console.error('Failed to parse script data-page', e);
        }
    }

    return undefined;
}

function startInertiaApp() {
    const initialPage = getInitialPage();

    createInertiaApp({
        page: initialPage,
        title: (title) => (title ? `${title} - ${appName}` : appName),
        resolve: (name) =>
            resolvePageComponent(
                `./Pages/${name}.vue`,
                import.meta.glob<DefineComponent>('./Pages/**/*.vue')
            ),
        setup({ el, App, props, plugin }) {
            const target = el || document.getElementById('app');
            if (target) {
                createApp({ render: () => h(App, props) })
                    .use(plugin)
                    .mount(target);
            }
        },
        progress: {
            color: '#4F46E5',
        },
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', startInertiaApp);
} else {
    startInertiaApp();
}

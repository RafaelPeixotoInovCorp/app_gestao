import '../css/app.css';
import './bootstrap';

import { getInitialPageFromDOM } from '@inertiajs/core';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'Gestão';

const INERTIA_ROOT_ID = 'app';

function readCsrfTokenFromMeta() {
    if (typeof document === 'undefined') {
        return '';
    }
    return document.head?.querySelector('meta[name="csrf-token"]')?.getAttribute('content')?.trim() ?? '';
}

function readCsrfTokenFromInitialPage() {
    try {
        const initial = getInitialPageFromDOM(INERTIA_ROOT_ID);
        const t = initial?.props?.csrf_token;
        return typeof t === 'string' ? t.trim() : '';
    } catch {
        return '';
    }
}

function inertiaCsrfToken() {
    if (typeof window !== 'undefined') {
        const w = window.__INERTIA_CSRF__;
        if (typeof w === 'string' && w.trim() !== '') {
            return w.trim();
        }
    }
    return readCsrfTokenFromInitialPage() || readCsrfTokenFromMeta();
}

if (typeof window !== 'undefined') {
    window.__INERTIA_CSRF__ = inertiaCsrfToken();
}

function syncCsrfMetaFromPage(event) {
    const token = event.detail?.page?.props?.csrf_token;
    const meta = document.head.querySelector('meta[name="csrf-token"]');
    if (meta && typeof token === 'string' && token.length > 0) {
        meta.setAttribute('content', token);
        if (typeof window !== 'undefined') {
            window.__INERTIA_CSRF__ = token;
        }
    }
}

router.on('navigate', syncCsrfMetaFromPage);
router.on('success', syncCsrfMetaFromPage);

/**
 * Laravel usa `request->input('_token')` antes dos cabeçalhos X-CSRF / X-XSRF.
 * Garantir `_token` em todos os POST/PUT/PATCH/DELETE Inertia evita 419 quando o axios
 * não envia o cookie XSRF (ex.: alguns browsers / extensões) ou há mistura http/https.
 */
router.on('before', (event) => {
    const visit = event.detail.visit;
    const method = String(visit.method ?? 'get').toLowerCase();
    if (method === 'get' || method === 'head' || method === 'options') {
        return;
    }
    const token = inertiaCsrfToken();
    if (!token) {
        return;
    }
    const data = visit.data;
    if (typeof FormData !== 'undefined' && data instanceof FormData) {
        data.set('_token', token);
        return;
    }
    if (data && typeof data === 'object' && !Array.isArray(data)) {
        visit.data = { ...data, _token: token };
        return;
    }
    visit.data = { _token: token };
});

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

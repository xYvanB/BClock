import '../css/app.css';

import { setLocale } from '@/lib/i18n';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createElement } from 'react';
import { createRoot } from 'react-dom/client';
import { route as routeFn } from 'ziggy-js';

declare global {
    const route: typeof routeFn;
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
    setup({ el, App, props }) {
        const root = createRoot(el);

        // The language is set before the page renders, so every text of the page is translated.
        root.render(
            <App {...props}>
                {({ Component, key, props: pageProps }) => {
                    setLocale(String(pageProps.locale ?? 'it'));
                    document.documentElement.lang = String(pageProps.locale ?? 'it');

                    return createElement(Component, { key, ...pageProps });
                }}
            </App>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});

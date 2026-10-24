/* prettier-ignore */
import {
createInertiaApp
} from '@inertiajs/react';
import createServer from '@inertiajs/react/server';
import { createElement } from 'react';
import ReactDOMServer from 'react-dom/server';
import { setLocale } from './lib/i18n';

createServer((page) =>
    createInertiaApp({
        page,
        render: ReactDOMServer.renderToString,
        resolve: (name) => {
            const pages = import.meta.glob('./pages/**/*.tsx', {
                eager: true,
            });
            return pages[`./pages/${name}.tsx`];
        },
        // prettier-ignore
        setup: ({ App, props }) => (
            <App {...props}>
                {({ Component, key, props: pageProps }) => {
                    setLocale(String(pageProps.locale ?? 'it'));

                    return createElement(Component, { key, ...pageProps });
                }}
            </App>
        ),
    }),
);

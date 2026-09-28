import { createInertiaApp } from '@inertiajs/react';
import createServer from '@inertiajs/react/server';
import { renderToString } from 'react-dom/server';

/**
 * The public site, rendered on the server.
 *
 * Without this the delivered document is an empty div and a JSON blob: no heading, no copy, no links.
 * Google runs the JavaScript and gets there anyway, which is why this was easy to miss for so long —
 * but Bing, LinkedIn, Slack and every AI crawler read what arrives, and what arrived was nothing.
 *
 * `./Pages/*.jsx` rather than `./Pages/**​/*.jsx`, and the single asterisk is the whole point. SSR
 * needs an eager glob, and depth one happens to be exactly the two pages a visitor can reach —
 * `Login` sits under `Pages/Auth/` and the admin under `Pages/Cms/`. So the admin's bundle, its editor
 * and its socket client never enter this process. It is also the safe direction if the middleware that
 * turns SSR off for `/cms` ever stopped working: the page would fail to resolve here, the gateway
 * would answer null, and the browser would render it exactly as it does today.
 */
createServer((page) => createInertiaApp({
    page,
    render: renderToString,
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/*.jsx', { eager: true });

        return pages[`./Pages/${name}.jsx`];
    },
    setup: ({ App, props }) => <App {...props} />,
}));

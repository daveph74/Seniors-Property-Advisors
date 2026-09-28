import { createInertiaApp } from '@inertiajs/react';
import { createRoot, hydrateRoot } from 'react-dom/client';

createInertiaApp({
    /*
     * The default bar is grey and waits a quarter of a second before showing. Against the navy
     * top bar it was invisible, so a tap that had to wait for the network looked like a tap that
     * had done nothing. Brand blue, and sooner.
     */
    progress: { color: '#5894D5', delay: 120 },
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.jsx');

        return pages[`./Pages/${name}.jsx`]();
    },
    /*
     * Two roots, and the branch is load-bearing in both directions. The public site arrives
     * server-rendered, and `createRoot().render()` on those nodes throws that HTML away and
     * redraws from scratch — SSR would still "work" and buy nothing, which is the version of this
     * bug nobody notices. The admin and `/login` are never server-rendered (see
     * `HandleInertiaRequests::$withoutSsr`), and neither is any page served while the SSR process
     * is down, so those arrive as an empty div where `hydrateRoot` would warn.
     */
    setup({ el, App, props }) {
        if (el.hasChildNodes()) {
            hydrateRoot(el, <App {...props} />);

            return;
        }

        createRoot(el).render(<App {...props} />);
    },
});

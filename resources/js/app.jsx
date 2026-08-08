import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

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
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
});

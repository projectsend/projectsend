import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import axios from 'axios';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { route as routeFn } from 'ziggy-js';
import { initializeTheme } from './hooks/use-appearance';
import { xsrfCookieName } from './lib/xsrf';

declare global {
    const route: typeof routeFn;
}

// Inertia sends every write through axios, which reads the CSRF token
// from a cookie it expects to be called `XSRF-TOKEN`. This installation
// names that cookie after itself so a neighbouring Laravel app on the same
// hostname cannot overwrite it — so axios has to be told. Without this,
// every write 419s the moment a neighbour answers a request.
axios.defaults.xsrfCookieName = xsrfCookieName();

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Pages shipped by packages, re-keyed to look exactly like a host page
// (`./pages/<name>.tsx`) so resolvePageComponent finds them the same
// way — a one-time, generic extension point so a new package's pages
// just work without touching this file again.
//
// vendor/ is the only location globbed, and it covers both ways a
// package arrives: `composer require` puts a published package there,
// and a path repository (how a dev checkout consumes a local clone)
// symlinks the clone
// there too. Globbing packages/* as well, which this did at first,
// matched the dev checkouts' pages through both paths and emitted every
// package page into the bundle twice.
const packagePages: Record<string, () => Promise<unknown>> = {};
for (const [path, loader] of Object.entries(import.meta.glob('../../vendor/*/*/resources/js/pages/**/*.tsx'))) {
    const match = path.match(/resources\/js\/pages\/(.+)$/);
    if (match) packagePages[`./pages/${match[1]}`] = loader;
}

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, { ...packagePages, ...import.meta.glob('./pages/**/*.tsx') }),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();

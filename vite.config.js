import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import {
    defineConfig
} from 'vite';
import tailwindcss from "@tailwindcss/vite";

export default defineConfig(({ mode }) => ({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            ssr: 'resources/js/ssr.jsx',
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    esbuild: {
        jsx: 'automatic',

        // Keep other people's development warnings out of our users'
        // consoles. React strips its own from a production build; several
        // of our dependencies do not — Radix emits an accessibility
        // warning on every dialog it considers underdescribed, and it
        // reaches anyone who opens devtools on a real installation. A
        // warning aimed at whoever is building the software is noise to
        // whoever is using it, and noise is where real errors go to hide.
        //
        // console.error is deliberately NOT in this list. Something has
        // genuinely gone wrong when it fires, and a support conversation
        // that starts with a real stack trace is worth more than a tidy
        // console. This only strips the levels that are advisory.
        //
        // Development is untouched: `npm run dev` still shows everything,
        // which is where those warnings are actually useful.
        pure: mode === 'production'
            ? ['console.log', 'console.warn', 'console.info', 'console.debug', 'console.trace']
            : [],
    },
    resolve: {
        // Package pages (packages/*/resources/js) are loaded through a
        // symlink to a shared clone outside this repo. Without this, Vite/Rollup
        // resolve modules against the symlink's *real* path and then walk
        // up from there looking for node_modules — which fails, since the
        // real path's ancestry doesn't include this repo's node_modules.
        // preserveSymlinks keeps resolution relative to the symlinked
        // path instead, whose ancestry does.
        preserveSymlinks: true,
    },
}));
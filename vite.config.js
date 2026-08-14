import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import {
    defineConfig
} from 'vite';
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
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
});
import { defineConfig } from 'vite';

/**
 * Builds the bundle the package ships in `assets/`.
 *
 * Not called `dist/`: a directory with that name (or build/, out/, target/) is
 * treated as generated output by the workspace tooling this was developed in and
 * may be dropped, which would leave the package without its renderer.
 *
 * Deliberately not laravel-vite-plugin: the host application owns its own Vite
 * setup, and this build has to work with none of it — the output is two files
 * the package serves itself (see Atlas\Scope\Support\Assets). Everything three.js
 * pulls in becomes a chunk, loaded only by the atlas page.
 */
export default defineConfig({
    build: {
        outDir: 'assets',
        emptyOutDir: true,
        cssCodeSplit: false,
        chunkSizeWarningLimit: 1600,
        rollupOptions: {
            input: 'resources/js/app.js',
            output: {
                entryFileNames: 'atlas.js',
                chunkFileNames: 'chunks/[name]-[hash].js',
                assetFileNames: (asset) => (asset.names?.[0]?.endsWith('.css') ? 'atlas.css' : '[name][extname]'),
            },
        },
    },
});

import { defineConfig } from 'vite';

/**
 * Builds the bundle the package ships in `dist/`.
 *
 * Deliberately not laravel-vite-plugin: the host application owns its own Vite
 * setup, and this build has to work with none of it — the output is two files
 * the package serves itself (see Atlas\Scope\Support\Assets). Everything three.js
 * pulls in becomes a chunk, loaded only by the atlas page.
 */
export default defineConfig({
    build: {
        outDir: 'dist',
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

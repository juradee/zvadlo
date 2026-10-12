import { defineConfig } from 'vite';

// Builds frontend/app.ts into www/assets with a manifest read by App\Presentation\Accessory\ViteExtension.
export default defineConfig({
    base: '/assets/',
    build: {
        outDir: 'www/assets',
        emptyOutDir: true,
        manifest: true,
        rollupOptions: {
            // The service worker is a second, self-contained entry; App\Presentation\Pwa\PwaPresenter serves it at /sw.js.
            input: {
                app: 'frontend/app.ts',
                sw: 'frontend/sw.ts',
            },
        },
    },
});

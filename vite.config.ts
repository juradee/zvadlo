import { defineConfig } from 'vite';

// Builds frontend/app.ts into www/assets with a manifest read by App\Presentation\Accessory\ViteExtension.
export default defineConfig({
    base: '/assets/',
    build: {
        outDir: 'www/assets',
        emptyOutDir: true,
        manifest: true,
        rollupOptions: {
            input: 'frontend/app.ts',
        },
    },
});

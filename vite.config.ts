import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import { fileURLToPath, URL } from 'node:url';
import { createHash } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { VitePWA } from 'vite-plugin-pwa';

const publicPwaAssets = [
    'offline.html',
    'pwa/icon-192.png',
    'pwa/icon-512.png',
    'pwa/icon-maskable-512.png',
    'pwa/apple-touch-icon.png',
].map((path) => ({
    url: `/${path}`,
    revision: createHash('sha256')
        .update(readFileSync(new URL(`./public/${path}`, import.meta.url)))
        .digest('hex'),
}));

export default defineConfig({
    plugins: [
        laravel({ input: 'resources/js/app.tsx', refresh: true }),
        react(),
        tailwindcss(),
        VitePWA({
            strategies: 'injectManifest',
            srcDir: 'resources/pwa',
            filename: 'sw.ts',
            outDir: 'public/build',
            base: '/',
            buildBase: '/build/',
            scope: '/',
            injectRegister: false,
            registerType: 'prompt',
            // Laravel owns public/. Never copy it into public/build or precache its contents.
            includeAssets: [],
            includeManifestIcons: false,
            injectManifest: {
                globPatterns: ['assets/**/*.{js,css,woff,woff2}'],
                additionalManifestEntries: publicPwaAssets,
            },
            manifest: {
                id: '/',
                name: 'CLBK TOKE',
                short_name: 'CLBK',
                description: 'Ruang kerja pengelolaan usaha sawit CLBK TOKE.',
                lang: 'id',
                start_url: '/',
                scope: '/',
                display: 'standalone',
                background_color: '#f5f6f0',
                theme_color: '#164b37',
                icons: [
                    { src: '/pwa/icon-192.png', sizes: '192x192', type: 'image/png' },
                    { src: '/pwa/icon-512.png', sizes: '512x512', type: 'image/png' },
                    {
                        src: '/pwa/icon-maskable-512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'maskable',
                    },
                ],
            },
            devOptions: { enabled: false },
        }),
    ],
    resolve: { alias: { '@': fileURLToPath(new URL('./resources/js', import.meta.url)) } },
});

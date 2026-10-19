/// <reference lib="webworker" />

import { precacheAndRoute, matchPrecache } from 'workbox-precaching';
import { NavigationRoute, registerRoute } from 'workbox-routing';
import { NetworkOnly } from 'workbox-strategies';

declare const self: ServiceWorkerGlobalScope & {
    __WB_MANIFEST: Array<{ url: string; revision: string | null }>;
};

// Only the build's explicit public asset list and offline page enter Cache Storage.
precacheAndRoute(self.__WB_MANIFEST, { ignoreURLParametersMatching: [] });

const network = new NetworkOnly();
registerRoute(
    new NavigationRoute(
        async ({ event, request }) => {
            try {
                return await network.handle({ event, request });
            } catch {
                return (await matchPrecache('/offline.html')) ?? Response.error();
            }
        },
        {
            denylist: [
                /^\/(?:up|healthz)(?:[/?]|$)/,
                /^\/(?:sale-proofs|finance-proofs)(?:[/?]|$)/,
                /^\/reports\/(?:export|print)(?:[/?]|$)/,
                /^\/settings\/backups(?:[/?]|$)/,
            ],
        },
    ),
);

// Inertia/JSON, private downloads, health checks and writes pass straight through.
// Do not use an app-shell fallback, runtime data cache, or background sync here.
self.addEventListener('message', (event) => {
    if (event.data?.type === 'SKIP_WAITING') event.waitUntil(self.skipWaiting());
});

// Claim after activation so the accepting tab receives controllerchange and reloads.
// Each tab's UI still decides whether it is safe to reload its own document.
self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

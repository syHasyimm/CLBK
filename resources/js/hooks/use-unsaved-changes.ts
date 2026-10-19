import type { PendingVisit } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { usePwaReloadGuard } from '@/lib/pwa-reload-guard';

export default function useUnsavedChanges(dirty: boolean) {
    usePwaReloadGuard(dirty);
    const [pending, setPending] = useState<PendingVisit | null>(null);
    const confirmed = useRef<PendingVisit | null>(null);
    useEffect(() => {
        if (!dirty) return;
        const stop = router.on('before', (event) => {
            const visit = event.detail.visit;
            // Saving the form must proceed; logging out also discards its unsaved data.
            const logout =
                visit.method === 'post' && visit.url.pathname === new URL(route('logout')).pathname;
            if (visit.prefetch || (visit.method !== 'get' && !logout)) return;
            if (
                confirmed.current?.url.href === visit.url.href &&
                confirmed.current.method === visit.method
            ) {
                confirmed.current = null;
                return;
            }
            event.preventDefault();
            setPending(visit);
        });
        const beforeUnload = (event: BeforeUnloadEvent) => {
            event.preventDefault();
            event.returnValue = '';
        };
        window.addEventListener('beforeunload', beforeUnload);
        return () => {
            stop();
            window.removeEventListener('beforeunload', beforeUnload);
        };
    }, [dirty]);
    return {
        open: pending !== null,
        cancel: () => setPending(null),
        leave: () => {
            if (!pending) return;
            confirmed.current = pending;
            setPending(null);
            try {
                router.visit(pending.url, pending);
            } finally {
                // A cancelled visit must not bypass the next confirmation.
                confirmed.current = null;
            }
        },
    };
}

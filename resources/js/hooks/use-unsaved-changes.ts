import type { PendingVisit } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

export default function useUnsavedChanges(dirty: boolean) {
    const [pending, setPending] = useState<PendingVisit | null>(null);
    const confirmed = useRef(false);
    useEffect(() => {
        if (!dirty) return;
        const stop = router.on('before', (event) => {
            if (event.detail.visit.method !== 'get') return;
            if (confirmed.current) {
                confirmed.current = false;
                return;
            }
            event.preventDefault();
            setPending(event.detail.visit);
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
            confirmed.current = true;
            setPending(null);
            router.visit(pending.url, pending);
        },
    };
}

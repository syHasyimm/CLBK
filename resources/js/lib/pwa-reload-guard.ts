import { useEffect, useSyncExternalStore } from 'react';

const blockers = new Set<symbol>();
const listeners = new Set<() => void>();

function notify() {
    listeners.forEach((listener) => listener());
}

export function hasPwaReloadBlockers() {
    return blockers.size > 0;
}

export function usePwaReloadBlocked() {
    return useSyncExternalStore(
        (listener) => {
            listeners.add(listener);
            return () => listeners.delete(listener);
        },
        hasPwaReloadBlockers,
        () => false,
    );
}

export function usePwaReloadGuard(blocked: boolean) {
    useEffect(() => {
        if (!blocked) return;
        const blocker = Symbol();
        blockers.add(blocker);
        notify();
        return () => {
            blockers.delete(blocker);
            notify();
        };
    }, [blocked]);
}

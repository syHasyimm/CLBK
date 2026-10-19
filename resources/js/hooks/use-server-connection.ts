import { useEffect, useState } from 'react';

export function useServerConnection() {
    const [connected, setConnected] = useState(true);
    useEffect(() => {
        let active = true;
        let request: AbortController | undefined;
        const check = async () => {
            if (!navigator.onLine) {
                if (active) setConnected(false);
                return;
            }
            request?.abort();
            const controller = new AbortController();
            request = controller;
            const timer = window.setTimeout(() => controller.abort(), 5000);
            try {
                const response = await fetch('/up', {
                    cache: 'no-store',
                    signal: controller.signal,
                });
                if (active && request === controller) setConnected(response.ok);
            } catch {
                if (active && request === controller) setConnected(false);
            } finally {
                window.clearTimeout(timer);
            }
        };
        const offline = () => {
            request?.abort();
            setConnected(false);
        };
        void check();
        const interval = window.setInterval(() => void check(), 30000);
        window.addEventListener('online', check);
        window.addEventListener('offline', offline);
        window.addEventListener('clbk:connection-lost', offline);
        return () => {
            active = false;
            request?.abort();
            window.clearInterval(interval);
            window.removeEventListener('online', check);
            window.removeEventListener('offline', offline);
            window.removeEventListener('clbk:connection-lost', offline);
        };
    }, []);
    return connected;
}

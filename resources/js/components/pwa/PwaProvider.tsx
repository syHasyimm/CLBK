import { router } from '@inertiajs/react';
import { isAxiosError } from 'axios';
import {
    createContext,
    useContext,
    useEffect,
    useRef,
    useState,
    type PropsWithChildren,
} from 'react';
import { registerSW } from 'virtual:pwa-register';
import { hasPwaReloadBlockers, usePwaReloadBlocked } from '@/lib/pwa-reload-guard';

interface InstallPrompt extends Event {
    prompt(): Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

interface PwaState {
    canInstall: boolean;
    installing: boolean;
    install: () => Promise<void>;
    updateAvailable: boolean;
    updateBlocked: boolean;
    updating: boolean;
    updateError: string;
    update: () => void;
    dismissUpdate: () => void;
}

const PwaContext = createContext<PwaState | null>(null);

export function usePwa() {
    const context = useContext(PwaContext);
    if (!context) throw new Error('PwaProvider is required.');
    return context;
}

export default function PwaProvider({ children }: PropsWithChildren) {
    const [installPrompt, setInstallPrompt] = useState<InstallPrompt | null>(null);
    const [installed, setInstalled] = useState(false);
    const [installing, setInstalling] = useState(false);
    const [updateAvailable, setUpdateAvailable] = useState(false);
    const [updating, setUpdating] = useState(false);
    const [updateError, setUpdateError] = useState('');
    const [visiting, setVisiting] = useState(false);
    const blocked = usePwaReloadBlocked();
    const activeVisits = useRef(0);
    const requestedReload = useRef(false);
    const activateUpdate = useRef<(() => Promise<void>) | null>(null);

    useEffect(() => {
        const standalone = window.matchMedia('(display-mode: standalone)');
        const updateInstalled = () => setInstalled(standalone.matches);
        const beforeInstall = (event: Event) => {
            event.preventDefault();
            setInstallPrompt(event as InstallPrompt);
        };
        const appInstalled = () => {
            setInstalled(true);
            setInstallPrompt(null);
        };
        updateInstalled();
        standalone.addEventListener('change', updateInstalled);
        window.addEventListener('beforeinstallprompt', beforeInstall);
        window.addEventListener('appinstalled', appInstalled);
        return () => {
            standalone.removeEventListener('change', updateInstalled);
            window.removeEventListener('beforeinstallprompt', beforeInstall);
            window.removeEventListener('appinstalled', appInstalled);
        };
    }, []);

    useEffect(() => {
        const stopStart = router.on('start', () => {
            activeVisits.current += 1;
            setVisiting(true);
        });
        const stopFinish = router.on('finish', () => {
            activeVisits.current = Math.max(0, activeVisits.current - 1);
            setVisiting(activeVisits.current > 0);
        });
        const stopException = router.on('exception', (event) => {
            // Keep the current form in place on network failures. Never retry a write.
            if (isAxiosError(event.detail.exception) && !event.detail.exception.response) {
                event.preventDefault();
                window.dispatchEvent(new Event('clbk:connection-lost'));
            }
        });
        return () => {
            stopStart();
            stopFinish();
            stopException();
        };
    }, []);

    useEffect(() => {
        if (!import.meta.env.PROD || !('serviceWorker' in navigator)) return;
        let active = true;
        let registration: ServiceWorkerRegistration | undefined;
        const onNeedReload = () => {
            if (!active) return;
            if (requestedReload.current && !hasPwaReloadBlockers() && !activeVisits.current) {
                window.location.reload();
            } else {
                requestedReload.current = false;
                setUpdating(false);
                setUpdateAvailable(true);
            }
        };
        registerSW({
            onNeedRefresh: () => active && setUpdateAvailable(true),
            // Also handles a worker activated by another tab without reloading this form.
            onNeedReload,
            onRegisteredSW: (_url, value) => {
                registration = value;
            },
            onRegisterError: (error: unknown) => {
                console.warn('PWA belum dapat diaktifkan.', error);
            },
        });
        activateUpdate.current = async () => {
            if (registration?.waiting) registration.waiting.postMessage({ type: 'SKIP_WAITING' });
            else onNeedReload();
        };
        const checkUpdate = () => {
            if (registration?.waiting) setUpdateAvailable(true);
            if (
                navigator.onLine &&
                document.visibilityState === 'visible' &&
                !registration?.installing
            ) {
                void registration?.update().catch(() => undefined);
            }
        };
        const interval = window.setInterval(checkUpdate, 60 * 60 * 1000);
        window.addEventListener('online', checkUpdate);
        document.addEventListener('visibilitychange', checkUpdate);
        return () => {
            active = false;
            activateUpdate.current = null;
            window.clearInterval(interval);
            window.removeEventListener('online', checkUpdate);
            document.removeEventListener('visibilitychange', checkUpdate);
        };
    }, []);

    // Recover the controls if a worker fails to activate or connectivity disappears.
    useEffect(() => {
        if (!updating) return;
        const timeout = window.setTimeout(() => {
            requestedReload.current = false;
            setUpdating(false);
            setUpdateError('Pembaruan belum selesai. Periksa koneksi, lalu coba lagi.');
        }, 15000);
        return () => window.clearTimeout(timeout);
    }, [updating]);

    const install = async () => {
        if (!installPrompt || installing) return;
        setInstalling(true);
        try {
            await installPrompt.prompt();
            const choice = await installPrompt.userChoice;
            if (choice.outcome === 'accepted') setInstalled(true);
        } catch {
            // A dismissed/expired prompt must not interrupt the user's work.
        } finally {
            setInstallPrompt(null);
            setInstalling(false);
        }
    };

    const update = () => {
        if (hasPwaReloadBlockers() || activeVisits.current || updating) return;
        if (!navigator.onLine) {
            setUpdateError('Hubungkan ke internet sebelum memperbarui aplikasi.');
            return;
        }
        requestedReload.current = true;
        setUpdateError('');
        setUpdating(true);
        void activateUpdate.current?.().catch(() => {
            requestedReload.current = false;
            setUpdating(false);
            setUpdateError('Pembaruan belum berhasil. Periksa koneksi, lalu coba lagi.');
        });
    };

    return (
        <PwaContext.Provider
            value={{
                canInstall: !!installPrompt && !installed,
                installing,
                install,
                updateAvailable,
                updateBlocked: blocked || visiting,
                updating,
                updateError,
                update,
                dismissUpdate: () => setUpdateAvailable(false),
            }}
        >
            {children}
        </PwaContext.Provider>
    );
}

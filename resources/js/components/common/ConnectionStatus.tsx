import { useServerConnection } from '@/hooks/use-server-connection';
import { WifiOff } from 'lucide-react';

export default function ConnectionStatus() {
    const connected = useServerConnection();
    if (connected) return null;
    return (
        <div
            role="alert"
            className="flex items-center justify-center gap-3 border-b border-amber-200 bg-amber-50 px-5 py-3 text-sm text-amber-900"
        >
            <WifiOff className="size-4 shrink-0" />
            <span>Koneksi ke server terputus. Periksa jaringan Anda sebelum melanjutkan.</span>
        </div>
    );
}

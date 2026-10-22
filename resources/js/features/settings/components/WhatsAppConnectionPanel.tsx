import { type FormEvent, useState } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';
import { CheckCircle2, CircleHelp, KeyRound, LoaderCircle, Unplug } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError';

export type WhatsAppConnection = {
    has_token: boolean;
    status: 'unchecked' | 'connected' | 'disconnected' | 'invalid' | 'error';
    message: string | null;
    device_number: string | null;
    checked_at: string | null;
};
type ConnectionResponse = { connection: WhatsAppConnection; configured: boolean };
const statusLabels = {
    unchecked: 'Belum diuji',
    connected: 'Terhubung',
    disconnected: 'Perangkat terputus',
    invalid: 'Token tidak valid',
    error: 'Pemeriksaan gagal',
};

export default function WhatsAppConnectionPanel({
    connection: initial,
}: {
    connection: WhatsAppConnection;
}) {
    // Credentials deliberately stay out of Inertia form/history state.
    const [token, setToken] = useState('');
    const [connection, setConnection] = useState(initial);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');

    function apply(result: ConnectionResponse) {
        setConnection(result.connection);
    }
    async function check(save: boolean) {
        if (busy) return;
        setBusy(true);
        setError('');
        try {
            if (save) {
                const saved = await axios.put<ConnectionResponse>(
                    route('whatsapp.connection.update'),
                    { token },
                );
                setToken('');
                apply(saved.data);
            }
            const checked = await axios.post<ConnectionResponse>(
                route('whatsapp.connection.check'),
            );
            apply(checked.data);
        } catch (failure) {
            if (axios.isAxiosError(failure) && failure.response?.status === 422) {
                setError(
                    failure.response.data?.errors?.token?.[0] ??
                        'Periksa token yang Anda masukkan.',
                );
            } else if (axios.isAxiosError(failure) && failure.response?.status === 429) {
                setError('Pemeriksaan terlalu sering. Tunggu satu menit lalu coba kembali.');
            } else if (axios.isAxiosError(failure) && failure.response?.status === 409) {
                setError('Token telah berubah. Muat ulang halaman lalu uji kembali.');
            } else {
                setError(
                    'Pemeriksaan belum selesai. Uji kembali token tersimpan atau muat ulang halaman.',
                );
            }
        } finally {
            setBusy(false);
            router.reload({ only: ['configured', 'connection', 'settings'] });
        }
    }
    const connected = connection.status === 'connected';
    const StatusIcon = connected
        ? CheckCircle2
        : connection.status === 'unchecked'
          ? CircleHelp
          : Unplug;
    return (
        <section
            className="mb-6 rounded-xl border bg-card p-5 sm:p-6"
            aria-labelledby="whatsapp-connection-title"
        >
            <div className="flex items-start gap-3">
                <KeyRound className="mt-0.5 size-5 text-primary" aria-hidden="true" />
                <div>
                    <h2 id="whatsapp-connection-title" className="font-semibold">
                        Token & koneksi WhatsApp
                    </h2>
                    <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                        Masukkan token perangkat dari dashboard Fonnte, lalu periksa apakah WhatsApp
                        sudah terhubung.
                    </p>
                </div>
            </div>
            <form
                onSubmit={(event: FormEvent) => {
                    event.preventDefault();
                    void check(true);
                }}
                className="mt-6"
            >
                <Label htmlFor="fonnte-token">Token perangkat Fonnte</Label>
                <Input
                    id="fonnte-token"
                    name="token"
                    type="password"
                    autoComplete="new-password"
                    spellCheck={false}
                    value={token}
                    maxLength={2048}
                    onChange={(event) => setToken(event.target.value)}
                    disabled={busy}
                    aria-invalid={!!error}
                    aria-describedby="token-help token-error"
                    placeholder={
                        connection.has_token
                            ? 'Token tersimpan. Isi hanya jika ingin mengganti.'
                            : 'Masukkan token Fonnte'
                    }
                    className="mt-2"
                />
                <p id="token-help" className="mt-2 text-xs leading-relaxed text-muted-foreground">
                    Token disimpan terenkripsi dan tidak ditampilkan kembali. Mengganti token
                    menonaktifkan pengiriman sampai koneksi diuji dan pengiriman diaktifkan kembali.
                </p>
                <InputError id="token-error" message={error} className="mt-3" />
                <div className="mt-5 flex flex-wrap gap-3">
                    <Button disabled={busy || !token.trim()}>
                        {busy && <LoaderCircle className="size-4 animate-spin" />}
                        {busy ? 'Memeriksa koneksi...' : 'Simpan & Uji koneksi'}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={busy || !connection.has_token || !!token}
                        onClick={() => void check(false)}
                    >
                        Uji token tersimpan
                    </Button>
                </div>
            </form>
            <div
                role="status"
                aria-live="polite"
                className={`mt-6 flex items-start gap-3 rounded-lg border p-4 ${connected ? 'border-primary/20 bg-primary/5' : 'bg-muted/30'}`}
            >
                <StatusIcon
                    className={`mt-0.5 size-5 shrink-0 ${connected ? 'text-primary' : 'text-muted-foreground'}`}
                    aria-hidden="true"
                />
                <div>
                    <p className="text-sm font-semibold">{statusLabels[connection.status]}</p>
                    <p className="mt-1 text-sm leading-relaxed text-muted-foreground">
                        {connection.message ??
                            (connection.has_token
                                ? 'Token tersedia. Uji koneksi untuk melihat status perangkat.'
                                : 'Belum ada token tersimpan.')}
                    </p>
                    {connection.device_number && (
                        <p className="mt-2 text-xs text-muted-foreground">
                            Perangkat: {connection.device_number}
                        </p>
                    )}
                    {connection.checked_at && (
                        <p className="mt-2 text-xs text-muted-foreground">
                            Diperiksa:{' '}
                            {new Intl.DateTimeFormat('id-ID', {
                                dateStyle: 'medium',
                                timeStyle: 'short',
                                timeZone: 'Asia/Jakarta',
                            }).format(new Date(connection.checked_at))}{' '}
                            WIB
                        </p>
                    )}
                </div>
            </div>
            <p className="mt-3 text-xs text-muted-foreground">
                Uji koneksi memeriksa perangkat. Gunakan “Kirim pesan tes” untuk menguji pengiriman
                ke nomor Anda.
            </p>
        </section>
    );
}

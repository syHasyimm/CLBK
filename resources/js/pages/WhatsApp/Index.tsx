import { type FormEvent, useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import axios from 'axios';
import { ArrowLeft, MessageCircle, RefreshCw, Send } from 'lucide-react';
import WhatsAppConnectionPanel, {
    type WhatsAppConnection,
} from '@/features/settings/components/WhatsAppConnectionPanel';
import AppLayout from '@/layouts/AppLayout';
import PageHeading from '@/components/common/PageHeading';
import FormField from '@/components/common/FormField';
import UnsavedChangesGuard from '@/components/common/UnsavedChangesGuard';
import InputError from '@/components/InputError';
import { Button } from '@/components/ui/button';
import { NativeSelect } from '@/components/ui/native-select';
import type { PaginatedData } from '@/types/master-data';

type Settings = { enabled: boolean; weighing_enabled: boolean; payment_enabled: boolean };
type Message = {
    id: number;
    event: string;
    customer_name: string;
    source_number: string;
    target: string | null;
    body: string;
    status: string;
    reason: string | null;
    attempts: number;
    created_at: string;
    provider_id: string | null;
};
type Attempt = {
    number: number;
    status: string;
    reason: string | null;
    created_at: string;
    finished_at: string | null;
};
const statuses: Record<string, string> = {
    pending: 'Menunggu pengiriman',
    retrying: 'Menunggu percobaan ulang',
    processing: 'Sedang dikirim',
    accepted: 'Diterima Fonnte',
    sent: 'Terkirim',
    delivered: 'Diterima penerima',
    read: 'Dibaca',
    failed: 'Gagal',
    uncertain: 'Perlu diperiksa',
    skipped: 'Dilewati',
    cancelled: 'Dibatalkan',
    held: 'Karantina setelah restore',
};
const events: Record<string, string> = {
    weighing_posted: 'Hasil timbangan',
    weighing_voided: 'Pembatalan timbangan',
    payment_posted: 'Konfirmasi pembayaran',
    payment_reversed: 'Pembatalan pembayaran',
    test: 'Pesan tes',
};
const date = (value: string) =>
    new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'Asia/Jakarta',
    }).format(new Date(value));

function MessageDetails({ message }: { message: Message }) {
    const [attempts, setAttempts] = useState<Attempt[] | null>(null);
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    async function load() {
        if (attempts || loading) return;
        setLoading(true);
        setError('');
        try {
            const response = await axios.get<{ attempts: Attempt[] }>(
                route('whatsapp.show', message.id),
            );
            setAttempts(response.data.attempts);
        } catch {
            setError(
                'Riwayat percobaan belum dapat dimuat. Tutup dan buka kembali detail untuk mencoba lagi.',
            );
        } finally {
            setLoading(false);
        }
    }
    return (
        <details
            className="mt-2"
            onToggle={(event) => {
                if (event.currentTarget.open) void load();
            }}
        >
            <summary className="cursor-pointer text-xs font-medium text-primary focus-visible:outline-2 focus-visible:outline-ring">
                Lihat pesan dan riwayat
            </summary>
            <div className="mt-3 max-w-md space-y-3">
                <pre className="whitespace-pre-wrap break-words rounded-lg bg-muted/40 p-4 font-sans text-xs leading-relaxed">
                    {message.body}
                </pre>
                {message.provider_id && (
                    <p className="text-xs text-muted-foreground">
                        ID Fonnte: {message.provider_id}
                    </p>
                )}
                {loading && <p className="text-xs text-muted-foreground">Memuat riwayat...</p>}
                {error && (
                    <p role="alert" className="text-xs text-destructive">
                        {error}
                    </p>
                )}
                {attempts?.map((attempt) => (
                    <p key={attempt.number} className="text-xs text-muted-foreground">
                        Percobaan {attempt.number} · {date(attempt.created_at)} ·{' '}
                        {statuses[attempt.status] ?? attempt.status}
                        {attempt.reason && ` — ${attempt.reason}`}
                    </p>
                ))}
                {attempts?.length === 0 && (
                    <p className="text-xs text-muted-foreground">Belum ada percobaan pengiriman.</p>
                )}
            </div>
        </details>
    );
}

function SettingsPanel({
    settings,
    configured,
    webhookConfigured,
}: {
    settings: Settings;
    configured: boolean;
    webhookConfigured: boolean;
}) {
    const form = useForm(settings);
    const test = useForm({ target: '', idempotency_key: crypto.randomUUID() });
    function save(event: FormEvent) {
        event.preventDefault();
        form.patch(route('whatsapp.settings'), {
            preserveScroll: true,
            onSuccess: () => form.setDefaults(),
        });
    }
    function sendTest(event: FormEvent) {
        event.preventDefault();
        test.post(route('whatsapp.test'), {
            preserveScroll: true,
            onSuccess: () => test.setData('idempotency_key', crypto.randomUUID()),
        });
    }
    return (
        <section className="mb-8 grid gap-6 lg:grid-cols-2">
            <form
                onSubmit={save}
                className="rounded-xl border bg-card p-5 sm:p-6"
                aria-label="Pengaturan WhatsApp"
            >
                <UnsavedChangesGuard dirty={form.isDirty && !form.processing} />
                <h2 className="font-semibold">Pengaturan pengiriman</h2>
                <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                    Pesan dikirim ke konsumen yang mengaktifkan notifikasi pada data konsumen.
                </p>
                {!configured && (
                    <p role="status" className="mt-4 rounded-lg border p-3 text-sm">
                        Simpan token dan pastikan hasil uji koneksi menunjukkan “Terhubung” sebelum
                        pengiriman diaktifkan.
                    </p>
                )}
                <div className="mt-5 space-y-4">
                    {(
                        [
                            ['enabled', 'Aktifkan notifikasi WhatsApp'],
                            ['weighing_enabled', 'Hasil timbangan dan koreksinya'],
                            ['payment_enabled', 'Konfirmasi pembayaran dan pembalikannya'],
                        ] as const
                    ).map(([key, label]) => (
                        <div key={key}>
                            <label className="flex items-center gap-3 text-sm">
                                <input
                                    type="checkbox"
                                    checked={form.data[key]}
                                    onChange={(event) => form.setData(key, event.target.checked)}
                                    disabled={key === 'enabled' && !configured}
                                    className="size-4 rounded accent-primary"
                                />
                                {label}
                            </label>
                            <InputError message={form.errors[key]} className="mt-2" />
                        </div>
                    ))}
                </div>
                <p className="mt-5 text-xs leading-relaxed text-muted-foreground">
                    Hasil timbangan memuat nama, tiket, waktu, dan berat buah. Harga dan total
                    pembelian tidak dicantumkan.
                </p>
                <div className="mt-6 flex justify-end border-t pt-5">
                    <Button disabled={form.processing}>Simpan pengaturan</Button>
                </div>
            </form>
            <form
                onSubmit={sendTest}
                className="rounded-xl border bg-card p-5 sm:p-6"
                aria-label="Kirim pesan tes"
            >
                <h2 className="font-semibold">Kirim pesan tes</h2>
                <p className="mb-5 mt-2 text-sm leading-relaxed text-muted-foreground">
                    Kirim pesan tes ke nomor Anda, lalu periksa hasilnya pada riwayat di bawah.
                </p>
                <FormField
                    field={{
                        key: 'target',
                        label: 'Nomor WhatsApp tujuan',
                        type: 'tel',
                        required: true,
                        maxLength: 30,
                    }}
                    value={test.data.target}
                    error={test.errors.target}
                    onChange={(value) => test.setData('target', value)}
                />
                <p className="mt-3 text-xs text-muted-foreground">
                    Contoh: 081234567890 atau +6281234567890.
                </p>
                <p className="mt-6 text-xs leading-relaxed text-muted-foreground">
                    {webhookConfigured
                        ? 'Pembaruan status pengiriman siap dikonfigurasi di dashboard Fonnte.'
                        : 'Pembaruan status belum dikonfigurasi. Pesan yang diterima Fonnte belum berarti diterima konsumen.'}
                </p>
                <div className="mt-6 flex justify-end border-t pt-5">
                    <Button
                        disabled={test.processing || !configured || !settings.enabled}
                        variant="outline"
                    >
                        <Send className="size-4" />
                        Kirim pesan tes
                    </Button>
                </div>
            </form>
        </section>
    );
}

export default function Index({
    messages,
    settings,
    configured,
    webhookConfigured,
    canManage,
    filters,
    connection,
}: {
    messages: PaginatedData<Message>;
    settings: Settings;
    configured: boolean;
    webhookConfigured: boolean;
    canManage: boolean;
    filters: { status?: string };
    connection: WhatsAppConnection | null;
}) {
    const [retrying, setRetrying] = useState<number | null>(null);
    const retry = useForm({ operation: '' });
    function retryMessage(message: Message) {
        if (retry.processing) return;
        setRetrying(message.id);
        retry.post(route('whatsapp.retry', message.id), {
            preserveScroll: true,
            onFinish: () => setRetrying(null),
        });
    }
    return (
        <AppLayout
            header={
                <PageHeading
                    title="Notifikasi WhatsApp"
                    description="Pantau hasil timbangan dan konfirmasi pembayaran yang dikirim kepada konsumen."
                    action={
                        <Button variant="outline" onClick={() => router.reload()}>
                            <RefreshCw className="size-4" />
                            Perbarui status
                        </Button>
                    }
                />
            }
        >
            <Head title="Notifikasi WhatsApp" />
            {canManage && (
                <Link
                    href={route('settings.business.edit')}
                    className="mb-5 inline-flex items-center gap-2 text-sm font-medium text-primary hover:underline"
                >
                    <ArrowLeft className="size-4" />
                    Pengaturan Usaha
                </Link>
            )}
            {canManage && connection && <WhatsAppConnectionPanel connection={connection} />}
            {canManage && (
                <SettingsPanel
                    key={`${connection?.checked_at ?? 'unchecked'}:${configured}`}
                    settings={settings}
                    configured={configured}
                    webhookConfigured={webhookConfigured}
                />
            )}
            <section className="rounded-xl border bg-card">
                <div className="flex flex-wrap items-center justify-between gap-4 border-b p-5 sm:px-6">
                    <div>
                        <h2 className="font-semibold">Riwayat pengiriman</h2>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {messages.total} pesan · waktu dalam WIB
                        </p>
                    </div>
                    <div>
                        <label htmlFor="status-filter" className="sr-only">
                            Filter status pengiriman
                        </label>
                        <NativeSelect
                            id="status-filter"
                            value={filters.status ?? ''}
                            onChange={(event) =>
                                router.get(
                                    route('whatsapp.index'),
                                    event.target.value ? { status: event.target.value } : {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <option value="">Semua status</option>
                            {Object.entries(statuses).map(([key, label]) => (
                                <option key={key} value={key}>
                                    {label}
                                </option>
                            ))}
                        </NativeSelect>
                    </div>
                </div>
                <InputError message={retry.errors.operation} className="px-6 pt-4" />
                {messages.data.length === 0 ? (
                    <div className="px-6 py-16 text-center">
                        <MessageCircle className="mx-auto mb-4 size-8 text-muted-foreground" />
                        <h3 className="font-medium">
                            Belum ada pesan{filters.status ? ' dengan status ini' : ''}
                        </h3>
                        <p className="mx-auto mt-2 max-w-md text-sm text-muted-foreground">
                            Notifikasi akan muncul setelah transaksi baru dicatat dengan pengiriman
                            dan notifikasi konsumen aktif.
                        </p>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[760px] text-left text-sm">
                            <thead className="border-b bg-muted/30 text-xs text-muted-foreground">
                                <tr>
                                    <th className="px-6 py-3 font-medium">Waktu / transaksi</th>
                                    <th className="px-4 py-3 font-medium">Konsumen</th>
                                    <th className="px-4 py-3 font-medium">Pesan</th>
                                    <th className="px-6 py-3 font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {messages.data.map((message) => (
                                    <tr key={message.id} className="align-top">
                                        <td className="px-6 py-5">
                                            <p className="font-medium">{message.source_number}</p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {date(message.created_at)}
                                            </p>
                                        </td>
                                        <td className="px-4 py-5">
                                            <p className="font-medium">{message.customer_name}</p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {message.target ?? 'Nomor tidak tersedia'}
                                            </p>
                                        </td>
                                        <td className="px-4 py-5">
                                            <p>{events[message.event] ?? message.event}</p>
                                            <MessageDetails message={message} />
                                        </td>
                                        <td className="max-w-xs px-6 py-5">
                                            <span
                                                className={`inline-block rounded-md px-2 py-1 text-xs font-medium ${['sent', 'delivered', 'read'].includes(message.status) ? 'bg-primary/10 text-primary' : ['failed', 'uncertain'].includes(message.status) ? 'bg-destructive/10 text-destructive' : 'bg-muted text-muted-foreground'}`}
                                            >
                                                {statuses[message.status] ?? message.status}
                                            </span>
                                            {message.reason && (
                                                <p className="mt-2 text-xs leading-relaxed text-muted-foreground">
                                                    {message.reason}
                                                </p>
                                            )}
                                            {canManage &&
                                                message.status === 'failed' &&
                                                !message.provider_id && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        className="mt-3"
                                                        disabled={retry.processing}
                                                        onClick={() => retryMessage(message)}
                                                    >
                                                        {retrying === message.id
                                                            ? 'Memproses...'
                                                            : 'Coba ulang'}
                                                    </Button>
                                                )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                <div className="flex items-center justify-between gap-4 border-t p-5 sm:px-6">
                    <p className="text-xs text-muted-foreground">
                        Halaman {messages.current_page} dari {messages.last_page}
                    </p>
                    <div className="flex gap-2">
                        {messages.prev_page_url && (
                            <Button size="sm" variant="outline" asChild>
                                <Link href={messages.prev_page_url} preserveScroll>
                                    Sebelumnya
                                </Link>
                            </Button>
                        )}
                        {messages.next_page_url && (
                            <Button size="sm" variant="outline" asChild>
                                <Link href={messages.next_page_url} preserveScroll>
                                    Berikutnya
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>
            </section>
        </AppLayout>
    );
}

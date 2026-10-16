import { FormEvent, useState } from 'react';
import { router } from '@inertiajs/react';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Button } from '@/components/ui/button';
import { statusLabels } from '@/types/operations';

export default function OperationalFilters({
    url,
    filters,
    statuses,
    dates = false,
}: {
    url: string;
    filters: Record<string, string>;
    statuses: string[];
    dates?: boolean;
}) {
    const [data, setData] = useState({
        q: filters.q ?? '',
        status: filters.status ?? '',
        from: filters.from ?? '',
        to: filters.to ?? '',
    });
    const [busy, setBusy] = useState(false);
    function submit(e: FormEvent) {
        e.preventDefault();
        router.get(url, data, {
            preserveScroll: true,
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
        });
    }
    return (
        <form
            onSubmit={submit}
            role="search"
            aria-label="Filter transaksi"
            className="mb-5 grid items-end gap-3 sm:flex sm:flex-wrap"
        >
            <Input
                className="min-w-0 sm:min-w-48 sm:flex-1"
                aria-label="Cari transaksi"
                placeholder="Cari nomor atau nama..."
                maxLength={100}
                value={data.q}
                onChange={(e) => setData({ ...data, q: e.target.value })}
            />
            {statuses.length > 0 && (
                <NativeSelect
                    className="w-full sm:w-48"
                    aria-label="Status transaksi"
                    value={data.status}
                    onChange={(e) => setData({ ...data, status: e.target.value })}
                >
                    <option value="">Semua status</option>
                    {statuses.map((status) => (
                        <option key={status} value={status}>
                            {statusLabels[status]}
                        </option>
                    ))}
                </NativeSelect>
            )}
            {dates && (
                <>
                    <Input
                        className="w-full sm:w-40"
                        type="date"
                        aria-label="Tanggal mulai"
                        value={data.from}
                        onChange={(e) => setData({ ...data, from: e.target.value })}
                    />
                    <Input
                        className="w-full sm:w-40"
                        type="date"
                        aria-label="Tanggal akhir"
                        value={data.to}
                        onChange={(e) => setData({ ...data, to: e.target.value })}
                    />
                </>
            )}
            <Button variant="outline" disabled={busy}>
                Terapkan
            </Button>
        </form>
    );
}

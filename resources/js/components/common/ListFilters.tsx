import { useState, FormEvent } from 'react';
import { router } from '@inertiajs/react';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Button } from '@/components/ui/button';
import { Search, X } from 'lucide-react';
import type { ListFilters as Filters } from '@/types/master-data';

export default function ListFilters({ filters, url }: { filters: Filters; url: string }) {
    const [q, setQ] = useState(filters.q);
    const [status, setStatus] = useState(filters.status);
    const [processing, setProcessing] = useState(false);
    function apply(event: FormEvent) {
        event.preventDefault();
        router.get(
            url,
            { q, status },
            {
                preserveState: true,
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            },
        );
    }
    return (
        <form
            onSubmit={apply}
            className="mb-5 flex flex-wrap gap-3"
            role="search"
            aria-label="Filter daftar"
        >
            <div className="relative min-w-48 flex-1">
                <Search className="pointer-events-none absolute left-3 top-3 size-4 text-muted-foreground" />
                <Input
                    aria-label="Cari data"
                    placeholder="Cari data..."
                    maxLength={100}
                    value={q}
                    onChange={(e) => setQ(e.target.value)}
                    className="h-10 pl-10"
                />
            </div>
            <NativeSelect
                aria-label="Filter status"
                className="w-36"
                value={status}
                onChange={(e) => setStatus(e.target.value)}
            >
                <option value="all">Semua status</option>
                <option value="active">Aktif</option>
                <option value="inactive">Nonaktif</option>
            </NativeSelect>
            <Button variant="outline" disabled={processing}>
                Terapkan
            </Button>
            {(filters.q || filters.status !== 'all') && (
                <Button
                    type="button"
                    variant="ghost"
                    disabled={processing}
                    onClick={() => {
                        setQ('');
                        setStatus('all');
                        router.get(url, {}, { preserveState: true });
                    }}
                >
                    <X />
                    Reset
                </Button>
            )}
        </form>
    );
}

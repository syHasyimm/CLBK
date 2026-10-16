import { useEffect, useState, FormEvent } from 'react';
import { router } from '@inertiajs/react';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Button } from '@/components/ui/button';
import { Search, X } from 'lucide-react';
import type { ListFilters as Filters } from '@/types/master-data';
import type { SelectOption } from '@/components/common/FormField';

export default function ListFilters({
    filters,
    url,
    roles,
}: {
    filters: Filters & { role?: string };
    url: string;
    roles?: SelectOption[];
}) {
    const [q, setQ] = useState(filters.q);
    const [status, setStatus] = useState(filters.status);
    const [role, setRole] = useState(filters.role ?? '');
    const [processing, setProcessing] = useState(false);
    useEffect(() => {
        setQ(filters.q);
        setStatus(filters.status);
        setRole(filters.role ?? '');
    }, [filters.q, filters.status, filters.role]);
    function apply(event: FormEvent) {
        event.preventDefault();
        router.get(
            url,
            { q, status, ...(roles ? { role } : {}) },
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
            {roles && (
                <NativeSelect
                    aria-label="Filter role"
                    className="w-44"
                    value={role}
                    onChange={(e) => setRole(e.target.value)}
                >
                    <option value="">Semua role</option>
                    {roles.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </NativeSelect>
            )}
            <Button variant="outline" disabled={processing}>
                Terapkan
            </Button>
            {(filters.q || filters.status !== 'all' || filters.role) && (
                <Button
                    type="button"
                    variant="ghost"
                    disabled={processing}
                    onClick={() => {
                        setQ('');
                        setStatus('all');
                        setRole('');
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

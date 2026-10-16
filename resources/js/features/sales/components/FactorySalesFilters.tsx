import InputError from '@/components/InputError';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import useFilterVisit from '@/hooks/use-filter-visit';
import { usePage } from '@inertiajs/react';
import { LoaderCircle, Search, SlidersHorizontal, X } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';

export default function FactorySalesFilters({ filters }: { filters: Record<string, string> }) {
    const { errors, auth } = usePage().props;
    const [data, setData] = useState({
        q: filters.q ?? '',
        status: filters.status ?? '',
        from: filters.from ?? '',
        to: filters.to ?? '',
    });
    const { busy, visit } = useFilterVisit(() => route('factory-sales.index'), {
        preserveState: true,
    });
    useEffect(() => {
        setData({
            q: filters.q ?? '',
            status: filters.status ?? '',
            from: filters.from ?? '',
            to: filters.to ?? '',
        });
    }, [filters.q, filters.status, filters.from, filters.to]);
    function submit(event: FormEvent) {
        event.preventDefault();
        visit(data);
    }
    return (
        <form
            onSubmit={submit}
            role="search"
            aria-label="Filter penjualan pabrik"
            aria-busy={busy}
            className="border-b bg-muted/20 p-5 sm:p-6"
        >
            <div className="grid items-start gap-4 sm:grid-cols-2 xl:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto]">
                <div className="min-w-0">
                    <Label htmlFor="factory-search">Nomor penjualan / nota</Label>
                    <div className="relative mt-2">
                        <Search
                            className="pointer-events-none absolute left-3 top-3 size-4 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <Input
                            id="factory-search"
                            name="q"
                            className="h-10 pl-10"
                            placeholder="Cari nomor..."
                            maxLength={100}
                            value={data.q}
                            onChange={(e) => setData({ ...data, q: e.target.value })}
                            aria-invalid={!!errors.q}
                            aria-describedby={errors.q ? 'factory-q-error' : undefined}
                        />
                    </div>
                    <InputError id="factory-q-error" message={errors.q} className="mt-2" />
                </div>
                <div>
                    <Label htmlFor="factory-status">Status nota</Label>
                    <NativeSelect
                        id="factory-status"
                        name="status"
                        value={data.status}
                        className="mt-2 h-10"
                        onChange={(e) => setData({ ...data, status: e.target.value })}
                        aria-invalid={!!errors.status}
                        aria-describedby={errors.status ? 'factory-status-error' : undefined}
                    >
                        <option value="">Semua status</option>
                        <option value="posted">Sah</option>
                        <option value="reversed">Dibalik</option>
                        {auth.user.role !== 'cashier' && (
                            <option value="draft">Draft historis</option>
                        )}
                    </NativeSelect>
                    <InputError
                        id="factory-status-error"
                        message={errors.status}
                        className="mt-2"
                    />
                </div>
                {(
                    [
                        { key: 'from', label: 'Dari tanggal' },
                        { key: 'to', label: 'Sampai tanggal' },
                    ] as const
                ).map(({ key, label }) => (
                    <div key={key} className="min-w-0">
                        <Label htmlFor={'factory-' + key}>{label}</Label>
                        <Input
                            id={'factory-' + key}
                            name={key}
                            type="date"
                            className="mt-2 h-10"
                            value={data[key]}
                            onChange={(e) => setData({ ...data, [key]: e.target.value })}
                            aria-invalid={!!errors[key]}
                            aria-describedby={errors[key] ? 'factory-' + key + '-error' : undefined}
                        />
                        <InputError
                            id={'factory-' + key + '-error'}
                            message={errors[key]}
                            className="mt-2"
                        />
                    </div>
                ))}
                <div className="flex gap-2 sm:col-span-2 xl:col-span-1 xl:pt-7">
                    <Button
                        type="submit"
                        variant="secondary"
                        className="h-10 flex-1 xl:flex-none"
                        disabled={busy}
                    >
                        {busy ? (
                            <LoaderCircle
                                className="size-4 animate-spin motion-reduce:animate-none"
                                aria-hidden="true"
                            />
                        ) : (
                            <SlidersHorizontal className="size-4" aria-hidden="true" />
                        )}{' '}
                        Terapkan
                    </Button>
                    {Object.values(data).some(Boolean) && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-lg"
                            aria-label="Reset filter penjualan pabrik"
                            disabled={busy}
                            onClick={() => {
                                setData({ q: '', status: '', from: '', to: '' });
                                visit({});
                            }}
                        >
                            <X className="size-4" aria-hidden="true" />
                        </Button>
                    )}
                </div>
            </div>
        </form>
    );
}

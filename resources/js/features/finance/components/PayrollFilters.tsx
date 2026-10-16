import InputError from '@/components/InputError';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import useFilterVisit from '@/hooks/use-filter-visit';
import { usePage } from '@inertiajs/react';
import { LoaderCircle, Search, SlidersHorizontal, X } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';

export default function PayrollFilters({
    url,
    filters,
    periodOnly = false,
}: {
    url: string;
    filters: Record<string, string>;
    periodOnly?: boolean;
}) {
    const { errors } = usePage().props;
    const [data, setData] = useState({
        q: filters.q ?? '',
        status: filters.status ?? 'unpaid',
        from: filters.from ?? '',
        to: filters.to ?? '',
    });
    const { busy, visit: navigate } = useFilterVisit(url, { preserveState: !periodOnly });
    useEffect(() => {
        setData({
            q: filters.q ?? '',
            status: filters.status ?? 'unpaid',
            from: filters.from ?? '',
            to: filters.to ?? '',
        });
    }, [filters.q, filters.status, filters.from, filters.to]);
    function visit(values: typeof data) {
        navigate(periodOnly ? { from: values.from, to: values.to } : values);
    }
    function submit(event: FormEvent) {
        event.preventDefault();
        visit(data);
    }
    return (
        <form
            onSubmit={submit}
            aria-label={periodOnly ? 'Periode upah karyawan' : 'Filter gaji karyawan muat'}
            aria-busy={busy}
            className="border-b bg-muted/20 p-5 sm:p-6"
        >
            <div
                className={
                    periodOnly
                        ? 'grid items-start gap-4 sm:grid-cols-[1fr_1fr_auto]'
                        : 'grid items-start gap-4 sm:grid-cols-2 xl:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)_minmax(0,1fr)_auto]'
                }
            >
                {!periodOnly && (
                    <div className="min-w-0">
                        <Label htmlFor="payroll-search">Nama / kode karyawan</Label>
                        <div className="relative mt-2">
                            <Search
                                className="pointer-events-none absolute left-3 top-3 size-4 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <Input
                                id="payroll-search"
                                name="q"
                                className="h-10 pl-10"
                                placeholder="Cari karyawan..."
                                maxLength={100}
                                value={data.q}
                                onChange={(e) => setData({ ...data, q: e.target.value })}
                                aria-invalid={!!errors.q}
                                aria-describedby={errors.q ? 'payroll-q-error' : undefined}
                            />
                        </div>
                        <InputError id="payroll-q-error" message={errors.q} className="mt-2" />
                    </div>
                )}
                {(
                    [
                        { key: 'from', label: 'Pekerjaan mulai' },
                        { key: 'to', label: 'Pekerjaan sampai' },
                    ] as const
                ).map(({ key, label }) => (
                    <div key={key} className="min-w-0">
                        <Label htmlFor={'payroll-' + key}>{label}</Label>
                        <Input
                            id={'payroll-' + key}
                            name={key}
                            type="date"
                            className="mt-2 h-10"
                            required={periodOnly}
                            value={data[key]}
                            onChange={(e) => setData({ ...data, [key]: e.target.value })}
                            aria-invalid={!!errors[key]}
                            aria-describedby={errors[key] ? 'payroll-' + key + '-error' : undefined}
                        />
                        <InputError
                            id={'payroll-' + key + '-error'}
                            message={errors[key]}
                            className="mt-2"
                        />
                    </div>
                ))}
                <div className="flex gap-2 sm:pt-7">
                    <Button
                        type="submit"
                        variant="secondary"
                        className="h-10 flex-1"
                        disabled={busy}
                    >
                        {busy ? (
                            <LoaderCircle
                                className="size-4 animate-spin motion-reduce:animate-none"
                                aria-hidden="true"
                            />
                        ) : (
                            <SlidersHorizontal className="size-4" aria-hidden="true" />
                        )}
                        {periodOnly ? 'Terapkan' : 'Tampilkan'}
                    </Button>
                    {!periodOnly && (data.q || data.from || data.to) && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-lg"
                            aria-label="Reset filter gaji"
                            disabled={busy}
                            onClick={() => {
                                const next = { q: '', status: data.status, from: '', to: '' };
                                setData(next);
                                visit(next);
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

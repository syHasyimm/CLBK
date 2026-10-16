import InputError from '@/components/InputError';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import useFilterVisit from '@/hooks/use-filter-visit';
import { statusLabels } from '@/types/operations';
import { usePage } from '@inertiajs/react';
import { LoaderCircle, Search, SlidersHorizontal, X } from 'lucide-react';
import { FormEvent, useState } from 'react';

export default function WeighingFilters({
    filters,
    cashier,
}: {
    filters: Record<string, string>;
    cashier: boolean;
}) {
    const { errors } = usePage().props;
    const [data, setData] = useState({
        q: filters.q ?? '',
        status: filters.status ?? '',
        from: filters.from ?? '',
        to: filters.to ?? '',
    });
    const { busy, visit } = useFilterVisit(() => route('weighings.index'));
    const hasFilters = Object.values(data).some(Boolean);

    function submit(event: FormEvent) {
        event.preventDefault();
        visit(data);
    }

    return (
        <form
            onSubmit={submit}
            role="search"
            aria-label="Filter timbangan"
            aria-busy={busy}
            className="border-b px-5 py-5 sm:px-6"
        >
            <div className="grid items-start gap-4 sm:grid-cols-2 xl:grid-cols-[1.5fr_1fr_1fr_1fr_auto]">
                <div className="min-w-0">
                    <Label htmlFor="weighing-search">Cari timbangan</Label>
                    <div className="relative mt-2">
                        <Search
                            aria-hidden="true"
                            className="pointer-events-none absolute left-3 top-3 size-4 text-muted-foreground"
                        />
                        <Input
                            id="weighing-search"
                            name="q"
                            value={data.q}
                            maxLength={100}
                            placeholder="Nomor tiket atau konsumen..."
                            className="h-10 pl-10"
                            aria-invalid={!!errors.q}
                            aria-describedby={errors.q ? 'weighing-search-error' : undefined}
                            onChange={(e) => setData({ ...data, q: e.target.value })}
                        />
                    </div>
                    <InputError id="weighing-search-error" message={errors.q} className="mt-2" />
                </div>
                <div>
                    <Label htmlFor="weighing-status">Status tiket</Label>
                    <NativeSelect
                        id="weighing-status"
                        name="status"
                        className="mt-2 h-10"
                        value={data.status}
                        onChange={(e) => setData({ ...data, status: e.target.value })}
                        aria-invalid={!!errors.status}
                        aria-describedby={errors.status ? 'weighing-status-error' : undefined}
                    >
                        <option value="">Semua status</option>
                        {(cashier ? ['posted', 'voided'] : ['draft', 'posted', 'voided']).map(
                            (status) => (
                                <option key={status} value={status}>
                                    {statusLabels[status]}
                                </option>
                            ),
                        )}
                    </NativeSelect>
                    <InputError
                        id="weighing-status-error"
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
                        <Label htmlFor={'weighing-' + key}>{label}</Label>
                        <Input
                            id={'weighing-' + key}
                            name={key}
                            type="date"
                            className="mt-2 h-10"
                            value={data[key]}
                            aria-invalid={!!errors[key]}
                            aria-describedby={
                                errors[key] ? 'weighing-' + key + '-error' : undefined
                            }
                            onChange={(e) => setData({ ...data, [key]: e.target.value })}
                        />
                        <InputError
                            id={'weighing-' + key + '-error'}
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
                                aria-hidden="true"
                                className="size-4 animate-spin motion-reduce:animate-none"
                            />
                        ) : (
                            <SlidersHorizontal aria-hidden="true" className="size-4" />
                        )}
                        Terapkan
                    </Button>
                    {hasFilters && (
                        <Button
                            type="button"
                            size="icon-lg"
                            variant="ghost"
                            disabled={busy}
                            aria-label="Reset filter timbangan"
                            onClick={() => visit({})}
                        >
                            <X aria-hidden="true" className="size-4" />
                        </Button>
                    )}
                </div>
            </div>
        </form>
    );
}

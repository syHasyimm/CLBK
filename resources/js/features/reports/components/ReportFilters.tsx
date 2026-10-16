import { FormEvent, useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { RotateCcw, SlidersHorizontal } from 'lucide-react';
import InputError from '@/components/InputError';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { statusLabels } from '@/types/operations';
import { reportFilterLabels as labels, reportGroups } from '../lib/presentation';

export interface ReportFiltersProps {
    filters: {
        view: string;
        from: string;
        to: string;
        vehicle_id: number | null;
        [key: string]: string | number | null;
    };
    views: Record<string, string>;
    viewFilters: Record<string, string[]>;
    options: Record<string, { id: number; label: string }[]>;
}
export default function ReportFilters({
    filters,
    views,
    viewFilters,
    options,
}: ReportFiltersProps) {
    const { errors } = usePage().props;
    const initialData = () =>
        Object.fromEntries(
            Object.entries(filters).map(([key, value]) => [key, String(value ?? '')]),
        );
    const [data, setData] = useState<Record<string, string>>(initialData);
    const [busy, setBusy] = useState(false);
    useEffect(
        () =>
            setData(
                Object.fromEntries(
                    Object.entries(filters).map(([key, value]) => [key, String(value ?? '')]),
                ),
            ),
        [filters],
    );
    const statuses = ['debts', 'payroll', 'receivables'].includes(filters.view)
        ? ['unpaid', 'partial', 'paid']
        : filters.view === 'weighings'
          ? ['posted', 'voided']
          : ['posted', 'reversed'];
    function submit(event: FormEvent) {
        event.preventDefault();
        router.get(route('reports.index'), data, {
            preserveScroll: true,
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
        });
    }
    function reset() {
        router.get(
            route('reports.index'),
            {},
            {
                replace: true,
                preserveState: false,
                preserveScroll: true,
                fresh: true,
                onStart: () => {
                    setBusy(true);
                    setData({ view: 'summary', from: '', to: '', vehicle_id: '' });
                },
                onFinish: () => setBusy(false),
            },
        );
    }
    return (
        <section className="report-panel overflow-hidden print:hidden">
            <div className="flex items-center gap-3 border-b border-primary/10 px-5 py-4 sm:px-6">
                <span className="flex size-8 items-center justify-center rounded-lg bg-secondary text-primary">
                    <SlidersHorizontal className="size-4" aria-hidden="true" />
                </span>
                <div>
                    <h2 className="text-sm font-semibold">Atur laporan</h2>
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        Pilih jenis, periode, dan rincian yang ingin dilihat.
                    </p>
                </div>
            </div>
            <form
                onSubmit={submit}
                aria-label="Filter laporan"
                className="grid items-end gap-4 p-5 sm:grid-cols-2 sm:p-6 xl:grid-cols-4"
            >
                <div className="sm:col-span-2">
                    <Label htmlFor="report_view">Jenis laporan</Label>
                    <NativeSelect
                        id="report_view"
                        className="mt-2"
                        value={filters.view}
                        disabled={busy}
                        onChange={(event) => {
                            const view = event.target.value;
                            router.get(
                                route('reports.index'),
                                {
                                    view,
                                    from: data.from || filters.from,
                                    to: data.to || filters.to,
                                    ...((viewFilters[view] ?? []).includes('vehicle_id')
                                        ? { vehicle_id: data.vehicle_id ?? '' }
                                        : {}),
                                },
                                {
                                    preserveScroll: true,
                                    onStart: () => setBusy(true),
                                    onFinish: () => setBusy(false),
                                },
                            );
                        }}
                    >
                        {reportGroups
                            .filter((group) => group.views.some((view) => views[view]))
                            .map((group) => (
                                <optgroup key={group.label} label={group.label}>
                                    {group.views
                                        .filter((view) => views[view])
                                        .map((view) => (
                                            <option key={view} value={view}>
                                                {views[view]}
                                            </option>
                                        ))}
                                </optgroup>
                            ))}
                    </NativeSelect>
                </div>
                {['from', 'to'].map((key) => (
                    <div key={key}>
                        <Label htmlFor={'report_' + key}>
                            {key === 'from' ? 'Tanggal mulai' : 'Tanggal akhir'}
                        </Label>
                        <Input
                            className="mt-2"
                            type="date"
                            id={'report_' + key}
                            required
                            disabled={busy}
                            aria-invalid={!!errors[key]}
                            min={key === 'to' ? data.from : undefined}
                            value={data[key] ?? filters[key]}
                            onChange={(event) => setData({ ...data, [key]: event.target.value })}
                        />
                        <InputError message={errors[key]} />
                    </div>
                ))}
                {Object.entries(options)
                    .filter(([key]) => key !== 'cash_account_id')
                    .map(([key, choices]) => (
                        <div key={key}>
                            <Label htmlFor={'report_' + key}>{labels[key]}</Label>
                            <NativeSelect
                                id={'report_' + key}
                                className="mt-2"
                                value={data[key] ?? ''}
                                disabled={busy}
                                aria-invalid={!!errors[key]}
                                onChange={(event) =>
                                    setData({ ...data, [key]: event.target.value })
                                }
                            >
                                <option value="">Semua {labels[key].toLowerCase()}</option>
                                {choices.map((choice) => (
                                    <option key={choice.id} value={choice.id}>
                                        {choice.label}
                                    </option>
                                ))}
                            </NativeSelect>
                            <InputError message={errors[key]} />
                        </div>
                    ))}
                {(viewFilters[filters.view] ?? []).includes('status') && (
                    <div>
                        <Label htmlFor="report_status">Status</Label>
                        <NativeSelect
                            id="report_status"
                            className="mt-2"
                            value={data.status ?? ''}
                            disabled={busy}
                            onChange={(event) => setData({ ...data, status: event.target.value })}
                        >
                            <option value="">Semua status</option>
                            {statuses.map((status) => (
                                <option key={status} value={status}>
                                    {statusLabels[status]}
                                </option>
                            ))}
                        </NativeSelect>
                        <InputError message={errors.status} />
                    </div>
                )}
                <div className="flex flex-wrap items-center gap-3 border-t border-primary/10 pt-4 sm:col-span-2 xl:col-span-4">
                    <Button disabled={busy} className="min-h-11 rounded-xl px-5">
                        {busy ? 'Memuat laporan...' : 'Terapkan filter'}
                    </Button>
                    <button
                        type="button"
                        className="report-link min-h-11 disabled:opacity-50"
                        onClick={reset}
                        disabled={busy}
                    >
                        <RotateCcw className="size-3.5" aria-hidden="true" />
                        Reset rincian
                    </button>
                    <p className="text-[11px] text-muted-foreground sm:ml-auto">
                        Ekspor dan cetak mengikuti filter yang sudah diterapkan.
                    </p>
                </div>
            </form>
        </section>
    );
}

import { type FormEvent, useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { CalendarDays, LoaderCircle, SlidersHorizontal } from 'lucide-react';
import InputError from '@/components/InputError';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { cn } from '@/lib/utils';
import type { PeriodProps } from '@/features/reports/components/PeriodOverview';

export function dashboardDate(value: string, long = false) {
    return new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: long ? 'long' : 'short',
        ...(long ? { year: 'numeric' as const } : {}),
        timeZone: 'UTC',
    }).format(new Date(value + 'T00:00:00Z'));
}

export default function DashboardFilters({
    filters,
    vehicles,
    today,
    idPrefix = 'dashboard',
}: Pick<PeriodProps, 'filters' | 'vehicles'> & { today: string; idPrefix?: string }) {
    const [data, setData] = useState({
        from: filters.from,
        to: filters.to,
        vehicle_id: String(filters.vehicle_id ?? ''),
    });
    const [busy, setBusy] = useState(false);
    const { errors } = usePage().props;
    useEffect(
        () =>
            setData({
                from: filters.from,
                to: filters.to,
                vehicle_id: String(filters.vehicle_id ?? ''),
            }),
        [filters],
    );
    const weekStart = new Date(today + 'T00:00:00Z');
    weekStart.setUTCDate(weekStart.getUTCDate() - 6);
    const presets = [
        { label: 'Hari ini', from: today },
        { label: '7 hari', from: weekStart.toISOString().slice(0, 10) },
        { label: 'Bulan ini', from: today.slice(0, 7) + '-01' },
    ];
    function visit(values: typeof data) {
        router.get(route('dashboard'), values, {
            preserveScroll: true,
            preserveState: true,
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
        });
    }
    function submit(event: FormEvent) {
        event.preventDefault();
        visit(data);
    }
    return (
        <section className="dashboard-panel" aria-label="Periode Dashboard" aria-busy={busy}>
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border/60 px-5 py-3.5 sm:px-6">
                <p className="flex items-center gap-2 text-sm font-medium">
                    <CalendarDays className="size-4 text-primary" aria-hidden="true" />
                    Periode pemantauan
                </p>
                <div
                    role="group"
                    aria-label="Pilihan cepat periode"
                    className="flex items-center gap-1 rounded-lg bg-muted/80 p-1"
                >
                    {presets.map((preset) => (
                        <button
                            key={preset.label}
                            type="button"
                            disabled={busy}
                            aria-pressed={filters.from === preset.from && filters.to === today}
                            onClick={() => {
                                const next = { ...data, from: preset.from, to: today };
                                setData(next);
                                visit(next);
                            }}
                            className={cn(
                                'rounded-md px-3 py-1.5 text-xs font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:opacity-50',
                                filters.from === preset.from && filters.to === today
                                    ? 'bg-white text-primary shadow-xs'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {preset.label}
                        </button>
                    ))}
                </div>
            </div>
            <form
                onSubmit={submit}
                className="grid items-start gap-4 p-5 sm:grid-cols-2 sm:p-6 xl:grid-cols-[1fr_1fr_1.25fr_auto]"
                aria-label="Filter Dashboard"
            >
                {(['from', 'to'] as const).map((key) => (
                    <div key={key} className="min-w-0">
                        <Label
                            className="text-xs text-muted-foreground"
                            htmlFor={idPrefix + '_' + key}
                        >
                            {key === 'from' ? 'Tanggal mulai' : 'Tanggal akhir'}
                        </Label>
                        <Input
                            id={idPrefix + '_' + key}
                            className="mt-2 h-10 min-w-0 rounded-lg bg-background/50"
                            type="date"
                            required
                            min={key === 'to' ? data.from : undefined}
                            value={data[key]}
                            disabled={busy}
                            aria-invalid={!!errors[key]}
                            aria-describedby={
                                errors[key] ? idPrefix + '_' + key + '_error' : undefined
                            }
                            onChange={(event) => setData({ ...data, [key]: event.target.value })}
                        />
                        <InputError
                            id={idPrefix + '_' + key + '_error'}
                            message={errors[key]}
                            className="mt-1.5"
                        />
                    </div>
                ))}
                <div className="min-w-0">
                    <Label
                        className="text-xs text-muted-foreground"
                        htmlFor={idPrefix + '_vehicle'}
                    >
                        Mobil
                    </Label>
                    <NativeSelect
                        id={idPrefix + '_vehicle'}
                        className="mt-2 rounded-lg bg-background/50"
                        value={data.vehicle_id}
                        disabled={busy}
                        onChange={(event) => setData({ ...data, vehicle_id: event.target.value })}
                    >
                        <option value="">Semua mobil</option>
                        {vehicles.map((vehicle) => (
                            <option key={vehicle.id} value={vehicle.id}>
                                {vehicle.plate_number}
                            </option>
                        ))}
                    </NativeSelect>
                    <InputError message={errors.vehicle_id} className="mt-1.5" />
                </div>
                <Button className="h-10 rounded-lg sm:mt-6" disabled={busy}>
                    {busy ? (
                        <LoaderCircle className="motion-safe:animate-spin" />
                    ) : (
                        <SlidersHorizontal />
                    )}{' '}
                    {busy ? 'Memuat...' : 'Terapkan'}
                </Button>
            </form>
        </section>
    );
}

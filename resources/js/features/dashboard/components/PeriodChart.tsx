import { useState } from 'react';
import { Link } from '@inertiajs/react';
import { ArrowUpRight, BarChart3, ChevronLeft, ChevronRight } from 'lucide-react';
import { rupiah } from '@/lib/currency';
import { dashboardDate } from './DashboardFilters';
import type { PeriodProps } from '@/features/reports/components/PeriodOverview';

type Bucket = { from: string; to: string; purchase: number; revenue: number };

export default function PeriodChart({
    summary,
    filters,
    canViewReports = true,
}: Pick<PeriodProps, 'summary' | 'filters'> & { canViewReports?: boolean }) {
    // Keep every source day, including reversals; longer periods are summed into at most six ranges.
    const buckets: Bucket[] = [];
    const size = Math.max(1, Math.ceil(summary.days.length / 6));
    summary.days.forEach((day, index) => {
        if (index % size === 0)
            buckets.push({ from: day.date, to: day.date, purchase: 0, revenue: 0 });
        const bucket = buckets[buckets.length - 1];
        bucket.to = day.date;
        bucket.purchase += day.purchase_idr;
        bucket.revenue += day.revenue_idr;
    });
    const [selected, setSelected] = useState(Math.max(0, buckets.length - 1));
    const activeIndex = Math.min(selected, buckets.length - 1);
    const active = buckets[activeIndex];
    const values = buckets.flatMap((bucket) => [bucket.purchase, bucket.revenue]);
    const high = Math.max(0, ...values);
    const low = Math.min(0, ...values);
    const range = high - low || 1;
    const y = (value: number) => 12 + ((high - value) / range) * 164;
    const baseline = y(0);
    const step = 640 / Math.max(1, buckets.length);
    const bar = Math.min(64, step / 4);
    const compact = (value: number) =>
        new Intl.NumberFormat('id-ID', { notation: 'compact', maximumFractionDigits: 1 }).format(
            value,
        );
    const label = (bucket: Bucket) =>
        dashboardDate(bucket.from) +
        (bucket.from === bucket.to ? '' : ' – ' + dashboardDate(bucket.to));
    return (
        <section
            className="dashboard-panel flex min-w-0 flex-col"
            aria-labelledby="dashboard-trend-title"
        >
            <div className="flex flex-wrap items-start justify-between gap-3 p-5 sm:p-6">
                <div>
                    <h2 id="dashboard-trend-title" className="font-semibold tracking-tight">
                        Pergerakan usaha
                    </h2>
                    <p className="mt-1.5 text-xs text-muted-foreground">
                        Pembelian dan pendapatan pada periode yang dipilih.
                    </p>
                </div>
                {canViewReports && (
                    <Link className="dashboard-text-link" href={route('reports.index', filters)}>
                        Rincian <ArrowUpRight className="size-3.5" aria-hidden="true" />
                    </Link>
                )}
            </div>
            {active ? (
                <>
                    <div
                        className="mx-5 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-background px-4 py-3 sm:mx-6"
                        aria-live="polite"
                    >
                        <div>
                            <p className="text-xs text-muted-foreground">{label(active)}</p>
                            <div className="mt-2 flex flex-wrap gap-x-5 gap-y-2 text-sm">
                                <p>
                                    <span
                                        className="mr-2 inline-block size-2 rounded-sm bg-primary"
                                        aria-hidden="true"
                                    />
                                    <span className="text-muted-foreground">Pendapatan </span>
                                    <strong className="font-semibold tabular-nums">
                                        {rupiah(active.revenue)}
                                    </strong>
                                </p>
                                <p>
                                    <span
                                        className="mr-2 inline-block size-2 rounded-sm bg-[#92ae9e]"
                                        aria-hidden="true"
                                    />
                                    <span className="text-muted-foreground">Pembelian </span>
                                    <strong className="font-semibold tabular-nums">
                                        {rupiah(active.purchase)}
                                    </strong>
                                </p>
                            </div>
                        </div>
                        <div className="flex shrink-0 gap-1">
                            <button
                                type="button"
                                className="dashboard-chart-control"
                                disabled={activeIndex === 0}
                                aria-label="Rentang sebelumnya"
                                onClick={() => setSelected(activeIndex - 1)}
                            >
                                <ChevronLeft className="size-4" />
                            </button>
                            <button
                                type="button"
                                className="dashboard-chart-control"
                                disabled={activeIndex === buckets.length - 1}
                                aria-label="Rentang berikutnya"
                                onClick={() => setSelected(activeIndex + 1)}
                            >
                                <ChevronRight className="size-4" />
                            </button>
                        </div>
                    </div>
                    <div className="mt-5 flex gap-2 px-5 sm:px-6">
                        <div
                            className="flex h-48 w-12 shrink-0 flex-col justify-between py-3 text-right text-[11px] tabular-nums text-muted-foreground"
                            aria-hidden="true"
                        >
                            <span>{compact(high)}</span>
                            <span>{compact((high + low) / 2)}</span>
                            <span>{compact(low)}</span>
                        </div>
                        <div className="min-w-0 flex-1">
                            <svg
                                className="h-48 w-full overflow-visible"
                                viewBox="0 0 640 188"
                                preserveAspectRatio="none"
                                role="group"
                                aria-label="Grafik pembelian dan pendapatan dalam rupiah"
                            >
                                {[12, 94, 176].map((line) => (
                                    <line
                                        key={line}
                                        x1="0"
                                        x2="640"
                                        y1={line}
                                        y2={line}
                                        stroke="#e7ece6"
                                        strokeDasharray="4 5"
                                        vectorEffect="non-scaling-stroke"
                                    />
                                ))}
                                <line
                                    x1="0"
                                    x2="640"
                                    y1={baseline}
                                    y2={baseline}
                                    stroke="#c4cfc4"
                                    vectorEffect="non-scaling-stroke"
                                />
                                {buckets.map((bucket, index) => (
                                    <g
                                        key={bucket.from}
                                        role="button"
                                        tabIndex={0}
                                        aria-pressed={activeIndex === index}
                                        aria-label={
                                            label(bucket) +
                                            ', pendapatan ' +
                                            rupiah(bucket.revenue) +
                                            ', pembelian ' +
                                            rupiah(bucket.purchase)
                                        }
                                        className="cursor-pointer focus:outline-none focus-visible:[&_rect:first-child]:stroke-primary"
                                        onMouseEnter={() => setSelected(index)}
                                        onFocus={() => setSelected(index)}
                                        onClick={() => setSelected(index)}
                                        onKeyDown={(event) => {
                                            if (event.key === 'Enter' || event.key === ' ') {
                                                event.preventDefault();
                                                setSelected(index);
                                            }
                                        }}
                                    >
                                        <rect
                                            x={index * step + 2}
                                            y="0"
                                            width={step - 4}
                                            height="188"
                                            rx="8"
                                            fill={activeIndex === index ? '#eef3ed' : 'transparent'}
                                        />
                                        <rect
                                            x={index * step + step / 2 - bar - 3}
                                            y={Math.min(y(bucket.revenue), baseline)}
                                            width={bar}
                                            height={Math.abs(y(bucket.revenue) - baseline)}
                                            rx="3"
                                            fill="#164b37"
                                        />
                                        <rect
                                            x={index * step + step / 2 + 3}
                                            y={Math.min(y(bucket.purchase), baseline)}
                                            width={bar}
                                            height={Math.abs(y(bucket.purchase) - baseline)}
                                            rx="3"
                                            fill="#92ae9e"
                                        />
                                        <title>{`${label(bucket)}: pendapatan ${rupiah(bucket.revenue)}, pembelian ${rupiah(bucket.purchase)}`}</title>
                                    </g>
                                ))}
                            </svg>
                            <div
                                className="mt-2 flex text-[10px] text-muted-foreground"
                                aria-hidden="true"
                            >
                                {buckets.map((bucket) => (
                                    <span key={bucket.from} className="min-w-0 flex-1 text-center">
                                        {dashboardDate(bucket.from)}
                                    </span>
                                ))}
                            </div>
                        </div>
                    </div>
                    <p className="mt-auto px-5 pt-5 pb-5 text-[11px] leading-relaxed text-muted-foreground sm:px-6">
                        {summary.days.length > 6
                            ? `${summary.days.length} hari transaksi digabung dalam ${buckets.length} rentang. `
                            : 'Hari tanpa transaksi tidak ditampilkan. '}
                        Nilai negatif menunjukkan pembalikan.
                    </p>
                </>
            ) : (
                <div className="flex min-h-64 flex-1 flex-col items-center justify-center px-6 pb-6 text-center">
                    <div className="mb-4 flex size-12 items-center justify-center rounded-xl bg-secondary">
                        <BarChart3 className="size-6 text-primary" aria-hidden="true" />
                    </div>
                    <h3 className="text-sm font-semibold">Belum ada aktivitas pada periode ini</h3>
                    <p className="mt-2 max-w-xs text-xs leading-relaxed text-muted-foreground">
                        Pilih periode lain untuk melihat pembelian dan penjualan yang sudah
                        tercatat.
                    </p>
                </div>
            )}
        </section>
    );
}

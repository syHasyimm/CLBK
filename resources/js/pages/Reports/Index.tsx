import { Head, Link } from '@inertiajs/react';
import {
    ArrowUpRight,
    CalendarDays,
    ChartNoAxesCombined,
    Download,
    FileText,
    Printer,
} from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import DataTable from '@/components/common/DataTable';
import { Button } from '@/components/ui/button';
import type { PeriodProps } from '@/features/reports/components/PeriodOverview';
import ReportSummary from '@/features/reports/components/ReportSummary';
import ReportFilters, {
    type ReportFiltersProps,
} from '@/features/reports/components/ReportFilters';
import CashFlowTable from '@/features/reports/components/CashFlowTable';
import {
    formatReportValue,
    reportDate,
    reportFilterLabels,
} from '@/features/reports/lib/presentation';
import { statusLabels } from '@/types/operations';
import type { ReportData } from '@/types/reports';

export default function Reports(
    props: Omit<PeriodProps, 'summary' | 'filters'> &
        ReportFiltersProps & { summary: PeriodProps['summary'] | null; report: ReportData | null },
) {
    const { report, filters, summary, options } = props;
    const activeFilters = Object.entries(options).flatMap(([key, choices]) => {
        const selected = choices.find((choice) => String(choice.id) === String(filters[key]));
        return selected ? [`${reportFilterLabels[key]}: ${selected.label}`] : [];
    });
    if (filters.status)
        activeFilters.push(`Status: ${statusLabels[String(filters.status)] ?? filters.status}`);
    const title = props.views[filters.view];
    return (
        <AppLayout>
            <Head title={'Laporan · ' + title} />
            <div className="reports-workspace space-y-6 sm:space-y-8">
                <div className="flex flex-col justify-between gap-5 sm:flex-row sm:items-end print:hidden">
                    <div className="min-w-0">
                        <p className="mb-3 flex items-center gap-2 text-xs font-medium text-primary">
                            <ChartNoAxesCombined className="size-4" aria-hidden="true" />
                            Angka usaha, lebih jelas
                        </p>
                        <h1 className="report-heading text-3xl font-semibold sm:text-4xl">
                            Laporan
                        </h1>
                        <p className="mt-3 max-w-xl text-sm leading-relaxed text-muted-foreground">
                            Pantau hasil usaha, telusuri transaksi, dan siapkan laporan untuk
                            dibagikan.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline" className="min-h-11 rounded-xl">
                            <a href={route('reports.export', filters)}>
                                <Download className="size-4" aria-hidden="true" />
                                Ekspor CSV
                            </a>
                        </Button>
                        <Button asChild className="min-h-11 rounded-xl">
                            <a
                                href={route('reports.print', filters)}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <Printer className="size-4" aria-hidden="true" />
                                Cetak / PDF
                            </a>
                        </Button>
                    </div>
                </div>
                <ReportFilters {...props} />
                <section
                    className="flex flex-col justify-between gap-3 border-b border-primary/10 pb-5 sm:flex-row sm:items-start"
                    aria-label="Cakupan laporan"
                >
                    <div className="min-w-0">
                        <p className="text-[11px] font-medium text-muted-foreground">
                            Laporan yang ditampilkan
                        </p>
                        <h2 className="report-heading mt-1.5 text-xl font-semibold sm:text-2xl">
                            {title}
                        </h2>
                        {!!activeFilters.length && (
                            <ul className="mt-3 flex flex-wrap gap-2" aria-label="Filter aktif">
                                {activeFilters.map((label) => (
                                    <li
                                        key={label}
                                        className="max-w-full break-words rounded-lg bg-secondary/70 px-2.5 py-1.5 text-[11px] text-primary"
                                    >
                                        {label}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                    <p className="flex shrink-0 items-center gap-2 text-xs text-muted-foreground">
                        <CalendarDays className="size-4 text-primary" aria-hidden="true" />
                        {reportDate(filters.from)} – {reportDate(filters.to)}
                    </p>
                </section>
                {summary && <ReportSummary summary={summary} filters={filters} />}
                {report && (
                    <>
                        {Object.keys(report.totals).length > 0 && (
                            <dl className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                {report.columns
                                    .filter((column) => column.sum)
                                    .map((column) => (
                                        <div key={column.key} className="report-panel min-w-0 p-5">
                                            <dt className="text-xs font-medium text-muted-foreground">
                                                {column.label}
                                            </dt>
                                            <dd className="mt-3 break-words text-xl font-semibold tabular-nums">
                                                {formatReportValue(
                                                    report.totals[column.key],
                                                    column,
                                                )}
                                            </dd>
                                        </div>
                                    ))}
                            </dl>
                        )}
                        {report.cashflow && (
                            <CashFlowTable
                                flow={report.cashflow}
                                from={filters.from}
                                to={filters.to}
                            />
                        )}
                        <section aria-labelledby="report-transactions-title">
                            <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h2
                                        id="report-transactions-title"
                                        className="flex items-center gap-2 text-base font-semibold"
                                    >
                                        <FileText
                                            className="size-4 text-primary"
                                            aria-hidden="true"
                                        />
                                        Rincian transaksi
                                    </h2>
                                    <p className="mt-1.5 text-xs text-muted-foreground">
                                        {report.rows.total} baris · Total mencakup seluruh hasil
                                        filter.
                                    </p>
                                </div>
                                <span className="rounded-lg bg-secondary/60 px-3 py-2 text-[11px] font-medium text-primary">
                                    Halaman {report.rows.current_page} / {report.rows.last_page}
                                </span>
                            </div>
                            <DataTable
                                mobileCards
                                records={report.rows}
                                empty="Belum ada transaksi untuk filter ini."
                                columns={[
                                    ...report.columns.map((column) => ({
                                        label: column.label,
                                        render: (row: (typeof report.rows.data)[number]) => (
                                            <span
                                                className={
                                                    column.type === 'text'
                                                        ? 'block max-w-64 break-words'
                                                        : 'whitespace-nowrap tabular-nums'
                                                }
                                            >
                                                {formatReportValue(row[column.key], column)}
                                            </span>
                                        ),
                                    })),
                                    {
                                        label: 'Dokumen sumber / bukti',
                                        render: (row) =>
                                            row.source ? (
                                                <Link
                                                    className="report-link"
                                                    href={route(
                                                        row.source.route,
                                                        typeof row.source.params === 'number'
                                                            ? [row.source.params]
                                                            : row.source.params,
                                                    )}
                                                >
                                                    Lihat rincian
                                                    <ArrowUpRight
                                                        className="size-3.5 shrink-0"
                                                        aria-hidden="true"
                                                    />
                                                </Link>
                                            ) : (
                                                '—'
                                            ),
                                    },
                                ]}
                            />
                        </section>
                        <p className="border-l-2 border-primary/25 pl-4 text-xs leading-relaxed text-muted-foreground">
                            {report.note}
                        </p>
                        <p className="text-[11px] text-muted-foreground">
                            CSV dan cetak memuat seluruh halaman dalam filter ini.
                        </p>
                    </>
                )}
            </div>
        </AppLayout>
    );
}

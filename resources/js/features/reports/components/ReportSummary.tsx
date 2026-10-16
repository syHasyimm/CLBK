import { Link, usePage } from '@inertiajs/react';
import { ArrowUpRight, Scale, Truck, Wallet } from 'lucide-react';
import DataTable from '@/components/common/DataTable';
import { rupiah } from '@/lib/currency';
import { weight } from '@/lib/weight';
import type { PeriodProps } from './PeriodOverview';
import CashFlowTable from './CashFlowTable';
import { reportDate } from '../lib/presentation';

type Totals = PeriodProps['summary']['totals'];
const pagination = <T,>(data: T[]) => ({
    data,
    total: data.length,
    current_page: 1,
    last_page: 1,
    prev_page_url: null,
    next_page_url: null,
    from: data.length ? 1 : null,
    to: data.length || null,
});

export default function ReportSummary({
    summary,
    filters,
}: Pick<PeriodProps, 'summary' | 'filters'>) {
    const manager = usePage().props.auth.user.role === 'manager';
    const t = summary.totals;
    const profit = manager && !filters.vehicle_id && t.period_difference_idr !== undefined;
    const moneyMetrics = [
        { label: 'Pendapatan penjualan', value: t.revenue_idr, views: ['factory', 'sort'] },
        { label: 'Pembelian buah', value: t.purchase_idr, views: ['weighings'] },
        { label: 'Hak upah muat', value: t.loading_idr, views: ['payroll'] },
        ...(!filters.vehicle_id
            ? [{ label: 'Pengeluaran operasional', value: t.expense_idr ?? 0, views: ['expenses'] }]
            : []),
    ];
    const reportLink = (view: string) =>
        route('reports.index', {
            view,
            from: filters.from,
            to: filters.to,
            ...(view !== 'expenses' ? { vehicle_id: filters.vehicle_id } : {}),
        });
    const columns = [
        {
            label: 'Timbang neto',
            render: (row: Totals) => <span className="tabular-nums">{row.weighing_count}</span>,
        },
        ...(
            [
                ['TBS beli', 'tbs_bought_grams'],
                ['Sortir beli', 'sort_bought_grams'],
                ['TBS jual', 'factory_sold_grams'],
                ['Sortir jual', 'sort_sold_grams'],
            ] as const
        ).map(([label, key]) => ({
            label,
            render: (row: Totals) => (
                <span className="whitespace-nowrap tabular-nums">{weight(row[key])}</span>
            ),
        })),
        ...(
            [
                ['Pembelian', 'purchase_idr'],
                ['Pendapatan', 'revenue_idr'],
                ['Hak upah', 'loading_idr'],
            ] as const
        ).map(([label, key]) => ({
            label,
            render: (row: Totals) => (
                <span className="whitespace-nowrap tabular-nums">{rupiah(row[key])}</span>
            ),
        })),
    ];
    return (
        <div className="space-y-6">
            {profit && (
                <section className="report-profit rounded-2xl bg-primary px-6 py-7 text-primary-foreground sm:px-8 sm:py-8">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div className="min-w-0">
                            <p className="text-xs font-medium text-white/75">
                                Hasil usaha pada periode ini
                            </p>
                            <h2 className="mt-2 text-base font-medium">
                                Keuntungan bersih periode
                            </h2>
                            <p className="report-heading mt-4 break-words text-3xl font-semibold tabular-nums sm:text-4xl">
                                {rupiah(t.period_difference_idr!)}
                            </p>
                        </div>
                        <span className="rounded-lg border border-white/20 px-3 py-2 text-xs">
                            Seluruh mobil
                        </span>
                    </div>
                    <p className="mt-5 max-w-3xl text-xs leading-relaxed text-white/75 sm:text-sm">
                        Pendapatan − pembelian buah − hak upah muat − pengeluaran operasional.
                        Pembayaran konsumen dan gaji tidak dikurangi lagi. Pembelian dan penjualan
                        dapat terjadi pada tanggal yang berbeda.
                    </p>
                </section>
            )}
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                {moneyMetrics.map((metric) => (
                    <section key={metric.label} className="report-panel flex min-w-0 flex-col p-5">
                        <h2 className="text-xs font-medium text-muted-foreground">
                            {metric.label}
                        </h2>
                        <p className="mt-3 break-words text-xl font-semibold tabular-nums sm:text-2xl">
                            {rupiah(metric.value)}
                        </p>
                        <div className="mt-auto flex flex-wrap gap-3 pt-5">
                            {metric.views.map((view) => (
                                <Link key={view} href={reportLink(view)} className="report-link">
                                    {view === 'factory'
                                        ? 'Nota pabrik'
                                        : view === 'sort'
                                          ? 'Nota sortir'
                                          : 'Lihat sumber'}
                                    <ArrowUpRight className="size-3.5" aria-hidden="true" />
                                </Link>
                            ))}
                        </div>
                    </section>
                ))}
            </div>
            <section className="report-panel px-5 py-5 sm:px-6">
                <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <h2 className="flex items-center gap-2 text-sm font-semibold">
                        <Scale className="size-4 text-primary" aria-hidden="true" /> Volume buah
                    </h2>
                    <span className="text-xs text-muted-foreground">
                        {t.weighing_count} transaksi timbang neto
                    </span>
                </div>
                <dl className="grid grid-cols-2 gap-x-5 gap-y-6 lg:grid-cols-4">
                    {(
                        [
                            ['TBS dari konsumen', t.tbs_bought_grams],
                            ['Sortir dari konsumen', t.sort_bought_grams],
                            ['TBS terjual ke pabrik', t.factory_sold_grams],
                            ['Sortir terjual', t.sort_sold_grams],
                        ] as const
                    ).map(([label, value]) => (
                        <div key={label} className="min-w-0">
                            <dt className="text-xs text-muted-foreground">{label}</dt>
                            <dd className="mt-2 break-words text-lg font-semibold tabular-nums">
                                {weight(value)}
                            </dd>
                        </div>
                    ))}
                </dl>
            </section>
            {filters.vehicle_id && (
                <p className="rounded-xl border border-primary/10 bg-secondary/40 px-5 py-4 text-sm leading-relaxed text-muted-foreground">
                    Pengeluaran harian merupakan biaya usaha tanpa pembagian otomatis ke mobil.
                    Keuntungan bersih periode tersedia bagi manajer ketika memilih semua mobil.
                </p>
            )}
            {summary.balances && (
                <section className="report-panel p-5 sm:p-6">
                    <h2 className="flex items-center gap-2 text-base font-semibold">
                        <Wallet className="size-4 text-primary" aria-hidden="true" /> Saldo saat ini
                    </h2>
                    <p className="mt-2 max-w-3xl text-xs leading-relaxed text-muted-foreground">
                        Mengikuti seluruh tanggal transaksi. Kas & bank mencakup seluruh usaha,
                        termasuk ketika mobil difilter.
                    </p>
                    <dl className="mt-6 grid grid-cols-2 gap-x-5 gap-y-6 xl:grid-cols-4">
                        {(
                            [
                                ['Utang konsumen', 'customer_debt_idr', 'debts'],
                                ['Utang upah', 'payroll_debt_idr', 'payroll'],
                                ['Piutang penjualan', 'sale_receivable_idr', 'receivables'],
                                ['Kas & bank', 'cash_idr', 'cash'],
                            ] as const
                        ).map(([label, key, view]) => (
                            <div key={key} className="min-w-0">
                                <dt className="text-xs text-muted-foreground">{label}</dt>
                                <dd className="mt-2 break-words text-lg font-semibold tabular-nums">
                                    {rupiah(summary.balances![key])}
                                </dd>
                                <Link
                                    className="report-link mt-3"
                                    href={route('reports.index', {
                                        view,
                                        from: '0001-01-01',
                                        to: summary.as_of_date,
                                        ...(view !== 'cash'
                                            ? { vehicle_id: filters.vehicle_id }
                                            : {}),
                                    })}
                                >
                                    Lihat rincian saldo
                                    <ArrowUpRight className="size-3.5" aria-hidden="true" />
                                </Link>
                            </div>
                        ))}
                    </dl>
                </section>
            )}
            {summary.cashflow && (
                <CashFlowTable flow={summary.cashflow} from={filters.from} to={filters.to} />
            )}
            <section aria-labelledby="report-days-title">
                <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h2 id="report-days-title" className="text-base font-semibold">
                        Rincian per hari
                    </h2>
                    <span className="text-xs text-muted-foreground">
                        {summary.days.length} hari dengan transaksi
                    </span>
                </div>
                <DataTable
                    mobileCards
                    showPagination={false}
                    records={pagination(summary.days.map((day) => ({ ...day, id: day.date })))}
                    empty="Belum ada transaksi pada periode ini."
                    columns={[
                        {
                            label: 'Tanggal',
                            render: (row) => (
                                <Link
                                    className="report-link"
                                    href={route('reports.index', {
                                        view: 'summary',
                                        from: row.date,
                                        to: row.date,
                                        vehicle_id: filters.vehicle_id,
                                    })}
                                >
                                    {reportDate(row.date)}
                                </Link>
                            ),
                        },
                        ...columns,
                        ...(!filters.vehicle_id
                            ? [
                                  {
                                      label: 'Pengeluaran',
                                      render: (row: Totals) => (
                                          <span className="whitespace-nowrap tabular-nums">
                                              {rupiah(row.expense_idr ?? 0)}
                                          </span>
                                      ),
                                  },
                              ]
                            : []),
                        ...(profit
                            ? [
                                  {
                                      label: 'Keuntungan bersih',
                                      render: (row: Totals) => (
                                          <span className="whitespace-nowrap font-semibold tabular-nums text-primary">
                                              {rupiah(row.period_difference_idr ?? 0)}
                                          </span>
                                      ),
                                  },
                              ]
                            : []),
                    ]}
                />
            </section>
            <section aria-labelledby="report-vehicles-title">
                <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h2
                        id="report-vehicles-title"
                        className="flex items-center gap-2 text-base font-semibold"
                    >
                        <Truck className="size-4 text-primary" aria-hidden="true" />
                        Rincian per mobil
                    </h2>
                    <span className="text-xs text-muted-foreground">
                        {summary.vehicles.length} mobil
                    </span>
                </div>
                <DataTable
                    mobileCards
                    showPagination={false}
                    records={pagination(summary.vehicles)}
                    empty="Belum ada transaksi mobil pada periode ini."
                    columns={[
                        {
                            label: 'Mobil',
                            render: (row) => (
                                <Link
                                    className="report-link"
                                    href={route('reports.index', {
                                        view: 'summary',
                                        from: filters.from,
                                        to: filters.to,
                                        vehicle_id: row.id,
                                    })}
                                >
                                    {row.plate_number}
                                    <ArrowUpRight className="size-3.5" aria-hidden="true" />
                                </Link>
                            ),
                        },
                        ...columns,
                    ]}
                />
            </section>
        </div>
    );
}

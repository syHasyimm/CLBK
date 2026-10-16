import { Link, usePage } from '@inertiajs/react';
import {
    ArrowDownLeft,
    ArrowUpRight,
    Banknote,
    CircleHelp,
    Coins,
    Scale,
    Wallet,
} from 'lucide-react';
import { rupiah } from '@/lib/currency';
import { weight } from '@/lib/weight';
import { cn } from '@/lib/utils';
import type { PeriodProps } from '@/features/reports/components/PeriodOverview';
import DashboardFilters, { dashboardDate } from './DashboardFilters';
import PeriodChart from './PeriodChart';
import DashboardCashFlow from './DashboardCashFlow';
import VehicleSummary from './VehicleSummary';
import NetProfitCard from './NetProfitCard';

export default function DashboardOverview({ filters, vehicles, summary }: PeriodProps) {
    const { auth } = usePage().props;
    const manager = auth.user.role === 'manager';
    const driver = auth.user.role === 'driver';
    const totals = summary.totals;
    const plate = vehicles.find(
        (vehicle) => String(vehicle.id) === String(filters.vehicle_id),
    )?.plate_number;
    const source = (view: string, current = false) =>
        driver
            ? route(
                  view === 'factory'
                      ? 'factory-sales.index'
                      : view === 'sort'
                        ? 'sort-sales.index'
                        : 'weighings.index',
                  { from: filters.from, to: filters.to },
              )
            : route('reports.index', {
                  view,
                  from: current ? '0001-01-01' : filters.from,
                  to: current ? summary.as_of_date : filters.to,
                  ...(!['cash', 'expenses'].includes(view)
                      ? { vehicle_id: filters.vehicle_id }
                      : {}),
              });
    return (
        <div className="dashboard space-y-6">
            <DashboardFilters filters={filters} vehicles={vehicles} today={summary.as_of_date} />
            <div className="flex flex-wrap items-center justify-between gap-3 text-xs text-muted-foreground">
                <p>
                    <span className="font-medium text-foreground">
                        {filters.from === filters.to
                            ? dashboardDate(filters.from, true)
                            : dashboardDate(filters.from) + ' – ' + dashboardDate(filters.to, true)}
                    </span>
                    <span className="mx-2 text-border">/</span>
                    {plate ?? 'Semua mobil'}
                    {driver && ' · Transaksi yang dapat Anda akses'}
                </p>
                <Link href={source('weighings')} className="dashboard-text-link">
                    <Scale className="size-3.5" aria-hidden="true" />
                    <span className="tabular-nums">{totals.weighing_count}</span> transaksi timbang
                    neto <ArrowUpRight className="size-3.5" aria-hidden="true" />
                </Link>
            </div>

            {manager && totals.period_difference_idr !== undefined && (
                <NetProfitCard
                    amount={totals.period_difference_idr}
                    subtitle="Periode yang dipilih · Seluruh mobil"
                    href={route('reports.index', filters)}
                    terms={[
                        { label: 'Penjualan pabrik + sortir', amount: totals.revenue_idr },
                        { label: 'Pembelian buah', amount: totals.purchase_idr, deduction: true },
                        { label: 'Hak upah muat', amount: totals.loading_idr, deduction: true },
                        {
                            label: 'Pengeluaran operasional',
                            amount: totals.expense_idr ?? 0,
                            deduction: true,
                        },
                    ]}
                    explanation="Pendapatan penjualan dikurangi pembelian buah, hak upah, dan pengeluaran pada periode yang sama. Pembayaran konsumen dan gaji tidak dikurangkan lagi. Pembelian dan penjualan dapat terjadi pada tanggal yang berbeda."
                />
            )}
            {manager && filters.vehicle_id && (
                <p className="rounded-xl border border-primary/10 bg-secondary/50 px-5 py-4 text-xs leading-relaxed text-primary">
                    Pilih Semua mobil untuk melihat keuntungan bersih periode, termasuk pengeluaran
                    operasional seluruh usaha.
                </p>
            )}

            <section
                aria-label="Angka utama periode"
                className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
            >
                <article className="relative flex min-w-0 flex-col overflow-hidden rounded-2xl border border-primary/10 bg-secondary p-5 sm:col-span-2 sm:p-6">
                    <div className="flex items-center justify-between gap-3">
                        <h2 className="text-sm font-medium text-primary">Pendapatan penjualan</h2>
                        <ArrowUpRight className="size-5 text-primary/60" aria-hidden="true" />
                    </div>
                    <p className="dashboard-heading mt-4 text-[clamp(1.75rem,3.3vw,2.6rem)] leading-tight font-semibold tabular-nums text-primary">
                        {rupiah(totals.revenue_idr)}
                    </p>
                    <p className="mt-2 text-xs text-primary/75">
                        Dari nota penjualan pabrik dan sortir.
                    </p>
                    <div className="mt-6 grid grid-cols-2 gap-4 border-t border-primary/10 pt-4">
                        {[
                            {
                                label: 'TBS ke pabrik',
                                amount: totals.factory_sold_grams,
                                view: 'factory',
                            },
                            {
                                label: 'Sortir terjual',
                                amount: totals.sort_sold_grams,
                                view: 'sort',
                            },
                        ].map((item) => (
                            <Link
                                key={item.view}
                                href={source(item.view)}
                                className="group rounded-md focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring"
                            >
                                <p className="text-[11px] text-primary/75">{item.label}</p>
                                <p className="mt-1.5 flex items-center gap-2 text-sm font-semibold tabular-nums text-primary">
                                    {weight(item.amount)}
                                    <ArrowUpRight
                                        className="size-3.5 opacity-50 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5 motion-reduce:transform-none"
                                        aria-hidden="true"
                                    />
                                </p>
                            </Link>
                        ))}
                    </div>
                </article>
                <article className="dashboard-panel flex min-w-0 flex-col p-5 sm:p-6">
                    <div className="flex items-center justify-between">
                        <h2 className="text-sm text-muted-foreground">Pembelian buah</h2>
                        <ArrowDownLeft className="size-4 text-primary/60" aria-hidden="true" />
                    </div>
                    <p className="dashboard-heading mt-4 text-[clamp(1.25rem,2.2vw,1.75rem)] font-semibold tabular-nums">
                        {rupiah(totals.purchase_idr)}
                    </p>
                    <dl className="mt-4 space-y-2 text-xs">
                        <div className="flex flex-wrap justify-between gap-1">
                            <dt className="text-muted-foreground">TBS</dt>
                            <dd className="font-medium tabular-nums">
                                {weight(totals.tbs_bought_grams)}
                            </dd>
                        </div>
                        <div className="flex flex-wrap justify-between gap-1">
                            <dt className="text-muted-foreground">Sortir</dt>
                            <dd className="font-medium tabular-nums">
                                {weight(totals.sort_bought_grams)}
                            </dd>
                        </div>
                    </dl>
                    <Link href={source('weighings')} className="dashboard-text-link mt-auto pt-5">
                        Rincian timbang <ArrowUpRight className="size-3.5" aria-hidden="true" />
                    </Link>
                </article>
                <article className="dashboard-panel flex min-w-0 flex-col p-5 sm:p-6">
                    <div className="flex items-center justify-between">
                        <h2 className="text-sm text-muted-foreground">Hak upah muat</h2>
                        <Coins className="size-4 text-primary/60" aria-hidden="true" />
                    </div>
                    <p className="dashboard-heading mt-4 text-[clamp(1.25rem,2.2vw,1.75rem)] font-semibold tabular-nums">
                        {rupiah(totals.loading_idr)}
                    </p>
                    <p className="mt-4 text-xs leading-relaxed text-muted-foreground">
                        Hak anggota dari timbangan pada periode ini, sesuai tarif saat transaksi.
                    </p>
                    <Link href={source('weighings')} className="dashboard-text-link mt-auto pt-5">
                        Sumber hak upah <ArrowUpRight className="size-3.5" aria-hidden="true" />
                    </Link>
                </article>
            </section>

            {summary.balances && (
                <section
                    className="dashboard-panel overflow-hidden"
                    aria-labelledby="dashboard-balances-title"
                >
                    <div className="flex flex-wrap items-center justify-between gap-2 px-5 pt-5 sm:px-6">
                        <h2 id="dashboard-balances-title" className="text-sm font-semibold">
                            Posisi keuangan saat ini
                        </h2>
                        <p className="text-[11px] text-muted-foreground">
                            Seluruh tanggal · {dashboardDate(summary.as_of_date)}
                        </p>
                    </div>
                    <dl className="grid divide-y sm:grid-cols-2 sm:divide-y-0 xl:grid-cols-4">
                        {[
                            {
                                label: 'Kas & bank',
                                amount: summary.balances.cash_idr,
                                view: 'cash',
                                icon: Wallet,
                            },
                            {
                                label: 'Piutang penjualan',
                                amount: summary.balances.sale_receivable_idr,
                                view: 'receivables',
                                icon: Banknote,
                            },
                            {
                                label: 'Utang konsumen',
                                amount: summary.balances.customer_debt_idr,
                                view: 'debts',
                                icon: Scale,
                            },
                            {
                                label: 'Utang upah',
                                amount: summary.balances.payroll_debt_idr,
                                view: 'payroll',
                                icon: Coins,
                            },
                        ].map(({ label, amount, view, icon: Icon }) => (
                            <div key={view} className="p-5 sm:p-6">
                                <Link
                                    href={source(view, true)}
                                    className="group block rounded-md focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring"
                                >
                                    <dt className="flex items-center gap-2 text-xs text-muted-foreground">
                                        <Icon className="size-3.5" aria-hidden="true" />
                                        {label}
                                    </dt>
                                    <dd
                                        className={cn(
                                            'dashboard-heading mt-2.5 flex items-center justify-between gap-2 text-xl font-semibold tabular-nums',
                                            view === 'cash' && 'text-primary',
                                        )}
                                    >
                                        {rupiah(amount)}
                                        <ArrowUpRight
                                            className="size-4 shrink-0 text-muted-foreground/50 transition-colors group-hover:text-primary"
                                            aria-hidden="true"
                                        />
                                    </dd>
                                </Link>
                            </div>
                        ))}
                    </dl>
                    {filters.vehicle_id && (
                        <p className="border-t px-5 py-3 text-[11px] text-muted-foreground sm:px-6">
                            Utang dan piutang mengikuti mobil yang dipilih. Kas & bank tetap
                            mencakup seluruh usaha.
                        </p>
                    )}
                </section>
            )}

            <div className="grid items-stretch gap-5 xl:grid-cols-[minmax(0,1.8fr)_minmax(0,1fr)]">
                <PeriodChart
                    key={`${filters.from}:${filters.to}:${filters.vehicle_id}`}
                    filters={filters}
                    summary={summary}
                    canViewReports={!driver}
                />
                <section
                    className="dashboard-panel flex min-w-0 flex-col p-5 sm:p-6"
                    aria-labelledby="dashboard-period-result"
                >
                    <h2 id="dashboard-period-result" className="font-semibold tracking-tight">
                        {driver
                            ? 'Ringkasan pekerjaan'
                            : filters.vehicle_id
                              ? 'Ringkasan mobil'
                              : 'Pengeluaran operasional'}
                    </h2>
                    {!driver && !filters.vehicle_id ? (
                        <>
                            <p className="dashboard-heading mt-5 text-3xl font-semibold tabular-nums">
                                {rupiah(totals.expense_idr ?? 0)}
                            </p>
                            <p className="mt-3 text-xs leading-relaxed text-muted-foreground">
                                Biaya harian yang dicatat pada periode ini.
                            </p>
                            <Link className="dashboard-text-link mt-4" href={source('expenses')}>
                                Lihat pengeluaran{' '}
                                <ArrowUpRight className="size-3.5" aria-hidden="true" />
                            </Link>
                        </>
                    ) : (
                        <dl className="mt-5 space-y-5">
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Transaksi timbang neto
                                </dt>
                                <dd className="dashboard-heading mt-2 text-3xl font-semibold tabular-nums">
                                    {totals.weighing_count}
                                </dd>
                            </div>
                            <div className="border-t pt-4">
                                <dt className="text-xs text-muted-foreground">
                                    Total buah dari konsumen
                                </dt>
                                <dd className="mt-2 text-xl font-semibold tabular-nums">
                                    {weight(totals.tbs_bought_grams + totals.sort_bought_grams)}
                                </dd>
                            </div>
                        </dl>
                    )}
                    <p className="mt-auto pt-6 text-xs leading-relaxed text-muted-foreground">
                        {driver
                            ? 'Ringkasan mencakup timbangan dan penjualan yang dapat Anda akses.'
                            : filters.vehicle_id
                              ? 'Pengeluaran harian dicatat untuk seluruh usaha. Pilih semua mobil untuk melihat pengeluaran periode.'
                              : 'Pembayaran konsumen dan gaji dicatat terpisah dari biaya operasional harian.'}
                    </p>
                </section>
            </div>
            {summary.cashflow && <DashboardCashFlow flow={summary.cashflow} filters={filters} />}
            <VehicleSummary filters={filters} summary={summary} canViewReports={!driver} />
            <p className="flex items-start gap-2 text-[11px] leading-relaxed text-muted-foreground">
                <CircleHelp className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                Angka periode mengikuti tanggal transaksi dan pembalikannya. Saldo keuangan
                mengikuti seluruh tanggal transaksi.
            </p>
        </div>
    );
}

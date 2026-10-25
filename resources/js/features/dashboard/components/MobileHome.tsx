import { Link, usePage } from '@inertiajs/react';
import {
    Banknote,
    ChevronRight,
    Coins,
    ContactRound,
    Eye,
    EyeOff,
    Factory,
    Leaf,
    ReceiptText,
    Scale,
    Truck,
    Users,
    Wallet,
    ChartNoAxesCombined,
    type LucideIcon,
} from 'lucide-react';
import type { PeriodProps } from '@/features/reports/components/PeriodOverview';
import ResponsiveFilters from '@/components/common/ResponsiveFilters';
import useAmountVisibility from '@/hooks/use-amount-visibility';
import { rupiah } from '@/lib/currency';
import { weight } from '@/lib/weight';
import DashboardFilters, { dashboardDate } from './DashboardFilters';

export default function MobileHome({ filters, vehicles, summary }: PeriodProps) {
    const { auth, cash } = usePage().props;
    const role = auth.user.role;
    const driver = role === 'driver';
    const manager = role === 'manager';
    const { hidden, toggle } = useAmountVisibility();
    const totals = summary.totals;
    const assigned = vehicles.find((vehicle) => vehicle.id === auth.user.assigned_vehicle_id);
    const selectedPlate = vehicles.find(
        (vehicle) => vehicle.id === filters.vehicle_id,
    )?.plate_number;
    const period =
        filters.from === filters.to
            ? dashboardDate(filters.from, true)
            : `${dashboardDate(filters.from)} – ${dashboardDate(filters.to, true)}`;
    const hour = Number(
        new Intl.DateTimeFormat('en-GB', {
            hour: '2-digit',
            hour12: false,
            timeZone: 'Asia/Jakarta',
        }).format(new Date()),
    );
    const greeting =
        hour < 11
            ? 'Selamat pagi'
            : hour < 15
              ? 'Selamat siang'
              : hour < 18
                ? 'Selamat sore'
                : 'Selamat malam';
    const label = driver ? 'Buah ditimbang' : manager ? 'Hasil periode' : 'Saldo Kas Usaha';
    const cashBalance = cash?.balance_idr ?? summary.balances?.cash_idr;
    const amount = driver
        ? weight(totals.tbs_bought_grams + totals.sort_bought_grams)
        : manager
          ? totals.period_difference_idr === undefined
              ? 'Belum tersedia'
              : rupiah(totals.period_difference_idr)
          : cashBalance === undefined
            ? 'Belum tersedia'
            : rupiah(cashBalance);
    const actions: { label: string; name: string; icon: LucideIcon }[] = driver
        ? [
              { label: 'Timbang', name: 'weighings.create', icon: Scale },
              { label: 'Pabrik', name: 'factory-sales.create', icon: Factory },
              { label: 'Sortir', name: 'sort-sales.create', icon: ReceiptText },
              { label: 'Konsumen', name: 'customers.create', icon: ContactRound },
          ]
        : manager
          ? [
                { label: 'Timbang', name: 'weighings.create', icon: Scale },
                { label: 'Penimbang', name: 'customer-payments.index', icon: Banknote },
                { label: 'Gaji Muat', name: 'payroll.index', icon: Users },
                { label: 'Laporan', name: 'reports.index', icon: ChartNoAxesCombined },
            ]
          : [
                { label: 'Penimbang', name: 'customer-payments.index', icon: Banknote },
                { label: 'Gaji Muat', name: 'payroll.index', icon: Users },
                { label: 'Penerimaan', name: 'sale-receipts.index', icon: ReceiptText },
                { label: 'Uang Keluar', name: 'daily-expenses.create', icon: Wallet },
            ];
    const money = (value: number) => (hidden ? '••••••' : rupiah(value));

    return (
        <div className="mobile-home lg:hidden">
            <section className="mobile-home-hero" aria-labelledby="mobile-home-title">
                <h1 id="mobile-home-title">
                    {greeting}, {auth.user.name.split(' ')[0]}
                </h1>
                <div className="mobile-hero-label">
                    <p>{label}</p>
                    {!driver && (
                        <button
                            type="button"
                            onClick={toggle}
                            aria-label={hidden ? 'Tampilkan nominal' : 'Sembunyikan nominal'}
                            aria-pressed={hidden}
                        >
                            {hidden ? <EyeOff aria-hidden="true" /> : <Eye aria-hidden="true" />}
                        </button>
                    )}
                </div>
                <p className="mobile-hero-amount">{hidden && !driver ? '••••••••' : amount}</p>
                <p className="mobile-hero-caption">
                    {driver || manager ? period : 'Saldo seluruh tanggal'}
                </p>
                {!driver && !manager && cash && !cash.is_active && (
                    <p className="mt-2 text-sm">Kas usaha nonaktif</p>
                )}
                {driver && (
                    <p className="mobile-assigned-vehicle">
                        <Truck aria-hidden="true" />
                        {assigned
                            ? `${assigned.plate_number} · Mobil ditugaskan`
                            : 'Belum ada mobil ditugaskan'}
                    </p>
                )}
                <nav aria-label="Akses cepat" className="mobile-quick-actions">
                    {actions.map((action) => (
                        <Link key={action.name} href={route(action.name)}>
                            <span>
                                <action.icon aria-hidden="true" />
                            </span>
                            <span>{action.label}</span>
                        </Link>
                    ))}
                </nav>
            </section>

            <ResponsiveFilters
                title="Periode pemantauan"
                summary={`${period} · ${selectedPlate ?? 'Semua mobil'}`}
            >
                <DashboardFilters
                    idPrefix="mobile-dashboard"
                    filters={filters}
                    vehicles={vehicles}
                    today={summary.as_of_date}
                />
            </ResponsiveFilters>

            <section className="mobile-summary-panel" aria-labelledby="mobile-period-title">
                <div className="mobile-section-title">
                    <h2 id="mobile-period-title">
                        {driver ? 'Pekerjaan Anda' : 'Ringkasan periode'}
                    </h2>
                    <span>
                        {filters.from === summary.as_of_date && filters.to === summary.as_of_date
                            ? 'Hari ini'
                            : 'Sesuai filter'}
                    </span>
                </div>
                <dl className="mobile-period-metrics">
                    <div>
                        <dt>{driver ? 'Transaksi timbang' : 'Pembelian buah'}</dt>
                        <dd>{driver ? totals.weighing_count : money(totals.purchase_idr)}</dd>
                    </div>
                    <div>
                        <dt>{driver ? 'Buah terjual' : 'Penjualan'}</dt>
                        <dd>
                            {driver
                                ? weight(totals.factory_sold_grams + totals.sort_sold_grams)
                                : money(totals.revenue_idr)}
                        </dd>
                    </div>
                </dl>
                {!driver && (
                    <p className="mobile-context-note">
                        Nilai penjualan mengikuti nota, penerimaan uang dicatat terpisah.
                    </p>
                )}
            </section>

            {!driver && summary.balances && (
                <section className="mobile-home-section" aria-labelledby="mobile-obligations-title">
                    <div className="mobile-section-title">
                        <h2 id="mobile-obligations-title">Perlu diselesaikan</h2>
                    </div>
                    <p className="mobile-section-context">
                        Saldo sampai {dashboardDate(summary.as_of_date)}
                        {selectedPlate ? ` · ${selectedPlate}` : ''}
                    </p>
                    <div className="mobile-task-list">
                        <TaskRow
                            icon={ContactRound}
                            label="Utang konsumen"
                            value={money(summary.balances.customer_debt_idr)}
                            href={route('customer-payments.index')}
                        />
                        <TaskRow
                            icon={Users}
                            label="Utang upah muat"
                            value={money(summary.balances.payroll_debt_idr)}
                            href={route('payroll.index')}
                        />
                        <TaskRow
                            icon={ReceiptText}
                            label="Piutang penjualan"
                            value={money(summary.balances.sale_receivable_idr)}
                            href={route('sale-receipts.index')}
                        />
                    </div>
                </section>
            )}

            {manager && (
                <section className="mobile-home-section" aria-labelledby="mobile-manager-detail">
                    <div className="mobile-section-title">
                        <h2 id="mobile-manager-detail">Rincian hasil periode</h2>
                    </div>
                    <div className="mobile-task-list">
                        <TaskRow
                            icon={Users}
                            label="Hak upah muat"
                            value={money(totals.loading_idr)}
                            href={route('reports.index', { ...filters, view: 'payroll' })}
                        />
                        <TaskRow
                            icon={Wallet}
                            label="Pengeluaran harian"
                            value={money(totals.expense_idr ?? 0)}
                            href={route('reports.index', { ...filters, view: 'expenses' })}
                        />
                        {cashBalance !== undefined && (
                            <TaskRow
                                icon={Coins}
                                label="Saldo kas seluruh tanggal"
                                value={money(cashBalance)}
                                href={route('cash.index')}
                            />
                        )}
                    </div>
                    <p className="mobile-context-note">
                        Hasil periode = penjualan − pembelian − hak upah − pengeluaran. Kas mencakup
                        seluruh usaha.
                    </p>
                </section>
            )}

            {driver && (
                <section className="mobile-home-section" aria-labelledby="mobile-daily-title">
                    <div className="mobile-section-title">
                        <h2 id="mobile-daily-title">Ringkasan harian</h2>
                        <Link
                            href={route('weighings.index', { from: filters.from, to: filters.to })}
                        >
                            Lihat timbang <ChevronRight aria-hidden="true" />
                        </Link>
                    </div>
                    <div className="mobile-task-list">
                        {summary.days
                            .filter((day) => day.weighing_count > 0)
                            .slice(-3)
                            .reverse()
                            .map((day) => (
                                <TaskRow
                                    key={day.date}
                                    icon={Scale}
                                    label={dashboardDate(day.date)}
                                    detail={`${day.weighing_count} transaksi timbang`}
                                    value={weight(day.tbs_bought_grams + day.sort_bought_grams)}
                                    href={route('weighings.index', {
                                        from: day.date,
                                        to: day.date,
                                    })}
                                />
                            ))}
                        {!summary.days.some((day) => day.weighing_count > 0) && (
                            <div className="mobile-empty-state">
                                <Scale aria-hidden="true" />
                                <h3>Belum ada timbangan pada periode ini</h3>
                                <p>Catat timbangan pertama atau ubah periode pemantauan.</p>
                                <Link href={route('weighings.create')}>
                                    Catat timbangan <ChevronRight aria-hidden="true" />
                                </Link>
                            </div>
                        )}
                    </div>
                    <p className="mobile-context-note">
                        Ringkasan hanya mencakup transaksi yang dapat Anda akses.
                    </p>
                </section>
            )}

            {!driver && (
                <Link href={route('prices.index')} className="mobile-info-link">
                    <span className="mobile-info-icon">
                        <Leaf aria-hidden="true" />
                    </span>
                    <span>
                        <strong>Harga & upah muat</strong>
                        <span>Lihat harga yang berlaku</span>
                    </span>
                    <ChevronRight aria-hidden="true" />
                </Link>
            )}
        </div>
    );
}

function TaskRow({
    icon: Icon,
    label,
    value,
    href,
    detail,
}: {
    icon: LucideIcon;
    label: string;
    value: string | number;
    href: string;
    detail?: string;
}) {
    return (
        <Link href={href} className="mobile-task-row">
            <span className="mobile-task-icon">
                <Icon aria-hidden="true" />
            </span>
            <span className="mobile-task-content">
                <span className="mobile-task-label">{label}</span>
                {detail && <span className="mobile-task-detail">{detail}</span>}
                <strong>{value}</strong>
            </span>
            <ChevronRight aria-hidden="true" className="mobile-task-chevron" />
        </Link>
    );
}

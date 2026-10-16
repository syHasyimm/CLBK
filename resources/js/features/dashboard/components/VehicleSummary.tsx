import { Link } from '@inertiajs/react';
import { rupiah } from '@/lib/currency';
import { cn } from '@/lib/utils';
import type { PeriodProps } from '@/features/reports/components/PeriodOverview';
import { ArrowRight, ArrowUpRight, Truck } from 'lucide-react';
import { weight } from '@/lib/weight';

export default function VehicleSummary({
    summary,
    filters,
    canViewReports = true,
}: Pick<PeriodProps, 'summary' | 'filters'> & { canViewReports?: boolean }) {
    return (
        <section
            className="dashboard-panel overflow-hidden"
            aria-labelledby="dashboard-vehicles-title"
        >
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border/60 p-5 sm:p-6">
                <div>
                    <h2 id="dashboard-vehicles-title" className="font-semibold tracking-tight">
                        Aktivitas per mobil
                    </h2>
                    <p className="mt-1.5 text-xs text-muted-foreground">
                        Pembelian, penjualan, dan hak upah pada periode yang sama.
                    </p>
                </div>
                <span className="flex items-center gap-2 text-xs text-muted-foreground">
                    <Truck className="size-4" aria-hidden="true" />
                    {summary.vehicles.length} mobil tercatat
                </span>
            </div>
            {summary.vehicles.length ? (
                <>
                    <div className="divide-y sm:hidden">
                        {summary.vehicles.map((vehicle) => (
                            <article key={vehicle.id} className="p-5">
                                <div className="flex items-center justify-between gap-2">
                                    <VehicleLink
                                        vehicle={vehicle}
                                        filters={filters}
                                        canViewReports={canViewReports}
                                    />
                                    <span className="text-[11px] text-muted-foreground">
                                        {vehicle.weighing_count} timbang neto
                                    </span>
                                </div>
                                <dl className="mt-4 grid grid-cols-2 gap-4 text-xs">
                                    {[
                                        ['TBS dibeli', weight(vehicle.tbs_bought_grams)],
                                        ['Sortir dibeli', weight(vehicle.sort_bought_grams)],
                                        ['TBS ke pabrik', weight(vehicle.factory_sold_grams)],
                                        ['Sortir terjual', weight(vehicle.sort_sold_grams)],
                                        ['Pembelian', rupiah(vehicle.purchase_idr)],
                                        ['Pendapatan', rupiah(vehicle.revenue_idr)],
                                        ['Hak upah', rupiah(vehicle.loading_idr)],
                                    ].map(([label, value]) => (
                                        <div key={label}>
                                            <dt className="text-muted-foreground">{label}</dt>
                                            <dd className="mt-1.5 font-medium tabular-nums">
                                                {value}
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                            </article>
                        ))}
                    </div>
                    <div className="hidden overflow-x-auto sm:block">
                        <table className="w-full whitespace-nowrap text-left text-xs">
                            <thead className="bg-background/50 text-muted-foreground">
                                <tr>
                                    {[
                                        'Mobil',
                                        'Timbang neto',
                                        'Buah dibeli',
                                        'Buah terjual',
                                        'Pembelian',
                                        'Pendapatan',
                                        'Hak upah',
                                    ].map((label) => (
                                        <th
                                            key={label}
                                            scope="col"
                                            className="px-5 py-3.5 font-medium"
                                        >
                                            {label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border/60">
                                {summary.vehicles.map((vehicle) => (
                                    <tr
                                        key={vehicle.id}
                                        className="transition-colors hover:bg-muted/30"
                                    >
                                        <td className="px-5 py-5">
                                            <VehicleLink
                                                vehicle={vehicle}
                                                filters={filters}
                                                canViewReports={canViewReports}
                                            />
                                        </td>
                                        <td className="px-5 py-5 tabular-nums">
                                            {vehicle.weighing_count}
                                        </td>
                                        <td className="px-5 py-4 tabular-nums">
                                            <p>
                                                {weight(vehicle.tbs_bought_grams)}{' '}
                                                <span className="text-[10px] text-muted-foreground">
                                                    TBS
                                                </span>
                                            </p>
                                            <p className="mt-1.5 text-muted-foreground">
                                                {weight(vehicle.sort_bought_grams)}{' '}
                                                <span className="text-[10px]">sortir</span>
                                            </p>
                                        </td>
                                        <td className="px-5 py-4 tabular-nums">
                                            <p>
                                                {weight(vehicle.factory_sold_grams)}{' '}
                                                <span className="text-[10px] text-muted-foreground">
                                                    TBS
                                                </span>
                                            </p>
                                            <p className="mt-1.5 text-muted-foreground">
                                                {weight(vehicle.sort_sold_grams)}{' '}
                                                <span className="text-[10px]">sortir</span>
                                            </p>
                                        </td>
                                        {[
                                            vehicle.purchase_idr,
                                            vehicle.revenue_idr,
                                            vehicle.loading_idr,
                                        ].map((amount, index) => (
                                            <td
                                                key={index}
                                                className={cn(
                                                    'px-5 py-5 font-medium tabular-nums',
                                                    index === 1 && 'text-primary',
                                                )}
                                            >
                                                {rupiah(amount)}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </>
            ) : (
                <div className="flex flex-col items-center gap-2 px-6 py-12 text-center">
                    <Truck className="mb-2 size-7 text-muted-foreground/50" aria-hidden="true" />
                    <p className="text-sm font-medium">Belum ada aktivitas mobil</p>
                    <p className="max-w-sm text-xs leading-relaxed text-muted-foreground">
                        Transaksi pada periode dan mobil yang dipilih akan muncul di sini.
                    </p>
                </div>
            )}
            {canViewReports && (
                <Link
                    href={route('reports.index', filters)}
                    className="flex items-center justify-between border-t border-border/60 px-5 py-3.5 text-xs font-medium text-primary transition-colors hover:bg-secondary/40 focus-visible:outline-2 focus-visible:outline-ring sm:px-6"
                >
                    Buka laporan periode lengkap
                    <ArrowRight className="size-4" aria-hidden="true" />
                </Link>
            )}
        </section>
    );
}

function VehicleLink({
    vehicle,
    filters,
    canViewReports,
}: {
    vehicle: { id: number; plate_number: string };
    filters: PeriodProps['filters'];
    canViewReports: boolean;
}) {
    return canViewReports ? (
        <Link
            href={route('reports.index', { ...filters, vehicle_id: vehicle.id })}
            className="dashboard-text-link font-semibold"
        >
            {vehicle.plate_number}
            <ArrowUpRight className="size-3.5" aria-hidden="true" />
        </Link>
    ) : (
        <span className="font-semibold text-primary">{vehicle.plate_number}</span>
    );
}

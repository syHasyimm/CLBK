import { Link } from '@inertiajs/react';
import {
    ArrowUpRight,
    ChevronLeft,
    ChevronRight,
    Factory,
    Plus,
    ReceiptText,
    Truck,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import PaymentStatusBadge from '@/features/finance/components/PaymentStatusBadge';
import { rupiah } from '@/lib/currency';
import { weight } from '@/lib/weight';
import { dateTime } from '@/lib/dates';
import { statusLabels, type PaginatedData } from '@/types/operations';
import type { SaleRecord } from '@/types/sales';

function Status({ record }: { record: SaleRecord }) {
    return (
        <Badge
            variant={record.status === 'posted' ? 'secondary' : 'outline'}
            className="rounded-md text-[10px]"
        >
            {statusLabels[record.status]}
        </Badge>
    );
}
function Payment({ record }: { record: SaleRecord }) {
    return record.payment_status ? (
        <div className="space-y-1.5">
            <PaymentStatusBadge status={record.payment_status} />
            {record.payment_status !== 'paid' && (
                <p className="text-[11px] tabular-nums text-muted-foreground">
                    Sisa {rupiah(record.receivable_idr)}
                </p>
            )}
        </div>
    ) : (
        <span className="text-xs text-muted-foreground">
            {record.status === 'reversed' ? 'Nota dibalik' : 'Belum disahkan'}
        </span>
    );
}
function DetailLink({ record }: { record: SaleRecord }) {
    return (
        <Link
            href={route('factory-sales.show', { sale: record.id })}
            className="factory-text-link"
            aria-label={'Rincian penjualan ' + record.number}
        >
            Rincian <ArrowUpRight className="size-3.5" aria-hidden="true" />
        </Link>
    );
}
export default function FactorySalesTable({
    sales,
    filtered,
    canCreate,
}: {
    sales: PaginatedData<SaleRecord>;
    filtered: boolean;
    canCreate: boolean;
}) {
    return (
        <>
            {sales.data.length ? (
                <>
                    <div className="hidden overflow-x-auto xl:block">
                        <table className="w-full min-w-[58rem] text-left text-sm">
                            <caption className="sr-only">
                                Nota penjualan pabrik, tanggal, mobil, berat, nilai penjualan dan
                                penerimaan uang.
                            </caption>
                            <thead className="border-b text-[11px] text-muted-foreground">
                                <tr>
                                    {[
                                        'Penjualan',
                                        'Pabrik / nota',
                                        'Mobil',
                                        'Berat bersih',
                                        'Nilai nota',
                                        'Penerimaan',
                                        '',
                                    ].map((label, i) => (
                                        <th scope="col" key={i} className="px-5 py-4 font-medium">
                                            {label || <span className="sr-only">Tindakan</span>}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {sales.data.map((record) => (
                                    <tr
                                        key={record.id}
                                        className="transition-colors hover:bg-secondary/20"
                                    >
                                        <td className="px-5 py-5">
                                            <Link
                                                className="font-semibold text-primary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring"
                                                href={route('factory-sales.show', {
                                                    sale: record.id,
                                                })}
                                            >
                                                {record.number}
                                            </Link>
                                            <p className="mt-1.5 text-[11px] text-muted-foreground">
                                                {dateTime(record.sold_at)}
                                            </p>
                                            <div className="mt-2">
                                                <Status record={record} />
                                            </div>
                                        </td>
                                        <td className="max-w-56 px-5 py-5">
                                            <p className="break-words font-medium">
                                                {record.factory?.name ?? '—'}
                                            </p>
                                            <p className="mt-1.5 text-xs text-muted-foreground">
                                                {record.invoice_number || 'Tanpa nomor nota'}
                                            </p>
                                        </td>
                                        <td className="px-5 py-5">
                                            <span className="inline-flex items-center gap-1.5 whitespace-nowrap text-xs">
                                                <Truck
                                                    className="size-3.5 text-muted-foreground"
                                                    aria-hidden="true"
                                                />
                                                {record.vehicle.plate_number}
                                            </span>
                                        </td>
                                        <td className="px-5 py-5">
                                            <p className="whitespace-nowrap font-medium tabular-nums">
                                                {record.accepted_grams != null
                                                    ? weight(record.accepted_grams)
                                                    : '—'}
                                            </p>
                                            <p className="mt-1.5 whitespace-nowrap text-[11px] tabular-nums text-muted-foreground">
                                                {rupiah(record.sell_price_snapshot_idr)} / kg
                                            </p>
                                        </td>
                                        <td className="px-5 py-5">
                                            <p className="whitespace-nowrap font-semibold tabular-nums text-primary">
                                                {rupiah(record.revenue_idr)}
                                            </p>
                                            {record.deduction_idr > 0 && (
                                                <p className="mt-1.5 text-[11px] tabular-nums text-muted-foreground">
                                                    Potongan {rupiah(record.deduction_idr)}
                                                </p>
                                            )}
                                        </td>
                                        <td className="px-5 py-5">
                                            <Payment record={record} />
                                        </td>
                                        <td className="px-5 py-5">
                                            <DetailLink record={record} />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="divide-y xl:hidden">
                        {sales.data.map((record) => (
                            <article key={record.id} className="p-5 sm:p-6">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <Link
                                            className="font-semibold text-primary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-ring"
                                            href={route('factory-sales.show', { sale: record.id })}
                                        >
                                            {record.number}
                                        </Link>
                                        <p className="mt-1.5 text-[11px] text-muted-foreground">
                                            {dateTime(record.sold_at)}
                                        </p>
                                    </div>
                                    <Status record={record} />
                                </div>
                                <div className="mt-4 flex items-start gap-2 text-sm">
                                    <Factory
                                        className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <div>
                                        <p className="font-medium">{record.factory?.name ?? '—'}</p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {record.invoice_number || 'Tanpa nomor nota'}
                                        </p>
                                    </div>
                                </div>
                                <dl className="mt-4 grid grid-cols-2 gap-4 rounded-lg bg-muted/40 p-3.5">
                                    <div>
                                        <dt className="text-[11px] text-muted-foreground">Mobil</dt>
                                        <dd className="mt-1.5 text-xs font-medium">
                                            {record.vehicle.plate_number}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-[11px] text-muted-foreground">
                                            Berat bersih
                                        </dt>
                                        <dd className="mt-1.5 text-sm font-medium tabular-nums">
                                            {record.accepted_grams != null
                                                ? weight(record.accepted_grams)
                                                : '—'}
                                        </dd>
                                    </div>
                                </dl>
                                <div className="mt-4 flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p className="text-[11px] text-muted-foreground">
                                            Nilai nota
                                        </p>
                                        <p className="factory-heading mt-1 text-xl font-semibold tabular-nums text-primary">
                                            {rupiah(record.revenue_idr)}
                                        </p>
                                        {record.deduction_idr > 0 && (
                                            <p className="mt-1 text-[11px] text-muted-foreground">
                                                Potongan {rupiah(record.deduction_idr)}
                                            </p>
                                        )}
                                    </div>
                                    <Payment record={record} />
                                </div>
                                <div className="mt-4 flex items-center justify-between gap-3 border-t pt-3.5">
                                    <span className="text-[11px] tabular-nums text-muted-foreground">
                                        {rupiah(record.sell_price_snapshot_idr)} / kg
                                    </span>
                                    <DetailLink record={record} />
                                </div>
                            </article>
                        ))}
                    </div>
                </>
            ) : (
                <div className="flex flex-col items-center px-6 py-16 text-center">
                    <span className="mb-5 flex size-14 items-center justify-center rounded-2xl bg-secondary text-primary">
                        <ReceiptText className="size-6" aria-hidden="true" />
                    </span>
                    <h3 className="font-semibold">
                        {filtered ? 'Tidak ada nota yang sesuai' : 'Belum ada penjualan pabrik'}
                    </h3>
                    <p className="mt-2 max-w-sm text-sm leading-relaxed text-muted-foreground">
                        {filtered
                            ? 'Sesuaikan nomor, status, atau tanggal untuk menampilkan nota lainnya.'
                            : 'Catat berat bersih dari pabrik. Harga dan nilai penjualan dihitung saat pencatatan.'}
                    </p>
                    {!filtered && canCreate && (
                        <Button asChild className="mt-6">
                            <Link href={route('factory-sales.create')}>
                                <Plus className="size-4" aria-hidden="true" />
                                Catat penjualan pertama
                            </Link>
                        </Button>
                    )}
                </div>
            )}
            <div className="flex flex-wrap items-center justify-between gap-4 border-t px-5 py-4 text-xs text-muted-foreground sm:px-6">
                <p>
                    {sales.total ? `${sales.from}–${sales.to} dari ${sales.total} nota` : '0 nota'}
                </p>
                <nav
                    aria-label="Halaman daftar penjualan pabrik"
                    className="flex items-center gap-3"
                >
                    <span>
                        Halaman {sales.current_page} / {sales.last_page}
                    </span>
                    {[
                        {
                            href: sales.prev_page_url,
                            label: 'Halaman sebelumnya',
                            Icon: ChevronLeft,
                        },
                        {
                            href: sales.next_page_url,
                            label: 'Halaman berikutnya',
                            Icon: ChevronRight,
                        },
                    ].map(({ href, label, Icon }) =>
                        href ? (
                            <Button key={label} variant="outline" size="icon" asChild>
                                <Link href={href} preserveScroll aria-label={label}>
                                    <Icon className="size-4" />
                                </Link>
                            </Button>
                        ) : (
                            <Button
                                key={label}
                                variant="outline"
                                size="icon"
                                disabled
                                aria-label={label}
                            >
                                <Icon className="size-4" />
                            </Button>
                        ),
                    )}
                </nav>
            </div>
        </>
    );
}

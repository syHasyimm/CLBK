import { Link } from '@inertiajs/react';
import { ArrowUpRight, ChevronLeft, ChevronRight, Plus, Scale, Truck, Users } from 'lucide-react';
import PaymentStatusBadge from '@/features/finance/components/PaymentStatusBadge';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { dateTime } from '@/lib/dates';
import { rupiah } from '@/lib/currency';
import { weight } from '@/lib/weight';
import { statusLabels, type Weighing, type PaginatedData } from '@/types/operations';

function TicketStatus({ status }: { status: Weighing['status'] }) {
    return (
        <Badge
            variant={status === 'posted' ? 'secondary' : 'outline'}
            className={status === 'voided' ? 'text-muted-foreground' : undefined}
        >
            {statusLabels[status]}
        </Badge>
    );
}

function FruitWeights({ record }: { record: Weighing }) {
    return (
        <dl className="space-y-2 text-xs">
            {(
                [
                    { type: 'TBS', label: 'TBS' },
                    { type: 'SORT', label: 'Sortir' },
                ] as const
            ).map(({ type, label }) => (
                <div key={type} className="flex justify-between gap-4">
                    <dt className="text-muted-foreground">{label}</dt>
                    <dd className="whitespace-nowrap font-medium tabular-nums">
                        {weight(
                            record.items.find((item) => item.fruit_type === type)?.weight_grams ??
                                0,
                        )}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

function ConsumerAmount({ record }: { record: Weighing }) {
    return (
        <div>
            <p className="break-words text-sm font-semibold tabular-nums text-primary">
                {rupiah(record.total_purchase_idr)}
            </p>
            {record.payment_status && (
                <div className="mt-2">
                    <PaymentStatusBadge status={record.payment_status} />
                </div>
            )}
            {record.payment_status === 'partial' && (
                <p className="mt-1.5 text-[11px] tabular-nums text-muted-foreground">
                    Sisa {rupiah(record.remaining_idr)}
                </p>
            )}
        </div>
    );
}

export default function WeighingsTable({
    records,
    cashier,
    filtered,
}: {
    records: PaginatedData<Weighing>;
    cashier: boolean;
    filtered: boolean;
}) {
    return (
        <>
            {records.data.length ? (
                <>
                    <div className="hidden overflow-x-auto xl:block">
                        <table className="w-full text-left text-sm">
                            <caption className="sr-only">
                                Daftar timbangan sawit beserta konsumen, mobil, berat buah, anggota
                                muat, dan pembayaran.
                            </caption>
                            <thead className="border-b bg-muted/40 text-xs text-muted-foreground">
                                <tr>
                                    {[
                                        'Tiket & waktu timbang',
                                        'Konsumen & mobil',
                                        'Berat buah',
                                        'Uang konsumen',
                                        'Anggota muat',
                                        '',
                                    ].map((label, index) => (
                                        <th
                                            key={index}
                                            scope="col"
                                            className="whitespace-nowrap px-5 py-4 font-medium"
                                        >
                                            {label || <span className="sr-only">Rincian</span>}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {records.data.map((record) => (
                                    <tr
                                        key={record.id}
                                        className="transition-colors hover:bg-muted/30"
                                    >
                                        <th scope="row" className="px-5 py-5 text-left font-normal">
                                            <Link
                                                href={route('weighings.show', record.id)}
                                                className="whitespace-nowrap font-mono text-xs font-semibold text-primary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring"
                                            >
                                                {record.number}
                                            </Link>
                                            <p className="mt-2 whitespace-nowrap text-xs text-muted-foreground">
                                                {dateTime(record.weighed_at)} WIB
                                            </p>
                                            <div className="mt-2.5">
                                                <TicketStatus status={record.status} />
                                            </div>
                                        </th>
                                        <td className="min-w-40 px-5 py-5">
                                            <p className="font-medium">{record.customer.name}</p>
                                            <p className="mt-2 inline-flex items-center gap-1.5 text-xs text-muted-foreground">
                                                <Truck aria-hidden="true" className="size-3.5" />
                                                {record.vehicle.plate_number}
                                            </p>
                                        </td>
                                        <td className="min-w-40 px-5 py-5">
                                            <FruitWeights record={record} />
                                        </td>
                                        <td className="min-w-40 px-5 py-5">
                                            <ConsumerAmount record={record} />
                                        </td>
                                        <td className="max-w-48 px-5 py-5">
                                            <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                                <Users aria-hidden="true" className="size-3.5" />
                                                {record.loaders.length} anggota
                                            </p>
                                            <p className="mt-2 text-xs leading-relaxed">
                                                {record.loaders
                                                    .map((loader) => loader.worker?.name)
                                                    .filter(Boolean)
                                                    .join(', ') || 'Belum dipilih'}
                                            </p>
                                        </td>
                                        <td className="px-3 py-5">
                                            <Button asChild variant="ghost" size="icon-sm">
                                                <Link
                                                    href={route('weighings.show', record.id)}
                                                    aria-label={'Lihat rincian ' + record.number}
                                                >
                                                    <ArrowUpRight
                                                        aria-hidden="true"
                                                        className="size-4"
                                                    />
                                                </Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <ul className="divide-y xl:hidden">
                        {records.data.map((record) => (
                            <li key={record.id} className="px-5 py-5 sm:px-6">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <Link
                                            href={route('weighings.show', record.id)}
                                            className="font-mono text-xs font-semibold text-primary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-ring"
                                        >
                                            {record.number}
                                        </Link>
                                        <p className="mt-1.5 text-xs text-muted-foreground">
                                            {dateTime(record.weighed_at)} WIB
                                        </p>
                                    </div>
                                    <TicketStatus status={record.status} />
                                </div>
                                <div className="mt-4 flex flex-wrap items-center justify-between gap-2">
                                    <p className="text-sm font-semibold">{record.customer.name}</p>
                                    <p className="inline-flex items-center gap-1.5 rounded-md bg-muted px-2 py-1 text-xs text-muted-foreground">
                                        <Truck aria-hidden="true" className="size-3.5" />
                                        {record.vehicle.plate_number}
                                    </p>
                                </div>
                                <div className="mt-4 grid gap-5 sm:grid-cols-2">
                                    <div>
                                        <p className="mb-2 text-xs font-medium">Berat buah</p>
                                        <FruitWeights record={record} />
                                    </div>
                                    <div>
                                        <p className="mb-2 text-xs text-muted-foreground">
                                            Uang konsumen
                                        </p>
                                        <ConsumerAmount record={record} />
                                    </div>
                                </div>
                                <div className="mt-4 flex flex-wrap items-end justify-between gap-3 border-t pt-4">
                                    <div className="min-w-0 flex-1">
                                        <p className="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                            <Users aria-hidden="true" className="size-3.5" />
                                            Anggota muat ({record.loaders.length})
                                        </p>
                                        <p className="mt-1 text-xs leading-relaxed break-words">
                                            {record.loaders
                                                .map((loader) => loader.worker?.name)
                                                .filter(Boolean)
                                                .join(', ') || 'Belum dipilih'}
                                        </p>
                                    </div>
                                    <Button asChild variant="secondary" size="sm">
                                        <Link
                                            href={route('weighings.show', record.id)}
                                            aria-label={'Lihat rincian ' + record.number}
                                        >
                                            Rincian
                                            <ArrowUpRight aria-hidden="true" className="size-3.5" />
                                        </Link>
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                </>
            ) : (
                <div className="px-6 py-14 text-center">
                    <span className="mx-auto mb-4 flex size-14 items-center justify-center rounded-2xl bg-secondary text-primary">
                        <Scale aria-hidden="true" className="size-6" />
                    </span>
                    <h3 className="text-base font-semibold">
                        {filtered || records.total
                            ? 'Tidak ada timbangan yang ditampilkan'
                            : 'Belum ada timbangan sawit'}
                    </h3>
                    <p className="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-muted-foreground">
                        {filtered || records.total
                            ? 'Sesuaikan pencarian, status, atau rentang tanggal untuk melihat timbangan.'
                            : 'Timbangan yang tersimpan akan tampil beserta berat buah, uang konsumen, dan status pembayarannya.'}
                    </p>
                    {!cashier && !filtered && !records.total && (
                        <Button asChild className="mt-5">
                            <Link href={route('weighings.create')}>
                                <Plus aria-hidden="true" className="size-4" />
                                Timbang sawit
                            </Link>
                        </Button>
                    )}
                </div>
            )}
            <div className="flex flex-wrap items-center justify-between gap-3 border-t px-5 py-4 text-xs text-muted-foreground sm:px-6">
                <p className="tabular-nums">
                    {records.from != null && records.to != null
                        ? `${records.from}-${records.to} dari ${records.total} tiket`
                        : `0 dari ${records.total} tiket`}
                </p>
                <div className="flex items-center gap-2">
                    <span className="mr-1 tabular-nums">
                        Halaman {records.current_page} / {records.last_page}
                    </span>
                    {[
                        {
                            label: 'Halaman sebelumnya',
                            url: records.prev_page_url,
                            icon: ChevronLeft,
                        },
                        {
                            label: 'Halaman berikutnya',
                            url: records.next_page_url,
                            icon: ChevronRight,
                        },
                    ].map(({ label, url, icon: Icon }) =>
                        url ? (
                            <Button key={label} asChild variant="outline" size="icon-sm">
                                <Link href={url} preserveScroll aria-label={label}>
                                    <Icon aria-hidden="true" className="size-4" />
                                </Link>
                            </Button>
                        ) : (
                            <Button
                                key={label}
                                variant="outline"
                                size="icon-sm"
                                disabled
                                aria-label={label}
                            >
                                <Icon aria-hidden="true" className="size-4" />
                            </Button>
                        ),
                    )}
                </div>
            </div>
        </>
    );
}

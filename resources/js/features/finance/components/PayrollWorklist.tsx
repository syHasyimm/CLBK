import { Head, Link } from '@inertiajs/react';
import { ArrowUpRight, HandCoins, History, Users } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { rupiah } from '@/lib/currency';
import { dateTime } from '@/lib/dates';
import type { PayeeCard, PaginatedData } from '@/types/finance';
import PaymentStatusBadge from './PaymentStatusBadge';
import PayrollFilters from './PayrollFilters';
import PayrollPagination from './PayrollPagination';

export default function PayrollWorklist({
    cards,
    filters,
}: {
    cards: PaginatedData<PayeeCard>;
    filters: Record<string, string>;
}) {
    const filtered = !!(filters.q || filters.from || filters.to);
    return (
        <AppLayout
            header={
                <div className="payroll flex flex-wrap items-center justify-between gap-5">
                    <div className="flex items-start gap-4">
                        <span className="hidden size-12 shrink-0 items-center justify-center rounded-2xl border border-primary/10 bg-secondary text-primary sm:flex">
                            <HandCoins className="size-6" aria-hidden="true" />
                        </span>
                        <div>
                            <p className="mb-2 text-[11px] font-medium text-muted-foreground">
                                Keuangan / Upah muat
                            </p>
                            <h1 className="payroll-heading text-2xl font-semibold sm:text-3xl">
                                Gaji karyawan muat
                            </h1>
                            <p className="mt-2 max-w-xl text-sm leading-relaxed text-muted-foreground">
                                Periksa sisa upah dari timbangan yang belum lunas dan catat
                                pembayaran setiap karyawan.
                            </p>
                        </div>
                    </div>
                    <Button variant="outline" className="rounded-lg" asChild>
                        <Link href={route('payroll.history')}>
                            <History className="size-4" aria-hidden="true" />
                            Riwayat penggajian
                        </Link>
                    </Button>
                </div>
            }
        >
            <Head title="Gaji Karyawan Muat" />
            <div className="payroll">
                <p className="mb-5 flex items-start gap-2 text-xs leading-relaxed text-muted-foreground">
                    <HandCoins className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                    Hak upah mengikuti pembagian yang tercatat saat timbang. Dasar pembayaran
                    berasal dari seluruh sisa upah dalam periode yang diperiksa.
                </p>
                <section
                    className="payroll-panel overflow-hidden"
                    aria-labelledby="payroll-workers-title"
                >
                    <div className="flex items-center justify-between gap-4 px-5 py-5 sm:px-6">
                        <div>
                            <h2 id="payroll-workers-title" className="text-sm font-semibold">
                                Daftar karyawan
                            </h2>
                            <p className="mt-1 text-[11px] text-muted-foreground">
                                {filtered
                                    ? 'Hak upah sesuai filter pekerjaan'
                                    : 'Ringkasan hak upah seluruh pekerjaan'}
                            </p>
                        </div>
                        <span className="rounded-md bg-muted px-2.5 py-1.5 text-[11px] font-medium tabular-nums">
                            {cards.total} karyawan
                        </span>
                    </div>
                    <PayrollFilters url={route('payroll.index')} filters={filters} />
                    <div className="divide-y">
                        {cards.data.map((card) => (
                            <article
                                key={card.id}
                                className="p-5 transition-colors hover:bg-secondary/15 sm:p-6"
                            >
                                <div className="grid items-start gap-5 xl:grid-cols-[minmax(0,1.25fr)_minmax(0,1fr)_minmax(0,1fr)_auto]">
                                    <div className="flex items-start gap-3">
                                        <span
                                            className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-secondary text-sm font-semibold text-primary"
                                            aria-hidden="true"
                                        >
                                            {card.name
                                                .trim()
                                                .slice(0, 2)
                                                .toLocaleUpperCase('id-ID')}
                                        </span>
                                        <div className="min-w-0">
                                            <h3 className="break-words text-sm font-semibold">
                                                {card.name}
                                            </h3>
                                            <p className="mt-1.5 text-[11px] text-muted-foreground">
                                                {card.code} · {card.ticket_count} timbangan
                                            </p>
                                            {!card.is_active && (
                                                <Badge
                                                    variant="outline"
                                                    className="mt-2 rounded-md text-[10px]"
                                                >
                                                    Karyawan nonaktif
                                                </Badge>
                                            )}
                                        </div>
                                    </div>
                                    <dl className="space-y-2 text-xs">
                                        <div className="flex justify-between gap-3 xl:justify-start">
                                            <dt className="text-muted-foreground">Total hak</dt>
                                            <dd className="font-medium tabular-nums">
                                                {rupiah(card.total_idr)}
                                            </dd>
                                        </div>
                                        <div className="flex justify-between gap-3 xl:justify-start">
                                            <dt className="text-muted-foreground">Dibayar</dt>
                                            <dd className="font-medium tabular-nums">
                                                {rupiah(card.paid_idr)}
                                            </dd>
                                        </div>
                                    </dl>
                                    <div className="flex items-start justify-between gap-4 xl:block">
                                        <div>
                                            <p className="text-[11px] text-muted-foreground">
                                                Sisa upah
                                            </p>
                                            <p className="payroll-heading mt-1 text-xl font-semibold tabular-nums text-primary">
                                                {rupiah(card.remaining_idr)}
                                            </p>
                                        </div>
                                        <div className="xl:mt-2">
                                            <PaymentStatusBadge
                                                status={card.paid_idr > 0 ? 'partial' : 'unpaid'}
                                            />
                                        </div>
                                    </div>
                                    <Button asChild variant="default" className="rounded-lg">
                                        <Link
                                            href={route('payroll.payee', {
                                                recipient: card.id,
                                                from: filters.from || card.first_work_date,
                                                to: filters.to || card.last_work_date,
                                            })}
                                        >
                                            Periksa & bayar
                                            <ArrowUpRight className="size-3.5" aria-hidden="true" />
                                        </Link>
                                    </Button>
                                </div>
                                <p className="mt-4 border-t pt-3 text-[11px] leading-relaxed text-muted-foreground">
                                    {card.last_payment ? (
                                        <>
                                            Pembayaran terakhir{' '}
                                            {dateTime(card.last_payment.occurred_at)} ·{' '}
                                            <span className="tabular-nums">
                                                {rupiah(card.last_payment.amount_idr)}
                                            </span>
                                        </>
                                    ) : (
                                        'Belum ada pembayaran tercatat.'
                                    )}
                                </p>
                            </article>
                        ))}
                    </div>
                    {!cards.data.length && (
                        <div className="flex flex-col items-center px-6 py-14 text-center">
                            <Users className="mb-4 size-8 text-primary/40" aria-hidden="true" />
                            <h3 className="font-semibold">Tidak ada sisa upah pada filter ini</h3>
                            <p className="mt-2 max-w-sm text-sm leading-relaxed text-muted-foreground">
                                Pekerjaan yang sudah lunas tersedia di Riwayat Penggajian Karyawan.
                                Sesuaikan nama atau periode untuk memeriksa sisa upah lainnya.
                            </p>
                        </div>
                    )}
                    <PayrollPagination records={cards} label="karyawan" />
                </section>
            </div>
        </AppLayout>
    );
}

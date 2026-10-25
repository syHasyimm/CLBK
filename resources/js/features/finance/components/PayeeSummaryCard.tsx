import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import PaymentStatusBadge from './PaymentStatusBadge';
import { rupiah } from '@/lib/currency';
import { dateTime } from '@/lib/dates';
import { kg } from '@/lib/weight';
import type { FinanceKind, PayeeCard } from '@/types/finance';
import { payeeRoute } from '@/types/finance';
import MobilePayeeRow from './MobilePayeeRow';
export default function PayeeSummaryCard({
    kind,
    card,
    filters,
}: {
    kind: FinanceKind;
    card: PayeeCard;
    filters: Record<string, string>;
}) {
    return (
        <>
            <MobilePayeeRow
                card={card}
                payroll={kind === 'payroll'}
                href={route(payeeRoute(kind), {
                    recipient: card.id,
                    from: filters.from,
                    to: filters.to,
                })}
            />
            <article className="hidden rounded-xl border bg-card p-6 lg:block print:block">
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <p className="text-xs text-muted-foreground">
                            {card.code}
                            {!card.is_active && ' · Nonaktif'}
                        </p>
                        <h2 className="mt-2 text-lg font-semibold">{card.name}</h2>
                    </div>
                    <PaymentStatusBadge
                        status={
                            card.remaining_idr === 0
                                ? 'paid'
                                : card.paid_idr > 0
                                  ? 'partial'
                                  : 'unpaid'
                        }
                    />
                </div>
                <p className="mt-4 text-sm text-muted-foreground">
                    {card.ticket_count}{' '}
                    {kind === 'customer' ? 'tiket timbang belum lunas' : 'pekerjaan'} dalam filter
                    ini
                </p>
                <dl className="mt-5 space-y-3 text-sm">
                    <div className="flex justify-between gap-3">
                        <dt>Total hak</dt>
                        <dd>{rupiah(card.total_idr)}</dd>
                    </div>
                    <div className="flex justify-between gap-3">
                        <dt>Sudah dibayar</dt>
                        <dd>{rupiah(card.paid_idr)}</dd>
                    </div>
                    <div className="flex justify-between gap-3 border-t pt-3 font-semibold">
                        <dt>Sisa hak</dt>
                        <dd>{rupiah(card.remaining_idr)}</dd>
                    </div>
                </dl>
                {kind === 'customer' && (
                    <p className="mt-5 border-t pt-4 text-xs font-medium text-foreground">
                        Berat per timbangan
                    </p>
                )}
                <ul
                    className={
                        'divide-y text-xs text-muted-foreground ' +
                        (kind === 'customer' ? 'mt-3' : 'mt-5 border-t pt-4')
                    }
                >
                    {card.tickets.map((ticket) => (
                        <li key={ticket.weighing_id} className="py-4 first:pt-0 last:pb-0">
                            <div className="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                                <Link
                                    className="min-w-0 break-words font-medium text-primary underline underline-offset-2"
                                    href={route('weighings.show', ticket.weighing_id)}
                                >
                                    {ticket.number}
                                </Link>
                                <p className="shrink-0 text-[11px]">{ticket.business_date}</p>
                            </div>
                            {kind === 'customer' ? (
                                <dl className="mt-3 grid grid-cols-2 gap-3">
                                    {['TBS', 'SORT'].map((fruitType) => {
                                        const item = ticket.items.find(
                                            (item) => item.fruit_type === fruitType,
                                        );
                                        return (
                                            <div
                                                key={fruitType}
                                                className="min-w-0 border-l pl-3 first:border-l-0 first:pl-0"
                                            >
                                                <dt className="text-xs font-medium">
                                                    {fruitType === 'TBS'
                                                        ? 'Berat TBS'
                                                        : 'Berat sortir'}
                                                </dt>
                                                <dd className="mt-1.5">
                                                    <span className="flex flex-wrap items-baseline gap-x-1 font-semibold tabular-nums text-primary">
                                                        <span className="min-w-0 break-words text-lg leading-snug sm:text-xl">
                                                            {kg(item?.weight_grams ?? 0)}
                                                        </span>
                                                        <span className="text-xs font-medium">
                                                            kg
                                                        </span>
                                                    </span>
                                                    <span className="mt-1 block text-[11px] leading-relaxed text-muted-foreground">
                                                        {item
                                                            ? `Harga ${rupiah(item.buy_price_snapshot_idr)}/kg`
                                                            : fruitType === 'TBS'
                                                              ? 'Tidak ada TBS'
                                                              : 'Tidak ada sortir'}
                                                    </span>
                                                </dd>
                                            </div>
                                        );
                                    })}
                                </dl>
                            ) : (
                                <p className="mt-1">
                                    Dasar {kg(ticket.basis_grams)} kg × {rupiah(ticket.rate_idr)}/kg
                                    · {ticket.member_count} anggota
                                </p>
                            )}
                        </li>
                    ))}
                </ul>
                {card.ticket_count > 3 && (
                    <p className="mt-2 text-xs text-muted-foreground">
                        Rincian seluruh {card.ticket_count} tiket tersedia pada halaman penerima.
                    </p>
                )}
                <p className="mt-4 text-xs leading-relaxed text-muted-foreground">
                    {card.last_payment ? (
                        <>
                            Pembayaran terakhir {dateTime(card.last_payment.occurred_at)} ·{' '}
                            {rupiah(card.last_payment.amount_idr)}
                        </>
                    ) : (
                        'Belum ada pembayaran tercatat.'
                    )}
                </p>
                <Button
                    className="mt-5 w-full"
                    variant={card.remaining_idr ? 'default' : 'outline'}
                    asChild
                >
                    <Link
                        href={route(payeeRoute(kind), {
                            recipient: card.id,
                            from: filters.from,
                            to: filters.to,
                        })}
                    >
                        {card.remaining_idr ? 'Periksa & bayar' : 'Lihat rincian'}
                    </Link>
                </Button>
            </article>
        </>
    );
}

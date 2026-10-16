import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import PaymentStatusBadge from '@/features/finance/components/PaymentStatusBadge';
import CostSummary from '@/features/sales/components/CostSummary';
import { rupiah } from '@/lib/currency';
import { dateTime } from '@/lib/dates';
import { kg } from '@/lib/weight';
import { statusLabels } from '@/types/operations';
import { Link } from '@inertiajs/react';

import type { SaleActionsModel } from '@/features/sales/hooks/use-sale-actions';

export default function SaleDetails({ model }: { model: SaleActionsModel }) {
    const { kind, record, preview, cashReceipts, canReceive, direct, manager, current } = model;

    return (
        <>
            <div className="mb-6 flex flex-wrap items-center gap-3">
                <Badge variant="secondary">{statusLabels[record.status]}</Badge>
                <span className="text-sm font-medium">{record.vehicle.plate_number}</span>
                {!direct && record.trip && (
                    <span className="text-xs text-muted-foreground">
                        Referensi arsip: {record.trip.number}
                    </span>
                )}
            </div>
            <div className="mb-6 grid gap-5 rounded-xl border bg-card p-6 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <p className="text-xs text-muted-foreground">
                        {kind === 'factory' ? 'Pabrik / nomor nota' : 'Pembeli'}
                    </p>
                    <p className="mt-2 font-semibold">
                        {kind === 'factory'
                            ? record.factory?.name
                            : record.buyer_name || 'Tidak dicatat'}
                    </p>
                    <p className="text-sm text-muted-foreground">
                        {kind === 'factory' ? record.invoice_number : record.buyer_contact}
                    </p>
                </div>
                <div>
                    <p className="text-xs text-muted-foreground">Waktu penjualan</p>
                    <p className="mt-2 font-semibold">{dateTime(record.sold_at)}</p>
                    <p className="text-sm text-muted-foreground">
                        Tanggal usaha {record.business_date}
                    </p>
                </div>
                <div>
                    <p className="text-xs text-muted-foreground">Harga aktual / kg</p>
                    <p className="mt-2 font-semibold">{rupiah(record.sell_price_snapshot_idr)}</p>
                </div>
                {kind === 'factory' ? (
                    <>
                        {(direct
                            ? [['Bersih diterima', record.accepted_grams]]
                            : [
                                  ['Muatan awal', record.initial_load_grams],
                                  ['Sortir pabrik', record.sorted_out_grams],
                                  ['Bersih diterima', record.accepted_grams],
                                  ['Selisih timbang', record.weight_difference_grams],
                              ]
                        ).map(([label, value]) => (
                            <div key={String(label)}>
                                <p className="text-xs text-muted-foreground">{label}</p>
                                <p className="mt-2 font-semibold tabular-nums">
                                    {kg(Number(value))} kg
                                </p>
                            </div>
                        ))}
                    </>
                ) : (
                    <div>
                        <p className="text-xs text-muted-foreground">Berat terjual (manual)</p>
                        <p className="mt-2 font-semibold">{kg(record.manual_sold_grams!)} kg</p>
                    </div>
                )}
                <div>
                    <p className="text-xs text-muted-foreground">Potongan</p>
                    <p className="mt-2 font-semibold">{rupiah(record.deduction_idr)}</p>
                    <p className="text-sm text-muted-foreground">{record.deduction_reason}</p>
                </div>
                <div>
                    <p className="text-xs text-muted-foreground">
                        Nilai penjualan setelah potongan
                    </p>
                    <p className="mt-2 text-xl font-semibold tabular-nums">
                        {rupiah(current.revenue_idr)}
                    </p>
                </div>
                {record.status === 'posted' && (
                    <div>
                        <p className="text-xs text-muted-foreground">Uang masuk Kas Usaha</p>
                        <p className="mt-2 font-semibold">{rupiah(record.received_idr)}</p>
                        <p className="text-sm text-muted-foreground">
                            <PaymentStatusBadge status={record.payment_status ?? 'unpaid'} />
                            {record.receivable_idr > 0
                                ? ` · Belum masuk ${rupiah(record.receivable_idr)}`
                                : ' · Tercatat di saldo'}
                        </p>
                        {canReceive && record.receivable_idr > 0 && (
                            <Button asChild className="mt-3" size="sm">
                                <Link
                                    href={route('sale-receipts.create', {
                                        sale_kind: kind,
                                        sale_id: record.id,
                                    })}
                                >
                                    Catat penerimaan
                                </Link>
                            </Button>
                        )}
                    </div>
                )}
            </div>
            {cashReceipts.length > 0 && (
                <section className="mb-6 rounded-xl border bg-card p-6">
                    <h2 className="font-semibold">Riwayat uang masuk penjualan</h2>
                    <div className="mt-4 divide-y">
                        {cashReceipts.map((receipt) => (
                            <div
                                key={receipt.id}
                                className="flex flex-wrap items-center justify-between gap-3 py-4 first:pt-0 last:pb-0"
                            >
                                <div>
                                    <p className="text-sm font-medium">
                                        {receipt.account_name} ·{' '}
                                        {receipt.method === 'cash' ? 'Tunai' : 'Transfer bank'}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {receipt.number} · {dateTime(receipt.occurred_at)}
                                        {receipt.reference ? ` · ${receipt.reference}` : ''}
                                    </p>
                                </div>
                                <div className="flex items-center gap-3">
                                    <span className="font-semibold tabular-nums">
                                        {rupiah(receipt.amount_idr)}
                                    </span>
                                    <Badge
                                        variant={
                                            receipt.status === 'posted' ? 'secondary' : 'outline'
                                        }
                                    >
                                        {receipt.status === 'posted' ? 'Masuk kas' : 'Dibalik'}
                                    </Badge>
                                </div>
                            </div>
                        ))}
                    </div>
                </section>
            )}
            {manager && !direct && (
                <CostSummary
                    revenue={current.revenue_idr}
                    purchase={current.purchase_cost_released_idr}
                    loading={current.loading_cost_released_idr}
                    status={current.cost_status}
                />
            )}
            {!direct && kind === 'factory' && Number(record.sorted_out_grams) > 0 && (
                <div className="mt-6 rounded-xl border bg-card p-6">
                    <h2 className="font-semibold">Retur pabrik</h2>
                    <p className="mt-2 text-sm">
                        {kg(record.sorted_out_grams!)} kg{' '}
                        {record.status === 'draft'
                            ? 'akan masuk'
                            : record.status === 'reversed'
                              ? 'telah dibalik dari'
                              : 'tercatat sebagai'}{' '}
                        stok sortir perjalanan ini.
                    </p>
                    {manager && preview && (
                        <p className="mt-2 text-sm text-muted-foreground">
                            Biaya beli dibawa:{' '}
                            {preview.cost_status === 'available'
                                ? rupiah(preview.purchase_cost_carried_idr)
                                : 'Belum lengkap'}{' '}
                            · Biaya muat dibawa:{' '}
                            {preview.cost_status === 'available'
                                ? rupiah(preview.loading_cost_carried_idr)
                                : 'Belum lengkap'}
                        </p>
                    )}
                </div>
            )}
            {!direct && kind === 'sort' && (preview?.items ?? record.items)?.length ? (
                <div className="mt-6 overflow-x-auto rounded-xl border bg-card">
                    <table className="w-full text-left text-sm">
                        <caption className="p-4 text-left font-semibold">Alokasi stok FIFO</caption>
                        <thead>
                            <tr className="border-y bg-muted/40">
                                <th className="p-4">Lot</th>
                                <th className="p-4">Berat</th>
                                <th className="p-4">Nilai jual</th>
                                {manager && <th className="p-4">Biaya beli / muat</th>}
                            </tr>
                        </thead>
                        <tbody>
                            {(preview?.items ?? record.items)!.map((item) => (
                                <tr className="border-b last:border-0" key={item.lot_id}>
                                    <td className="p-4">Lot #{item.lot_id} (arsip)</td>
                                    <td className="whitespace-nowrap p-4">
                                        {kg(item.weight_grams)} kg
                                    </td>
                                    <td className="p-4">{rupiah(item.gross_revenue_idr)}</td>
                                    {manager && !direct && (
                                        <td className="whitespace-nowrap p-4">
                                            {rupiah(item.purchase_cost_released_idr)} /{' '}
                                            {rupiah(item.loading_cost_released_idr)}
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            ) : null}
            {record.notes && (
                <p className="mt-6 whitespace-pre-wrap rounded-xl border bg-card p-6 text-sm">
                    {record.notes}
                </p>
            )}
            {record.replaces_sale_id && (
                <p className="mt-4 text-sm">
                    Pengganti{' '}
                    <Link
                        className="text-primary hover:underline"
                        href={route(kind + '-sales.show', { sale: record.replaces_sale_id })}
                    >
                        nota #{record.replaces_sale_id}
                    </Link>{' '}
                    · {record.correction_reason}
                </p>
            )}
        </>
    );
}

import ApplicationLogo from '@/components/ApplicationLogo';
import { rupiah } from '@/lib/currency';
import { dateTime } from '@/lib/dates';
import type { FinanceDocument, FinanceKind } from '@/types/finance';
import { Link, usePage } from '@inertiajs/react';
import { Check, RotateCcw } from 'lucide-react';

interface VoucherDocumentProps {
    kind: FinanceKind;
    record: FinanceDocument;
    party: string;
    label: string;
    source: { kind: string; id: number; number: string } | null;
    allocations: {
        weighing_id: number;
        number: string;
        business_date: string;
        amount_idr: number;
    }[];
}

export default function VoucherDocument({
    kind,
    record,
    party,
    label,
    source,
    allocations,
}: VoucherDocumentProps) {
    const { business } = usePage().props;
    const reversed = record.status === 'reversed';
    return (
        <article
            className="finance-voucher overflow-hidden rounded-2xl border border-primary/10 bg-card p-5 sm:p-8 lg:p-10"
            aria-label="Dokumen voucher"
        >
            <div className="voucher-brand flex items-start justify-between gap-5 border-b border-primary/15 pb-6">
                <div className="flex min-w-0 items-center gap-3">
                    <ApplicationLogo className="size-11 shrink-0 text-primary" color="#164b37" />
                    <div className="min-w-0">
                        <p className="break-words text-lg font-semibold tracking-tight">
                            {business.name}
                        </p>
                        <p className="mt-1 text-[11px] text-muted-foreground">
                            Pengelolaan usaha sawit
                        </p>
                    </div>
                </div>
                <span className="hidden shrink-0 text-right text-xs text-muted-foreground sm:block">
                    Dokumen keuangan
                    <br />
                    <span className="mt-1 block font-medium text-primary">
                        {kind === 'receipt' ? 'Bukti penerimaan' : 'Bukti pembayaran'}
                    </span>
                </span>
            </div>
            <div className="voucher-heading my-6 flex flex-wrap items-start justify-between gap-4">
                <div className="min-w-0">
                    <p className="text-[10px] font-medium tracking-wider text-muted-foreground">
                        VOUCHER TRANSAKSI
                    </p>
                    <h2 className="report-heading mt-2 break-words text-2xl font-semibold sm:text-3xl">
                        {label}
                    </h2>
                    <p className="mt-2 break-words text-sm font-medium text-primary">
                        {record.number}
                    </p>
                </div>
                <span
                    className={`voucher-status inline-flex items-center gap-1.5 rounded-lg border px-3 py-2 text-xs font-semibold ${reversed ? 'voucher-status-reversed border-destructive/20 bg-destructive/5 text-destructive' : 'border-primary/15 bg-secondary/70 text-primary'}`}
                >
                    {reversed ? (
                        <RotateCcw className="size-3.5" aria-hidden="true" />
                    ) : (
                        <Check className="size-3.5" aria-hidden="true" />
                    )}
                    {reversed ? 'Dibalik' : 'Sah'}
                </span>
            </div>
            <div className="voucher-total mb-7 flex flex-col justify-between gap-2 rounded-xl border border-primary/10 bg-secondary/60 p-5 sm:flex-row sm:items-center">
                <div>
                    <p className="text-xs font-medium text-primary">Total voucher</p>
                    <p className="mt-1 text-[11px] text-muted-foreground">
                        {reversed
                            ? 'Nominal transaksi asli yang telah dibalik'
                            : kind === 'receipt'
                              ? 'Uang yang diterima ke kas / bank'
                              : 'Uang yang dibayarkan dari kas / bank'}
                    </p>
                </div>
                <strong className="break-words text-2xl font-semibold tabular-nums text-primary sm:text-3xl">
                    {rupiah(record.amount_idr)}
                </strong>
            </div>
            <dl className="voucher-meta grid grid-cols-1 gap-x-8 gap-y-5 text-sm sm:grid-cols-2">
                {[
                    ['Penerima / pihak terkait', party],
                    ['Tanggal & waktu', `${dateTime(record.occurred_at)} WIB`],
                    ['Kas', record.cash_account.name],
                    ['Metode', record.method === 'cash' ? 'Tunai' : 'Transfer bank'],
                    ['Petugas', record.creator.name],
                    ['Referensi', record.reference ?? '—'],
                ].map(([title, value]) => (
                    <div key={title} className="min-w-0">
                        <dt className="text-[11px] text-muted-foreground">{title}</dt>
                        <dd className="mt-1.5 break-words font-medium leading-relaxed">{value}</dd>
                    </div>
                ))}
            </dl>
            {(record.period_start || record.description || source) && (
                <div className="voucher-context mt-6 space-y-3 border-t border-primary/10 pt-5 text-sm leading-relaxed">
                    {record.period_start && (
                        <p>
                            <span className="text-muted-foreground">Periode pekerjaan: </span>
                            {record.period_start} sampai {record.period_end}
                        </p>
                    )}
                    {record.description && (
                        <p className="whitespace-pre-wrap break-words">
                            <span className="text-muted-foreground">Deskripsi: </span>
                            {record.description}
                        </p>
                    )}
                    {source && (
                        <p>
                            <span className="text-muted-foreground">Dokumen asal: </span>
                            {source.kind === 'trip' ? (
                                <span>{source.number} (referensi arsip)</span>
                            ) : (
                                <Link
                                    className="font-medium text-primary underline underline-offset-4"
                                    href={route(source.kind + '-sales.show', source.id)}
                                >
                                    {source.number}
                                </Link>
                            )}
                        </p>
                    )}
                </div>
            )}
            {!!allocations.length && (
                <section
                    className="voucher-allocations mt-7"
                    aria-labelledby="voucher-allocation-title"
                >
                    <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <h3 id="voucher-allocation-title" className="text-sm font-semibold">
                            Rincian pembayaran
                        </h3>
                        <span className="text-[11px] text-muted-foreground">
                            {allocations.length} tiket timbang
                        </span>
                    </div>
                    <div className="voucher-table-wrap overflow-x-auto rounded-xl border border-primary/10">
                        <table className="voucher-table w-full text-left text-sm">
                            <thead className="border-b border-primary/10 bg-secondary/50">
                                <tr>
                                    {['Tiket timbang', 'Tanggal pekerjaan', 'Dibayar'].map(
                                        (title, index) => (
                                            <th
                                                key={title}
                                                scope="col"
                                                className={`px-4 py-3 text-[11px] font-medium text-muted-foreground ${index === 2 ? 'text-right' : ''}`}
                                            >
                                                {title}
                                            </th>
                                        ),
                                    )}
                                </tr>
                            </thead>
                            <tbody>
                                {allocations.map((allocation, index) => (
                                    <tr
                                        key={index}
                                        className="border-b border-primary/10 last:border-0"
                                    >
                                        <td className="px-4 py-3">
                                            <Link
                                                className="font-medium text-primary underline-offset-4 hover:underline"
                                                href={route(
                                                    'weighings.show',
                                                    allocation.weighing_id,
                                                )}
                                            >
                                                {allocation.number}
                                            </Link>
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-xs text-muted-foreground">
                                            {allocation.business_date}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right text-xs font-medium tabular-nums">
                                            {rupiah(allocation.amount_idr)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot className="border-t border-primary/15 bg-secondary/30">
                                <tr>
                                    <td colSpan={2} className="px-4 py-3 text-xs font-semibold">
                                        Total alokasi
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3 text-right text-xs font-semibold tabular-nums">
                                        {rupiah(
                                            allocations.reduce(
                                                (total, row) => total + row.amount_idr,
                                                0,
                                            ),
                                        )}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>
            )}
            {(record.notes || record.backdate_reason) && (
                <div className="voucher-annotations mt-6 space-y-3 border-t border-primary/10 pt-5 text-xs leading-relaxed text-muted-foreground">
                    {record.notes && (
                        <p className="whitespace-pre-wrap break-words">
                            <strong className="font-medium text-foreground">Catatan: </strong>
                            {record.notes}
                        </p>
                    )}
                    {record.backdate_reason && (
                        <p className="break-words">
                            <strong className="font-medium text-foreground">
                                Alasan tanggal sebelumnya:{' '}
                            </strong>
                            {record.backdate_reason}
                        </p>
                    )}
                </div>
            )}
            {reversed && (
                <section
                    className="voucher-reversal mt-6 rounded-xl border border-destructive/20 bg-destructive/5 p-5 text-xs leading-relaxed"
                    aria-label="Pembalikan voucher"
                >
                    <h3 className="font-semibold text-destructive">Voucher telah dibalik</h3>
                    <p className="mt-2">
                        Dibalik {record.reversed_at ? dateTime(record.reversed_at) : '—'} WIB.
                    </p>
                    <p className="mt-2 break-words">Alasan: {record.reversal_reason}</p>
                    <p className="mt-2 break-words">Pengembalian uang: {record.return_reference}</p>
                    <p className="mt-3 font-medium">
                        {kind === 'expense'
                            ? 'Biaya dan mutasi kas dibalik. Histori asli tetap tersimpan.'
                            : 'Voucher ini tidak mengurangi hak atau piutang aktif.'}
                    </p>
                </section>
            )}
            <div className="voucher-signatures mt-10 hidden grid-cols-2 gap-10 print:grid">
                <div>
                    <p>Petugas</p>
                    <div className="signature-line"></div>
                    <p>{record.creator.name}</p>
                </div>
                <div>
                    <p>Penerima / pihak terkait</p>
                    <div className="signature-line"></div>
                    <p>{party}</p>
                </div>
            </div>
            <div className="voucher-footer mt-8 flex flex-wrap justify-between gap-2 border-t border-primary/10 pt-4 text-[10px] leading-relaxed text-muted-foreground">
                <span>Dokumen transaksi {business.name}</span>
                <span className="font-medium">
                    {record.number} · {reversed ? 'Dibalik' : 'Sah'}
                </span>
            </div>
        </article>
    );
}

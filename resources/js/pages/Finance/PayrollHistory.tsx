import { Button } from '@/components/ui/button';
import PaymentVoucher from '@/features/finance/components/payroll-history/PaymentVoucher';
import PayrollHistoryFilters from '@/features/finance/components/payroll-history/PayrollHistoryFilters';
import SettledJob from '@/features/finance/components/payroll-history/SettledJob';
import PayrollPagination from '@/features/finance/components/PayrollPagination';
import usePayrollHistory from '@/features/finance/hooks/use-payroll-history';
import AppLayout from '@/layouts/AppLayout';
import { rupiah } from '@/lib/currency';
import type { PayrollHistoryProps } from '@/types/payroll-history';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, History } from 'lucide-react';

export default function PayrollHistory(props: PayrollHistoryProps) {
    const model = usePayrollHistory(props);
    const {
        view,
        worker,
        rows,
        documents,
        summary,
        data,
        busy,
        settled,
        records,
        filtered,
        visit,
    } = model;
    return (
        <AppLayout
            header={
                <div className="payroll">
                    <Link href={route('payroll.index')} className="payroll-link mb-4">
                        <ArrowLeft className="size-3.5" aria-hidden="true" />
                        Gaji karyawan muat
                    </Link>
                    <div className="flex items-start gap-4">
                        <span className="hidden size-12 shrink-0 items-center justify-center rounded-2xl border border-primary/10 bg-secondary text-primary sm:flex">
                            <History className="size-6" aria-hidden="true" />
                        </span>
                        <div className="min-w-0">
                            <p className="mb-2 text-[11px] font-medium text-muted-foreground">
                                Keuangan / Upah muat
                            </p>
                            <h1 className="payroll-heading text-2xl font-semibold sm:text-3xl">
                                Riwayat penggajian karyawan
                            </h1>
                            <p className="mt-2 max-w-xl text-sm leading-relaxed text-muted-foreground">
                                Pekerjaan yang sudah lunas dan seluruh voucher pembayaran upah
                                karyawan muat.
                            </p>
                        </div>
                    </div>
                </div>
            }
        >
            <Head title="Riwayat Penggajian Karyawan" />
            <div className="payroll space-y-6">
                <section
                    aria-label="Ringkasan riwayat penggajian"
                    className="grid gap-3 sm:grid-cols-3"
                >
                    {[
                        {
                            label: settled ? 'Pekerjaan lunas' : 'Voucher pembayaran',
                            value: summary.record_count.toLocaleString('id-ID'),
                            note: 'Sesuai filter yang diterapkan',
                        },
                        {
                            label: 'Karyawan',
                            value: summary.worker_count.toLocaleString('id-ID'),
                            note: 'Karyawan dalam riwayat ini',
                        },
                        {
                            label: settled ? 'Total hak upah lunas' : 'Total pembayaran sah',
                            value: rupiah(summary.total_idr),
                            note: settled
                                ? 'Hak upah dari pekerjaan lunas'
                                : `Voucher dibalik: ${rupiah(summary.reversed_idr)}`,
                        },
                    ].map(({ label, value, note }) => (
                        <div key={label} className="payroll-panel px-5 py-4">
                            <p className="text-[11px] text-muted-foreground">{label}</p>
                            <p className="payroll-heading mt-2 break-words text-xl font-semibold tabular-nums">
                                {value}
                            </p>
                            <p className="mt-2 text-[11px] leading-relaxed text-muted-foreground">
                                {note}
                            </p>
                        </div>
                    ))}
                </section>
                <section
                    className="payroll-panel overflow-hidden"
                    aria-labelledby="payroll-history-title"
                >
                    <div className="px-5 pt-5 sm:px-6">
                        <h2 id="payroll-history-title" className="text-sm font-semibold">
                            {settled ? 'Pekerjaan lunas' : 'Voucher pembayaran'}
                        </h2>
                        <p className="mt-1.5 text-[11px] leading-relaxed text-muted-foreground">
                            {settled
                                ? 'Hak upah yang sudah dibayar penuh. Periode mengikuti tanggal pekerjaan.'
                                : 'Termasuk pembayaran sebagian dan voucher yang dibalik. Periode mengikuti tanggal pembayaran.'}
                        </p>
                        <nav
                            className="my-4 flex flex-wrap gap-1"
                            aria-label="Jenis riwayat penggajian"
                        >
                            {(
                                [
                                    { value: 'settled', label: 'Pekerjaan lunas' },
                                    { value: 'payments', label: 'Voucher pembayaran' },
                                ] as const
                            ).map(({ value, label }) => (
                                <Button
                                    key={value}
                                    type="button"
                                    size="sm"
                                    variant={view === value ? 'secondary' : 'ghost'}
                                    className="rounded-lg"
                                    aria-pressed={view === value}
                                    disabled={busy}
                                    onClick={() => visit({ ...data, status: '' }, value)}
                                >
                                    {label}
                                </Button>
                            ))}
                        </nav>
                    </div>
                    <PayrollHistoryFilters model={model} />
                    <div className="divide-y">
                        {settled
                            ? rows?.data.map((row) => <SettledJob key={row.id} row={row} />)
                            : documents?.data.map((document) => (
                                  <PaymentVoucher key={document.id} document={document} />
                              ))}
                    </div>
                    {!records?.data.length && (
                        <div className="flex flex-col items-center px-6 py-14 text-center">
                            <History className="mb-4 size-8 text-primary/40" aria-hidden="true" />
                            <h3 className="text-sm font-semibold">
                                {settled
                                    ? 'Belum ada pekerjaan lunas pada filter ini'
                                    : 'Belum ada voucher pembayaran pada filter ini'}
                            </h3>
                            <p className="mt-2 max-w-sm text-sm leading-relaxed text-muted-foreground">
                                {filtered || worker
                                    ? 'Sesuaikan pencarian, periode, atau karyawan untuk melihat riwayat lainnya.'
                                    : settled
                                      ? 'Pekerjaan akan muncul di sini setelah seluruh hak upah karyawan dibayar.'
                                      : 'Pembayaran upah yang dicatat akan muncul di sini.'}
                            </p>
                        </div>
                    )}
                    {records && (
                        <PayrollPagination
                            records={records}
                            label={settled ? 'pekerjaan lunas' : 'voucher'}
                        />
                    )}
                </section>
            </div>
        </AppLayout>
    );
}

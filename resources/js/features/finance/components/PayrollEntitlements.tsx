import { Link } from '@inertiajs/react';
import { ArrowUpRight, Check, ClipboardList, Scale } from 'lucide-react';
import { rupiah } from '@/lib/currency';
import { weight } from '@/lib/weight';
import { dateTime } from '@/lib/dates';
import type { Entitlement, PaginatedData, PayeeTotals } from '@/types/finance';
import PaymentStatusBadge from './PaymentStatusBadge';
import PayrollPagination from './PayrollPagination';

export default function PayrollEntitlements({
    rows,
    totals,
}: {
    rows: PaginatedData<Entitlement>;
    totals: PayeeTotals;
}) {
    return (
        <section
            className="payroll-panel min-w-0 overflow-hidden"
            aria-labelledby="payroll-entitlements-title"
        >
            <div className="p-5 sm:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2
                            id="payroll-entitlements-title"
                            className="flex items-center gap-2 text-sm font-semibold"
                        >
                            <ClipboardList className="size-4 text-primary" aria-hidden="true" />
                            Timbangan belum lunas
                        </h2>
                        <p className="mt-2 text-xs leading-relaxed text-muted-foreground">
                            {rows.total} timbangan masih memiliki sisa upah pada periode ini.
                        </p>
                    </div>
                    <span className="inline-flex items-center gap-1.5 rounded-md bg-secondary px-2.5 py-1.5 text-[10px] font-medium text-primary">
                        <Check className="size-3" aria-hidden="true" />
                        Otomatis tercakup
                    </span>
                </div>
                <dl className="mt-6 grid gap-5 border-t pt-5 sm:grid-cols-2">
                    <div>
                        <dt className="text-xs text-muted-foreground">Sisa upah periode</dt>
                        <dd className="payroll-heading mt-2 text-[clamp(1.5rem,2.5vw,2rem)] font-semibold tabular-nums text-primary">
                            {rupiah(totals.remaining_idr)}
                        </dd>
                        <p className="mt-2 text-[11px] text-muted-foreground">
                            {totals.outstanding_count} timbangan belum lunas
                        </p>
                    </div>
                    <div className="space-y-3 text-xs sm:pt-1">
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted-foreground">Total hak upah</dt>
                            <dd className="font-semibold tabular-nums">
                                {rupiah(totals.total_idr)}
                            </dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted-foreground">Sudah dibayar</dt>
                            <dd className="font-semibold tabular-nums">
                                {rupiah(totals.paid_idr)}
                            </dd>
                        </div>
                    </div>
                </dl>
            </div>
            <p className="border-y bg-secondary/35 px-5 py-3 text-xs leading-relaxed text-primary sm:px-6">
                Seluruh sisa upah periode ini otomatis menjadi dasar pembayaran, termasuk timbangan
                di halaman berikutnya. Nominal sebagian dibayar dari timbangan tertua.
            </p>
            <div
                className="max-h-[34rem] divide-y overflow-y-auto focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-ring"
                role="region"
                aria-label="Rincian timbangan dalam periode"
                tabIndex={rows.data.length ? 0 : undefined}
            >
                {rows.data.map((row) => (
                    <article key={row.id} className="p-5 sm:p-6">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3>
                                    <Link
                                        href={route('weighings.show', row.weighing_id)}
                                        className="payroll-link text-sm"
                                    >
                                        {row.number}
                                        <ArrowUpRight className="size-3.5" aria-hidden="true" />
                                    </Link>
                                </h3>
                                <p className="mt-1.5 text-[11px] text-muted-foreground">
                                    {dateTime(row.weighed_at)}
                                </p>
                            </div>
                            <PaymentStatusBadge
                                status={
                                    row.remaining_idr === 0
                                        ? 'paid'
                                        : row.paid_idr > 0
                                          ? 'partial'
                                          : 'unpaid'
                                }
                            />
                        </div>
                        <div className="mt-4 flex items-start gap-2 text-xs leading-relaxed text-muted-foreground">
                            <Scale className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                            <p>
                                Dasar {weight(row.basis_grams)} × {rupiah(row.rate_idr)}/kg · Dibagi{' '}
                                {row.member_count ?? '—'} anggota
                            </p>
                        </div>
                        <dl className="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3">
                            {[
                                { label: 'Hak karyawan', value: row.total_idr },
                                { label: 'Sudah dibayar', value: row.paid_idr },
                                { label: 'Sisa upah', value: row.remaining_idr },
                            ].map(({ label, value }) => (
                                <div key={label}>
                                    <dt className="text-[11px] text-muted-foreground">{label}</dt>
                                    <dd
                                        className={
                                            'payroll-heading mt-1.5 text-sm font-semibold tabular-nums ' +
                                            (label === 'Sisa upah' ? 'text-primary' : '')
                                        }
                                    >
                                        {rupiah(value)}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </article>
                ))}
            </div>
            {!rows.data.length && (
                <div className="px-6 py-12 text-center">
                    <ClipboardList
                        className="mx-auto mb-3 size-7 text-primary/40"
                        aria-hidden="true"
                    />
                    <h3 className="text-sm font-semibold">Tidak ada timbangan yang belum lunas</h3>
                    <p className="mt-2 text-xs leading-relaxed text-muted-foreground">
                        Pekerjaan yang sudah lunas tersedia di Riwayat Penggajian Karyawan.
                        Sesuaikan periode untuk memeriksa pekerjaan lainnya.
                    </p>
                </div>
            )}
            <PayrollPagination records={rows} label="timbangan" />
        </section>
    );
}

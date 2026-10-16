import { Badge } from '@/components/ui/badge';
import { rupiah } from '@/lib/currency';
import { dateTime } from '@/lib/dates';
import { weight } from '@/lib/weight';
import type { SettledPayrollRow } from '@/types/payroll-history';
import { Link } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import WorkerIdentity from './WorkerIdentity';

export default function SettledJob({ row }: { row: SettledPayrollRow }) {
    return (
        <article className="p-5 transition-colors hover:bg-secondary/15 sm:p-6">
            <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)_auto]">
                <WorkerIdentity worker={row.worker} />
                <div className="min-w-0">
                    <Link
                        href={route('weighings.show', row.weighing_id)}
                        className="payroll-link break-all text-sm"
                    >
                        {row.number}
                        <ArrowUpRight className="size-3.5 shrink-0" aria-hidden="true" />
                    </Link>
                    <p className="mt-1.5 text-[11px] text-muted-foreground">
                        {dateTime(row.weighed_at)}
                    </p>
                    <p className="mt-3 text-xs leading-relaxed text-muted-foreground">
                        Dasar {weight(row.basis_grams)} × {rupiah(row.rate_idr)}/kg
                        {' · '}Dibagi {row.member_count ?? '—'} anggota
                    </p>
                </div>
                <div className="flex items-center justify-between gap-4 lg:block lg:text-right">
                    <div>
                        <p className="text-[11px] text-muted-foreground">Hak upah karyawan</p>
                        <p className="payroll-heading mt-1.5 text-lg font-semibold tabular-nums">
                            {rupiah(row.total_idr)}
                        </p>
                    </div>
                    <Badge variant="secondary" className="rounded-md lg:mt-2">
                        Lunas
                    </Badge>
                </div>
            </div>
            <div className="mt-4 border-t pt-3">
                <Link
                    href={route('payroll.history', { view: 'payments', worker_id: row.worker.id })}
                    className="payroll-link text-xs"
                >
                    Lihat voucher pembayaran karyawan
                    <ArrowUpRight className="size-3.5" aria-hidden="true" />
                </Link>
            </div>
        </article>
    );
}

import { Badge } from '@/components/ui/badge';
import { dateLabel } from '@/features/finance/lib/payroll-history';
import { rupiah } from '@/lib/currency';
import { dateTime } from '@/lib/dates';
import type { PayrollHistoryDocument } from '@/types/payroll-history';
import { Link } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import WorkerIdentity from './WorkerIdentity';

export default function PaymentVoucher({ document }: { document: PayrollHistoryDocument }) {
    return (
        <article className="p-5 transition-colors hover:bg-secondary/15 sm:p-6">
            <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)_auto]">
                <WorkerIdentity worker={document.worker} />
                <div className="min-w-0">
                    <Link
                        href={route('finance.show', { kind: 'payroll', document: document.id })}
                        className="payroll-link break-all text-sm"
                    >
                        {document.number}
                        <ArrowUpRight className="size-3.5 shrink-0" aria-hidden="true" />
                    </Link>
                    <p className="mt-1.5 text-[11px] text-muted-foreground">
                        Dibayar {dateTime(document.occurred_at)}
                    </p>
                    <p className="mt-3 text-xs leading-relaxed text-muted-foreground">
                        Periode pekerjaan {dateLabel(document.period_start)} –{' '}
                        {dateLabel(document.period_end)}
                    </p>
                </div>
                <div className="flex items-center justify-between gap-4 lg:block lg:text-right">
                    <div>
                        <p className="text-[11px] text-muted-foreground">Nominal pembayaran</p>
                        <p className="payroll-heading mt-1.5 text-lg font-semibold tabular-nums">
                            {rupiah(document.amount_idr)}
                        </p>
                    </div>
                    <Badge
                        variant={document.status === 'posted' ? 'secondary' : 'outline'}
                        className="rounded-md lg:mt-2"
                    >
                        {document.status === 'posted' ? 'Sah' : 'Dibalik'}
                    </Badge>
                </div>
            </div>
            <p className="mt-4 border-t pt-3 text-[11px] leading-relaxed text-muted-foreground">
                Kas {document.cash_account.name}
                {document.creator ? ` · Dicatat oleh ${document.creator.name}` : ''}
            </p>
        </article>
    );
}

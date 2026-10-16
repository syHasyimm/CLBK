import { Coins, ReceiptText, Scale, Wallet } from 'lucide-react';
import { rupiah } from '@/lib/currency';
import { weight } from '@/lib/weight';

export interface WeighingListSummary {
    ticket_count: number;
    weight_grams: number;
    purchase_idr: number;
    loading_wage_idr: number;
}

export default function WeighingOverview({ summary }: { summary: WeighingListSummary }) {
    const metrics = [
        {
            label: 'Tiket sah',
            value: summary.ticket_count.toLocaleString('id-ID'),
            icon: ReceiptText,
        },
        { label: 'Total berat buah', value: weight(summary.weight_grams), icon: Scale },
        { label: 'Uang konsumen', value: rupiah(summary.purchase_idr), icon: Wallet },
        { label: 'Total upah muat', value: rupiah(summary.loading_wage_idr), icon: Coins },
    ];

    return (
        <section
            aria-labelledby="weighing-overview-heading"
            className="mb-8 overflow-hidden rounded-2xl border bg-card"
        >
            <div className="flex flex-wrap items-center justify-between gap-2 border-b bg-secondary/40 px-5 py-4 sm:px-6">
                <h2 id="weighing-overview-heading" className="text-sm font-semibold">
                    Ringkasan tiket sah
                </h2>
                <p className="text-xs text-muted-foreground">
                    Mengikuti pencarian, status, dan rentang tanggal.
                </p>
            </div>
            <dl className="grid gap-px bg-border sm:grid-cols-2 xl:grid-cols-4">
                {metrics.map(({ label, value, icon: Icon }) => (
                    <div
                        key={label}
                        className="flex min-w-0 items-start justify-between gap-3 bg-card px-5 py-5 sm:px-6"
                    >
                        <div className="min-w-0">
                            <dt className="text-xs text-muted-foreground">{label}</dt>
                            <dd className="mt-2 break-words text-xl font-semibold tracking-tight tabular-nums text-primary 2xl:text-2xl">
                                {value}
                            </dd>
                        </div>
                        <Icon
                            aria-hidden="true"
                            className="mt-0.5 size-4 shrink-0 text-primary/50"
                        />
                    </div>
                ))}
            </dl>
        </section>
    );
}

import { Link } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import { rupiah } from '@/lib/currency';
import { cn } from '@/lib/utils';

export default function NetProfitCard({
    amount,
    subtitle,
    explanation,
    href,
    terms,
}: {
    amount: number | null;
    subtitle: string;
    explanation: string;
    href: string;
    terms: { label: string; amount: number; deduction?: boolean }[];
}) {
    return (
        <section
            className="dashboard-panel overflow-hidden"
            aria-labelledby="dashboard-net-profit-title"
        >
            <div className="grid xl:grid-cols-[minmax(0,1fr)_minmax(0,1.7fr)]">
                <div className="border-b bg-secondary/60 p-5 sm:p-6 xl:border-r xl:border-b-0">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <h2
                            id="dashboard-net-profit-title"
                            className="text-sm font-semibold text-primary"
                        >
                            Keuntungan bersih
                        </h2>
                        {amount !== null && (
                            <span
                                className={cn(
                                    'rounded-md px-2 py-1 text-[10px] font-medium',
                                    amount < 0
                                        ? 'bg-destructive/10 text-destructive'
                                        : 'bg-primary/10 text-primary',
                                )}
                            >
                                {amount < 0 ? 'Rugi' : amount > 0 ? 'Untung' : 'Neto nol'}
                            </span>
                        )}
                    </div>
                    <p
                        className={cn(
                            'dashboard-heading mt-4 text-[clamp(1.75rem,3vw,2.25rem)] font-semibold tabular-nums',
                            amount !== null && amount < 0 ? 'text-destructive' : 'text-primary',
                        )}
                    >
                        {amount === null ? 'Belum lengkap' : rupiah(amount)}
                    </p>
                    <p className="mt-2 text-xs text-muted-foreground">{subtitle}</p>
                    <Link href={href} className="dashboard-text-link mt-5">
                        Lihat perhitungan
                        <ArrowUpRight className="size-3.5" aria-hidden="true" />
                    </Link>
                </div>
                <div className="flex flex-col justify-center p-5 sm:p-6">
                    <dl className="grid grid-cols-2 gap-x-6 gap-y-4">
                        {terms.map((term) => (
                            <div key={term.label}>
                                <dt className="text-xs text-muted-foreground">
                                    {term.deduction ? '− ' : ''}
                                    {term.label}
                                </dt>
                                <dd className="mt-1.5 text-sm font-semibold tabular-nums">
                                    {rupiah(term.amount)}
                                </dd>
                            </div>
                        ))}
                    </dl>
                    <p className="mt-5 border-t pt-4 text-[11px] leading-relaxed text-muted-foreground">
                        {explanation}
                    </p>
                </div>
            </div>
        </section>
    );
}

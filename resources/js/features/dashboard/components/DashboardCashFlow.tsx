import { Link } from '@inertiajs/react';
import { rupiah } from '@/lib/currency';
import { cn } from '@/lib/utils';
import type { PeriodProps } from '@/features/reports/components/PeriodOverview';
import { ArrowDownLeft, ArrowUpRight, Wallet } from 'lucide-react';
import type { CashFlow } from '@/types/reports';

export default function DashboardCashFlow({
    flow,
    filters,
}: {
    flow: CashFlow;
    filters: PeriodProps['filters'];
}) {
    return (
        <section className="dashboard-panel overflow-hidden" aria-labelledby="dashboard-cash-title">
            <div className="flex flex-wrap items-center justify-between gap-4 border-b border-border/60 p-5 sm:p-6">
                <div>
                    <h2 id="dashboard-cash-title" className="font-semibold tracking-tight">
                        Arus Kas Usaha
                    </h2>
                    <p className="mt-1.5 text-xs text-muted-foreground">
                        Saldo awal, uang bergerak, dan saldo akhir periode untuk seluruh usaha.
                    </p>
                </div>
                <Link
                    className="dashboard-text-link"
                    href={route('reports.index', {
                        view: 'cash',
                        from: filters.from,
                        to: filters.to,
                    })}
                >
                    Semua mutasi <ArrowUpRight className="size-3.5" aria-hidden="true" />
                </Link>
            </div>
            <div className="grid gap-5 border-b border-border/60 bg-background/50 p-5 sm:grid-cols-3 sm:p-6">
                {[
                    { label: 'Uang masuk', amount: flow.totals.incoming_idr, icon: ArrowDownLeft },
                    { label: 'Uang keluar', amount: flow.totals.outgoing_idr, icon: ArrowUpRight },
                    { label: 'Saldo akhir periode', amount: flow.totals.closing_idr, icon: Wallet },
                ].map(({ label, amount, icon: Icon }) => (
                    <div key={label}>
                        <p className="flex items-center gap-2 text-xs text-muted-foreground">
                            <Icon className="size-3.5 text-primary" aria-hidden="true" />
                            {label}
                        </p>
                        <p className="dashboard-heading mt-2 text-xl font-semibold tabular-nums">
                            {rupiah(amount)}
                        </p>
                    </div>
                ))}
            </div>
            <div className="overflow-x-auto">
                <table className="w-full whitespace-nowrap text-left text-xs">
                    <thead className="text-muted-foreground">
                        <tr>
                            {['Kas', 'Saldo awal', 'Masuk', 'Keluar', 'Saldo akhir'].map(
                                (label, index) => (
                                    <th
                                        key={label}
                                        scope="col"
                                        className={cn(
                                            'px-5 py-3.5 font-medium sm:px-6',
                                            index > 0 && 'text-right',
                                        )}
                                    >
                                        {label}
                                    </th>
                                ),
                            )}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-border/60">
                        {flow.accounts.map((account) => (
                            <tr key={account.id} className="transition-colors hover:bg-muted/30">
                                <td className="px-5 py-4 sm:px-6">
                                    <Link
                                        className="dashboard-text-link"
                                        href={route('reports.index', {
                                            view: 'cash',
                                            from: filters.from,
                                            to: filters.to,
                                            cash_account_id: account.id,
                                        })}
                                    >
                                        {account.name}
                                        <ArrowUpRight className="size-3" aria-hidden="true" />
                                    </Link>
                                    <p className="mt-1 text-[10px] text-muted-foreground">
                                        Kas Usaha
                                    </p>
                                </td>
                                {[
                                    account.opening_idr,
                                    account.incoming_idr,
                                    account.outgoing_idr,
                                    account.closing_idr,
                                ].map((amount, index) => (
                                    <td
                                        key={index}
                                        className={cn(
                                            'px-5 py-4 text-right tabular-nums sm:px-6',
                                            index === 3 && 'font-semibold text-primary',
                                        )}
                                    >
                                        {rupiah(amount)}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                    <tfoot className="border-t bg-muted/40 font-semibold">
                        <tr>
                            <td className="px-5 py-4 sm:px-6">Total kas usaha</td>
                            {[
                                flow.totals.opening_idr,
                                flow.totals.incoming_idr,
                                flow.totals.outgoing_idr,
                                flow.totals.closing_idr,
                            ].map((amount, index) => (
                                <td
                                    key={index}
                                    className="px-5 py-4 text-right tabular-nums sm:px-6"
                                >
                                    {rupiah(amount)}
                                </td>
                            ))}
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>
    );
}

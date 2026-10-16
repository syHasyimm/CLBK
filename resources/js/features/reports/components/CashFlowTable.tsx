import { Link } from '@inertiajs/react';
import { rupiah } from '@/lib/currency';
import type { CashFlow } from '@/types/reports';

export default function CashFlowTable({
    flow,
    from,
    to,
}: {
    flow: CashFlow;
    from: string;
    to: string;
}) {
    return (
        <section className="overflow-hidden rounded-xl border bg-card">
            <div className="p-5">
                <h2 className="font-semibold">Arus kas usaha pada periode ini</h2>
                <p className="mt-2 text-sm text-muted-foreground">
                    Saldo awal sebelum {from}, mutasi {from} sampai {to}, dan saldo akhir periode.
                    Pembayaran serta setoran modal mengikuti tanggal uang bergerak.
                </p>
            </div>
            <div className="divide-y border-t md:hidden print:hidden">
                {flow.accounts.map((account) => (
                    <article key={account.id} className="min-w-0 p-5">
                        <h3>
                            <Link
                                href={route('reports.index', {
                                    view: 'cash',
                                    from,
                                    to,
                                    cash_account_id: account.id,
                                })}
                                className="inline-flex min-h-11 items-center text-sm font-semibold break-words text-primary hover:underline"
                            >
                                {account.name}
                            </Link>
                        </h3>
                        <FlowValues amounts={account} />
                    </article>
                ))}
                <div className="bg-secondary/40 p-5">
                    <p className="text-sm font-semibold">Total kas usaha</p>
                    <FlowValues amounts={flow.totals} />
                </div>
            </div>
            <div className="hidden overflow-x-auto md:block print:block">
                <table className="w-full whitespace-nowrap text-left text-sm">
                    <thead className="border-y bg-muted/40">
                        <tr>
                            {['Kas', 'Saldo awal', 'Masuk', 'Keluar', 'Saldo akhir'].map(
                                (label) => (
                                    <th key={label} scope="col" className="p-4">
                                        {label}
                                    </th>
                                ),
                            )}
                        </tr>
                    </thead>
                    <tbody>
                        {flow.accounts.map((account) => (
                            <tr key={account.id} className="border-b">
                                <td className="p-4">
                                    <Link
                                        className="font-medium text-primary hover:underline"
                                        href={route('reports.index', {
                                            view: 'cash',
                                            from,
                                            to,
                                            cash_account_id: account.id,
                                        })}
                                    >
                                        {account.name}
                                    </Link>
                                </td>
                                {[
                                    account.opening_idr,
                                    account.incoming_idr,
                                    account.outgoing_idr,
                                    account.closing_idr,
                                ].map((value, index) => (
                                    <td key={index} className="p-4 tabular-nums">
                                        {rupiah(value)}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                    <tfoot className="font-semibold">
                        <tr>
                            <td className="p-4">Total kas usaha</td>
                            {[
                                flow.totals.opening_idr,
                                flow.totals.incoming_idr,
                                flow.totals.outgoing_idr,
                                flow.totals.closing_idr,
                            ].map((value, index) => (
                                <td key={index} className="p-4 tabular-nums">
                                    {rupiah(value)}
                                </td>
                            ))}
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>
    );
}

function FlowValues({ amounts }: { amounts: CashFlow['totals'] }) {
    return (
        <dl className="mt-3 grid grid-cols-2 gap-4">
            {(['opening_idr', 'incoming_idr', 'outgoing_idr', 'closing_idr'] as const).map(
                (key, index) => (
                    <div key={key} className="min-w-0">
                        <dt className="text-xs text-muted-foreground">
                            {['Saldo awal', 'Masuk', 'Keluar', 'Saldo akhir'][index]}
                        </dt>
                        <dd className="mt-1 text-sm font-medium break-words tabular-nums [overflow-wrap:anywhere]">
                            {rupiah(amounts[key])}
                        </dd>
                    </div>
                ),
            )}
        </dl>
    );
}

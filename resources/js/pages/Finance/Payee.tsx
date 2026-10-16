import { useState, FormEvent } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import PageHeading from '@/components/common/PageHeading';
import CustomerSettlementForm from '@/features/finance/components/CustomerSettlementForm';
import FinanceHistoryTable from '@/features/finance/components/FinanceHistoryTable';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { rupiah } from '@/lib/currency';
import type { PayeePageProps } from '@/types/finance';
import PayrollPayee from '@/features/finance/components/PayrollPayee';
import { financeLabels, worklistRoute, payeeRoute } from '@/types/finance';
export default function Payee(props: PayeePageProps) {
    if (props.kind === 'payroll') return <PayrollPayee {...props} />;
    return <CustomerPayee {...props} />;
}
function CustomerPayee({
    kind,
    recipient,
    rows,
    totals,
    history,
    filters,
    localNow,
    customerSettlement,
}: PayeePageProps) {
    const [from, setFrom] = useState(filters.from ?? ''),
        [to, setTo] = useState(filters.to ?? '');
    function filter(e: FormEvent) {
        e.preventDefault();
        router.get(route(payeeRoute(kind), recipient.id), { from, to });
    }
    return (
        <AppLayout
            header={
                <PageHeading
                    title={recipient.name}
                    description={`${recipient.code} · ${financeLabels[kind]}${!recipient.is_active ? ' · Penerima nonaktif tetap dapat menerima sisa hak.' : ''}`}
                    action={
                        <Button variant="outline" asChild>
                            <Link href={route(worklistRoute(kind))}>Daftar penerima</Link>
                        </Button>
                    }
                />
            }
        >
            <Head title={`${recipient.name} · ${financeLabels[kind]}`} />
            <form onSubmit={filter} className="mb-6 grid items-end gap-3 sm:flex sm:flex-wrap">
                <div>
                    <Label htmlFor="period-from">Pekerjaan mulai</Label>
                    <Input
                        id="period-from"
                        className="mt-2 w-full sm:w-44"
                        type="date"
                        required={kind === 'payroll'}
                        value={from}
                        onChange={(e) => setFrom(e.target.value)}
                    />
                </div>
                <div>
                    <Label htmlFor="period-to">Pekerjaan sampai</Label>
                    <Input
                        id="period-to"
                        className="mt-2 w-full sm:w-44"
                        type="date"
                        required={kind === 'payroll'}
                        value={to}
                        onChange={(e) => setTo(e.target.value)}
                    />
                </div>
                <Button variant="outline">Terapkan periode</Button>
            </form>
            <dl className="mb-6 grid gap-4 sm:grid-cols-3">
                {[
                    ['Total hak', totals.total_idr],
                    ['Sudah dibayar', totals.paid_idr],
                    ['Sisa sesuai filter', totals.remaining_idr],
                ].map(([label, value]) => (
                    <div key={String(label)} className="rounded-xl border bg-card p-5">
                        <dt className="text-sm text-muted-foreground">{label}</dt>
                        <dd className="mt-2 text-xl font-semibold">{rupiah(Number(value))}</dd>
                    </div>
                ))}
            </dl>
            {(customerSettlement?.remaining_idr ?? 0) > 0 ? (
                <CustomerSettlementForm
                    key={`${recipient.id}:${customerSettlement!.remaining_idr}`}
                    recipient={recipient}
                    rows={rows.data}
                    localNow={localNow}
                    settlement={customerSettlement!}
                />
            ) : (
                <section className="rounded-xl border border-primary/15 bg-secondary/50 px-5 py-8 sm:px-6">
                    <h2 className="text-lg font-semibold text-primary">
                        Tidak ada timbangan yang belum lunas
                    </h2>
                    <p className="mt-2 max-w-xl text-sm leading-relaxed text-muted-foreground">
                        Seluruh hak konsumen sudah dibayar, atau belum ada timbangan. Riwayat
                        pembayaran tetap tersedia di bawah.
                    </p>
                </section>
            )}
            <div className="my-5 flex flex-wrap justify-between gap-3 text-sm">
                <p>
                    {rows.total} timbangan belum lunas · Halaman {rows.current_page} /{' '}
                    {rows.last_page}. Pelunasan mencakup seluruh tiket yang belum lunas, termasuk di
                    luar filter dan halaman ini.
                </p>
                <div className="flex gap-2">
                    {rows.prev_page_url && (
                        <Button size="sm" variant="outline" asChild>
                            <Link href={rows.prev_page_url}>Sebelumnya</Link>
                        </Button>
                    )}
                    {rows.next_page_url && (
                        <Button size="sm" variant="outline" asChild>
                            <Link href={rows.next_page_url}>Berikutnya</Link>
                        </Button>
                    )}
                </div>
            </div>
            <h2 className="mb-4 mt-8 text-lg font-semibold">Riwayat pembayaran penerima</h2>
            <FinanceHistoryTable kind={kind} documents={history} />
        </AppLayout>
    );
}

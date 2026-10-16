import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, HandCoins, History } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import { Badge } from '@/components/ui/badge';
import type { PayeePageProps } from '@/types/finance';
import PayrollFilters from './PayrollFilters';
import PayrollEntitlements from './PayrollEntitlements';
import PayrollPaymentForm from './PayrollPaymentForm';

export default function PayrollPayee({
    recipient,
    rows,
    totals,
    filters,
    localNow,
}: PayeePageProps) {
    return (
        <AppLayout
            header={
                <div className="payroll">
                    <Link
                        href={route('payroll.index', { from: filters.from, to: filters.to })}
                        className="payroll-link mb-4"
                    >
                        <ArrowLeft className="size-3.5" aria-hidden="true" />
                        Gaji karyawan muat
                    </Link>
                    <div className="flex items-start gap-4">
                        <span className="hidden size-12 shrink-0 items-center justify-center rounded-2xl border border-primary/10 bg-secondary text-primary sm:flex">
                            <HandCoins className="size-6" aria-hidden="true" />
                        </span>
                        <div className="min-w-0">
                            <p className="mb-2 text-[11px] font-medium text-muted-foreground">
                                Periksa & bayar · {recipient.code}
                            </p>
                            <h1 className="payroll-heading text-2xl font-semibold sm:text-3xl">
                                {recipient.name}
                            </h1>
                            <p className="mt-2 max-w-xl text-sm leading-relaxed text-muted-foreground">
                                Periksa timbangan yang belum lunas pada periode ini, lalu catat
                                pembayaran sisa upah.
                            </p>
                            <Link
                                href={route('payroll.history', { worker_id: recipient.id })}
                                className="payroll-link mt-4"
                            >
                                <History className="size-4" aria-hidden="true" />
                                Riwayat penggajian karyawan
                            </Link>
                            {!recipient.is_active && (
                                <Badge variant="outline" className="mt-3 rounded-md">
                                    Karyawan nonaktif · Sisa hak tetap dapat dibayar
                                </Badge>
                            )}
                        </div>
                    </div>
                </div>
            }
        >
            <Head title={`${recipient.name} · Gaji Karyawan Muat`} />
            <div className="payroll space-y-6">
                <section
                    className="payroll-panel overflow-hidden"
                    aria-labelledby="payroll-period-title"
                >
                    <h2
                        id="payroll-period-title"
                        className="px-5 pt-5 text-sm font-semibold sm:px-6"
                    >
                        Periode pekerjaan
                    </h2>
                    <PayrollFilters
                        url={route('payroll.payee', { recipient: recipient.id })}
                        filters={filters}
                        periodOnly
                    />
                </section>
                <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1.7fr)_minmax(0,1fr)]">
                    <PayrollEntitlements rows={rows} totals={totals} />
                    <aside
                        className="min-w-0 xl:sticky xl:top-6"
                        aria-label="Pembayaran upah karyawan"
                    >
                        <PayrollPaymentForm
                            key={`${recipient.id}:${filters.from}:${filters.to}:${totals.remaining_idr}:${totals.outstanding_count}`}
                            recipient={recipient}
                            totals={totals}
                            localNow={localNow}
                            period={{ from: filters.from ?? '', to: filters.to ?? '' }}
                        />
                    </aside>
                </div>
            </div>
        </AppLayout>
    );
}

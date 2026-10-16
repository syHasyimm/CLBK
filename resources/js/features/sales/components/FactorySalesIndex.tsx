import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowUpRight, Check, Factory, Plus, ReceiptText } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { rupiah } from '@/lib/currency';
import type { PaginatedData } from '@/types/operations';
import type { SaleRecord } from '@/types/sales';
import FactorySalesFilters from './FactorySalesFilters';
import FactorySalesTable from './FactorySalesTable';

export default function FactorySalesIndex({
    sales,
    filters,
    savedSale,
}: {
    sales: PaginatedData<SaleRecord>;
    filters: Record<string, string>;
    savedSale: { id: number; number: string; revenue_idr: number } | null;
}) {
    const { auth } = usePage().props;
    const canCreate = auth.user.role !== 'cashier';
    const filtered = Object.values(filters).some(Boolean);
    return (
        <AppLayout
            header={
                <div className="factory-sales flex flex-wrap items-center justify-between gap-5">
                    <div className="flex items-start gap-4">
                        <span className="hidden size-12 items-center justify-center rounded-2xl border border-primary/10 bg-secondary text-primary sm:flex">
                            <Factory className="size-6" aria-hidden="true" />
                        </span>
                        <div>
                            <p className="mb-2 text-[11px] font-medium text-muted-foreground">
                                Operasional / Penjualan
                            </p>
                            <h1 className="factory-heading text-2xl font-semibold sm:text-3xl">
                                Penjualan pabrik
                            </h1>
                            <p className="mt-2 max-w-xl text-sm leading-relaxed text-muted-foreground">
                                Kelola nota, berat bersih, dan penerimaan dari pabrik.
                            </p>
                        </div>
                    </div>
                    {canCreate && (
                        <Button asChild size="lg" className="rounded-lg">
                            <Link href={route('factory-sales.create')}>
                                <Plus className="size-4" aria-hidden="true" />
                                Catat penjualan
                            </Link>
                        </Button>
                    )}
                </div>
            }
        >
            <Head title="Penjualan Pabrik" />
            <div className="factory-sales space-y-5">
                {savedSale && (
                    <section
                        role="status"
                        className="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-primary/15 bg-secondary/60 p-5"
                    >
                        <div className="flex items-start gap-3">
                            <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <Check className="size-4" aria-hidden="true" />
                            </span>
                            <div>
                                <p className="text-sm font-semibold">Penjualan berhasil dicatat</p>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {savedSale.number} ·{' '}
                                    <span className="font-medium tabular-nums">
                                        {rupiah(savedSale.revenue_idr)}
                                    </span>
                                </p>
                            </div>
                        </div>
                        <Link
                            href={route('factory-sales.show', { sale: savedSale.id })}
                            className="factory-text-link"
                        >
                            Lihat nota <ArrowUpRight className="size-3.5" aria-hidden="true" />
                        </Link>
                    </section>
                )}
                <div className="flex flex-wrap items-center justify-between gap-3 text-xs text-muted-foreground">
                    <p className="flex items-center gap-2">
                        <ReceiptText className="size-3.5" aria-hidden="true" />
                        Uang masuk dicatat melalui Penerimaan Penjualan.
                    </p>
                    {auth.user.role !== 'driver' && (
                        <Link href={route('sale-receipts.index')} className="factory-text-link">
                            Buka penerimaan <ArrowUpRight className="size-3.5" aria-hidden="true" />
                        </Link>
                    )}
                </div>
                <section
                    className="factory-panel overflow-hidden"
                    aria-labelledby="factory-sales-list-title"
                >
                    <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-5 sm:px-6">
                        <div>
                            <h2 id="factory-sales-list-title" className="text-sm font-semibold">
                                Daftar nota penjualan
                            </h2>
                            <p className="mt-1 text-[11px] text-muted-foreground">
                                {filtered
                                    ? 'Hasil sesuai filter yang diterapkan'
                                    : 'Riwayat penjualan yang dapat Anda akses'}
                            </p>
                        </div>
                        <span className="rounded-md bg-muted px-2.5 py-1.5 text-[11px] font-medium tabular-nums">
                            {sales.total} nota
                        </span>
                    </div>
                    <FactorySalesFilters filters={filters} />
                    <FactorySalesTable sales={sales} filtered={filtered} canCreate={canCreate} />
                </section>
            </div>
        </AppLayout>
    );
}

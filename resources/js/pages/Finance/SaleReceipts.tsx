import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import PageHeading from '@/components/common/PageHeading';
import DataTable from '@/components/common/DataTable';
import OperationalFilters from '@/components/common/OperationalFilters';
import FinanceHistoryTable from '@/features/finance/components/FinanceHistoryTable';
import PaymentStatusBadge from '@/features/finance/components/PaymentStatusBadge';
import { Button } from '@/components/ui/button';
import { rupiah } from '@/lib/currency';
import { dateTime } from '@/lib/dates';
import type { SaleKind, SaleRecord } from '@/types/sales';
import type { FinanceDocument, PaginatedData } from '@/types/finance';

function ReceiptSales({ kind, records }: { kind: SaleKind; records: PaginatedData<SaleRecord> }) {
    const factory = kind === 'factory';
    return (
        <section>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h2 className="font-semibold">Nota penjualan {factory ? 'pabrik' : 'sortir'}</h2>
                <Button variant="outline" size="sm" asChild>
                    <Link href={route(kind + '-sales.index')}>Daftar penjualan</Link>
                </Button>
            </div>
            <DataTable
                records={records}
                columns={[
                    {
                        label: factory ? 'Nota / pabrik' : 'Nota / pembeli',
                        render: (sale) => (
                            <div>
                                <Link
                                    className="font-medium text-primary hover:underline"
                                    href={route(kind + '-sales.show', sale.id)}
                                >
                                    {sale.number}
                                </Link>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {factory
                                        ? sale.factory?.name
                                        : sale.buyer_name || 'Pembeli sortir'}
                                    {sale.invoice_number ? ` · ${sale.invoice_number}` : ''}
                                </p>
                            </div>
                        ),
                    },
                    { label: 'Waktu penjualan', render: (sale) => dateTime(sale.sold_at) },
                    { label: 'Nilai penjualan', render: (sale) => rupiah(sale.revenue_idr) },
                    { label: 'Sudah diterima', render: (sale) => rupiah(sale.received_idr) },
                    {
                        label: 'Sisa piutang',
                        render: (sale) => (
                            <span className="font-medium tabular-nums">
                                {rupiah(sale.receivable_idr)}
                            </span>
                        ),
                    },
                    {
                        label: 'Status',
                        render: (sale) => (
                            <PaymentStatusBadge status={sale.payment_status ?? 'unpaid'} />
                        ),
                    },
                    {
                        label: 'Tindakan',
                        render: (sale) =>
                            sale.receivable_idr > 0 ? (
                                <Button asChild size="sm">
                                    <Link
                                        href={route('sale-receipts.create', {
                                            sale_kind: kind,
                                            sale_id: sale.id,
                                        })}
                                    >
                                        Catat penerimaan
                                    </Link>
                                </Button>
                            ) : (
                                <Button asChild size="sm" variant="outline">
                                    <Link href={route(kind + '-sales.show', sale.id)}>
                                        Lihat rincian
                                    </Link>
                                </Button>
                            ),
                    },
                ]}
            />
        </section>
    );
}

export default function SaleReceipts({
    factorySales,
    sortSales,
    documents,
    filters,
}: {
    factorySales: PaginatedData<SaleRecord>;
    sortSales: PaginatedData<SaleRecord>;
    documents: PaginatedData<FinanceDocument>;
    filters: Record<string, string>;
}) {
    return (
        <AppLayout
            header={
                <PageHeading
                    title="Penerimaan Penjualan"
                    description="Catat pembayaran penjualan pabrik dan sortir, sebagian atau lunas. Uang yang diterima menambah saldo kas atau bank yang dipilih."
                />
            }
        >
            <Head title="Penerimaan Penjualan" />
            <OperationalFilters
                url={route('sale-receipts.index')}
                filters={filters}
                statuses={['unpaid', 'partial', 'paid']}
                dates
            />
            <div className="space-y-8">
                <ReceiptSales kind="factory" records={factorySales} />
                <ReceiptSales kind="sort" records={sortSales} />
                <section>
                    <h2 className="font-semibold">Riwayat penerimaan penjualan</h2>
                    <p className="mb-4 mt-2 text-sm text-muted-foreground">
                        Penerimaan pabrik, sortir, dan pembalikan tetap tersimpan, termasuk uang
                        masuk yang telah dicatat sebelumnya.
                    </p>
                    <FinanceHistoryTable kind="receipt" documents={documents} />
                </section>
            </div>
        </AppLayout>
    );
}

import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import PageHeading from '@/components/common/PageHeading';
import DataTable from '@/components/common/DataTable';
import OperationalFilters from '@/components/common/OperationalFilters';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { rupiah } from '@/lib/currency';
import { weight } from '@/lib/weight';
import PaymentStatusBadge from '@/features/finance/components/PaymentStatusBadge';
import FactorySalesIndex from '@/features/sales/components/FactorySalesIndex';
import { statusLabels, type PaginatedData } from '@/types/operations';
import { saleTitle, type SaleKind, type SaleRecord } from '@/types/sales';
export default function Index({
    kind,
    sales,
    filters,
    savedSale,
}: {
    kind: SaleKind;
    sales: PaginatedData<SaleRecord>;
    filters: Record<string, string>;
    savedSale: { id: number; number: string; revenue_idr: number } | null;
}) {
    const { auth } = usePage().props;
    if (kind === 'factory')
        return <FactorySalesIndex sales={sales} filters={filters} savedSale={savedSale} />;
    return (
        <AppLayout
            header={
                <PageHeading
                    title={saleTitle(kind)}
                    description="Nota mencatat piutang. Uang diterima dicatat melalui Penerimaan Penjualan."
                    action={
                        auth.user.role !== 'cashier' ? (
                            <Button asChild>
                                <Link href={route(kind + '-sales.create')}>Catat penjualan</Link>
                            </Button>
                        ) : undefined
                    }
                />
            }
        >
            <Head title={saleTitle(kind)} />
            {savedSale && (
                <div className="mb-6 rounded-xl border bg-secondary p-5" role="status">
                    <p className="font-medium">
                        {savedSale.number} tersimpan · {rupiah(savedSale.revenue_idr)}
                    </p>
                    <div className="mt-3 flex flex-wrap gap-3">
                        <Button asChild size="sm">
                            <Link href={route(kind + '-sales.create')}>Catat Penjualan Baru</Link>
                        </Button>
                        <Button asChild size="sm" variant="outline">
                            <Link href={route(kind + '-sales.show', { sale: savedSale.id })}>
                                Lihat Rincian
                            </Link>
                        </Button>
                    </div>
                </div>
            )}
            <OperationalFilters
                url={route(kind + '-sales.index')}
                filters={filters}
                statuses={['draft', 'posted', 'reversed']}
                dates
            />
            <DataTable
                records={sales}
                columns={[
                    {
                        label: 'Nomor',
                        render: (row) => (
                            <Link
                                className="font-medium text-primary hover:underline"
                                href={route(kind + '-sales.show', { sale: row.id })}
                            >
                                {row.number}
                            </Link>
                        ),
                    },
                    {
                        label: 'Mobil',
                        render: (row) => row.vehicle.plate_number,
                    },
                    {
                        label: 'Pembeli',
                        render: (row) => row.buyer_name || '—',
                    },
                    { label: 'Tanggal usaha', render: (row) => row.business_date },
                    {
                        label: 'Berat terjual',
                        render: (row) => weight(row.manual_sold_grams!),
                    },
                    { label: 'Nilai penjualan', render: (row) => rupiah(row.revenue_idr) },
                    {
                        label: 'Uang masuk',
                        render: (row) =>
                            row.payment_status ? (
                                <PaymentStatusBadge status={row.payment_status} />
                            ) : (
                                '—'
                            ),
                    },
                    {
                        label: 'Status',
                        render: (row) => (
                            <Badge variant="secondary">{statusLabels[row.status]}</Badge>
                        ),
                    },
                ]}
            />
        </AppLayout>
    );
}

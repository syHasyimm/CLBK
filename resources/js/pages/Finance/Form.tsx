import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import PageHeading from '@/components/common/PageHeading';
import FinanceForm from '@/features/finance/components/FinanceForm';
import { Button } from '@/components/ui/button';
import { financeLabels } from '@/types/finance';
import type { ReceiptSource } from '@/types/finance';
export default function Form({
    kind,
    localNow,
    sale,
}: {
    kind: 'receipt' | 'expense';
    localNow: string;
    sale: ReceiptSource | null;
}) {
    return (
        <AppLayout
            header={
                <PageHeading
                    title={financeLabels[kind]}
                    description={
                        kind === 'receipt'
                            ? 'Penerimaan uang penjualan pabrik dan sortir, sebagian atau lunas.'
                            : 'Catat biaya harian yang telah dibayar dari kas atau bank.'
                    }
                    action={
                        <Button variant="outline" asChild>
                            <Link
                                href={
                                    kind === 'receipt'
                                        ? route('sale-receipts.index')
                                        : route('finance.index', { kind })
                                }
                            >
                                Riwayat
                            </Link>
                        </Button>
                    }
                />
            }
        >
            <Head title={financeLabels[kind]} />
            <FinanceForm kind={kind} localNow={localNow} sale={sale} />
        </AppLayout>
    );
}

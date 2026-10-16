import PageHeading from '@/components/common/PageHeading';
import UnsavedChangesGuard from '@/components/common/UnsavedChangesGuard';
import { Button } from '@/components/ui/button';
import SaleActions from '@/features/sales/components/SaleActions';
import SaleDetails from '@/features/sales/components/SaleDetails';
import SaleProofs from '@/features/sales/components/SaleProofs';
import useSaleActions from '@/features/sales/hooks/use-sale-actions';
import type { SaleShowProps } from '@/features/sales/types/sale-show';
import AppLayout from '@/layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';

export default function Show(props: SaleShowProps) {
    const model = useSaleActions(props);
    const { kind, record, direct, reversal, proof } = model;
    return (
        <AppLayout
            header={
                <PageHeading
                    title={record.number}
                    description={
                        kind === 'factory'
                            ? 'Nota pabrik dengan berat aktual.'
                            : direct
                              ? 'Hasil penjualan sortir.'
                              : 'Rincian penjualan sortir historis.'
                    }
                    action={
                        <Button asChild variant="outline">
                            <Link href={route(kind + '-sales.index')}>Daftar penjualan</Link>
                        </Button>
                    }
                />
            }
        >
            <Head title={record.number} />
            <UnsavedChangesGuard dirty={!!reversal.data.reason || !!proof.data.proof} />
            <SaleDetails model={model} />
            <SaleActions model={model} />
            <SaleProofs model={model} />
        </AppLayout>
    );
}

import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import PageHeading from '@/components/common/PageHeading';
import LegacySaleForm, { type SaleFormProps } from '@/features/sales/components/LegacySaleForm';
export default function LegacyForm(props: SaleFormProps) {
    return (
        <AppLayout
            header={
                <PageHeading
                    title="Edit draft penjualan lama"
                    description="Selesaikan dokumen historis dengan referensi yang tersimpan."
                />
            }
        >
            <Head title="Edit draft penjualan lama" />
            <LegacySaleForm {...props} />
        </AppLayout>
    );
}

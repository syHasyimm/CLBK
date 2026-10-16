import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import PageHeading from '@/components/common/PageHeading';
import LegacyWeighingForm, {
    LegacyWeighingFormProps,
} from '@/features/weighings/components/LegacyWeighingForm';

export default function LegacyForm(props: LegacyWeighingFormProps) {
    return (
        <AppLayout
            header={
                <PageHeading
                    title="Edit Draft Timbang Lama"
                    description="Selesaikan draft yang dibuat sebelum perubahan alur. Timbangan baru disimpan langsung melalui Timbang Sawit."
                />
            }
        >
            <Head title="Timbang Sawit" />
            <LegacyWeighingForm key={props.record!.id} {...props} />
        </AppLayout>
    );
}

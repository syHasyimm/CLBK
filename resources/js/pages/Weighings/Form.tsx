import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import PageHeading from '@/components/common/PageHeading';
import { Button } from '@/components/ui/button';
import WeighingForm, { WeighingFormProps } from '@/features/weighings/components/WeighingForm';

export default function Form(props: WeighingFormProps) {
    return (
        <AppLayout
            header={
                <PageHeading
                    title="Timbang Sawit"
                    description={
                        props.vehicleAssignment
                            ? 'Mobil terisi sesuai penugasan. Pilih konsumen, catat berat buah serta anggota muat, lalu simpan.'
                            : 'Pilih konsumen dan mobil, catat berat buah serta anggota muat, lalu simpan.'
                    }
                    action={
                        <Button asChild variant="outline" size="lg" className="rounded-lg">
                            <Link href={route('weighings.index')}>
                                <ArrowLeft aria-hidden="true" className="size-4" />
                                Daftar timbangan
                            </Link>
                        </Button>
                    }
                />
            }
        >
            <Head title="Timbang Sawit" />
            <WeighingForm key={props.replacement?.id ?? 'new'} {...props} />
        </AppLayout>
    );
}

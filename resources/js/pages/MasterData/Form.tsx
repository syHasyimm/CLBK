import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import PageHeading from '@/components/common/PageHeading';
import MasterDataForm, { MasterFormProps } from '@/features/master-data/components/MasterDataForm';

export default function Form(props: MasterFormProps) {
    const title = (props.record ? 'Ubah ' : 'Tambah ') + props.title.toLowerCase();
    return (
        <AppLayout
            header={
                <PageHeading
                    title={title}
                    description="Lengkapi informasi dan simpan data untuk digunakan dalam kegiatan usaha."
                />
            }
        >
            <Head title={title} />
            {props.resource === 'vehicles' && (
                <p className="mb-5 max-w-3xl rounded-lg border bg-secondary/40 p-4 text-sm leading-relaxed">
                    Supir default merupakan referensi data mobil. Tetapkan mobil untuk akun supir
                    melalui{' '}
                    <Link
                        href={route('users.index')}
                        className="font-medium text-primary underline underline-offset-4"
                    >
                        Kelola Pengguna
                    </Link>{' '}
                    agar digunakan otomatis pada timbang dan penjualan.
                </p>
            )}
            <MasterDataForm key={props.resource + '-' + (props.record?.id ?? 'new')} {...props} />
        </AppLayout>
    );
}

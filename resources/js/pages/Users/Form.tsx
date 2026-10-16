import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import PageHeading from '@/components/common/PageHeading';
import UserForm, { type UserFormProps } from '@/features/users/components/UserForm';

export default function Form({ record, roles, vehicles }: UserFormProps) {
    const title = record ? 'Ubah akun pengguna' : 'Tambah akun pengguna';
    return (
        <AppLayout
            header={
                <PageHeading
                    title={title}
                    description="Role menentukan fitur yang dapat diakses. Akun nonaktif tidak dapat masuk ke aplikasi."
                />
            }
        >
            <Head title={title} />
            <UserForm key={record?.id ?? 'new'} record={record} roles={roles} vehicles={vehicles} />
        </AppLayout>
    );
}

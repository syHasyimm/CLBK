import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import PageHeading from '@/components/common/PageHeading';
import RecordsTable from '@/components/common/RecordsTable';
import RecordActions from '@/components/common/RecordActions';
import ListFilters from '@/components/common/ListFilters';
import type { SelectOption } from '@/components/common/FormField';
import { Button } from '@/components/ui/button';
import { Plus } from 'lucide-react';
import {
    roleLabels,
    ListFilters as Filters,
    MasterRecord,
    PaginatedData,
} from '@/types/master-data';

export default function Index({
    records,
    filters,
    roles,
}: {
    records: PaginatedData<MasterRecord>;
    filters: Filters & { role: string };
    roles: SelectOption[];
}) {
    const displayRecords = {
        ...records,
        data: records.data.map((record) => ({
            ...record,
            role_label: roleLabels[String(record.role)],
        })),
    };
    return (
        <AppLayout
            header={
                <PageHeading
                    title="Akun Pengguna"
                    description="Atur akun dan hak akses manajer, supir, serta kasir."
                    action={
                        <Button asChild>
                            <Link href={route('users.create')}>
                                <Plus />
                                Tambah akun
                            </Link>
                        </Button>
                    }
                />
            }
        >
            <Head title="Akun Pengguna" />
            <ListFilters filters={filters} url={route('users.index')} roles={roles} />
            <RecordsTable
                records={displayRecords}
                columns={{
                    name: 'Nama',
                    email: 'Email',
                    role_label: 'Role',
                    assigned_vehicle: 'Mobil yang ditugaskan',
                }}
                emptyLabel="Daftar akun pengguna"
                actions={(record) => (
                    <RecordActions
                        record={record}
                        editUrl={route('users.edit', record.id)}
                        statusUrl={route('users.status', record.id)}
                    />
                )}
            />
            <p className="mt-4 text-xs leading-relaxed text-muted-foreground">
                Minimal satu manajer harus tetap aktif. Anggota muat dikelola sebagai data pekerja
                dan tidak memerlukan akun login.
            </p>
        </AppLayout>
    );
}

import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import PageHeading from '@/components/common/PageHeading';
import RecordsTable from '@/components/common/RecordsTable';
import RecordActions from '@/components/common/RecordActions';
import ListFilters from '@/components/common/ListFilters';
import { Button } from '@/components/ui/button';
import { Plus } from 'lucide-react';
import type { ListFilters as Filters, MasterRecord, PaginatedData } from '@/types/master-data';

export default function Index({
    resource,
    title,
    description,
    columns,
    records,
    filters,
}: {
    resource: string;
    title: string;
    description: string;
    columns: Record<string, string>;
    records: PaginatedData<MasterRecord>;
    filters: Filters;
}) {
    return (
        <AppLayout
            header={
                <PageHeading
                    title={title}
                    description={description}
                    action={
                        <Button asChild>
                            <Link href={route('master-data.create', resource)}>
                                <Plus />
                                Tambah data
                            </Link>
                        </Button>
                    }
                />
            }
        >
            <Head title={title} />
            <ListFilters
                key={resource}
                filters={filters}
                url={route('master-data.index', resource)}
            />
            <RecordsTable
                records={records}
                columns={columns}
                emptyLabel={'Daftar ' + title.toLowerCase()}
                actions={(record) => (
                    <RecordActions
                        record={record}
                        editUrl={route('master-data.edit', [resource, record.id])}
                        statusUrl={route('master-data.status', [resource, record.id])}
                        deleteUrl={route('master-data.destroy', [resource, record.id])}
                    />
                )}
            />
        </AppLayout>
    );
}

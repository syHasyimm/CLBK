import { Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import ConfirmDialog from '@/components/common/ConfirmDialog';
import { Pencil, Trash2 } from 'lucide-react';
import type { MasterRecord } from '@/types/master-data';

export default function RecordActions({
    record,
    editUrl,
    statusUrl,
    deleteUrl,
}: {
    record: MasterRecord;
    editUrl: string;
    statusUrl: string;
    deleteUrl?: string;
}) {
    const form = useForm({ is_active: record.is_active });
    const name = String(record.name ?? record.plate_number ?? record.code ?? '');
    function toggle() {
        form.transform(() => ({ is_active: !record.is_active }));
        form.patch(statusUrl, { preserveScroll: true });
    }
    return (
        <div className="flex items-center justify-end gap-1">
            <Button size="sm" variant="ghost" asChild>
                <Link href={editUrl} aria-label={'Ubah ' + name}>
                    <Pencil className="size-4" />
                    <span className="hidden xl:inline">Ubah</span>
                </Link>
            </Button>
            <ConfirmDialog
                title={record.is_active ? 'Nonaktifkan ' + name + '?' : 'Aktifkan ' + name + '?'}
                description={
                    record.is_active
                        ? 'Data tetap tersimpan. Data ini tidak dapat dipilih untuk pencatatan baru.'
                        : 'Data dapat dipilih kembali untuk pencatatan baru.'
                }
                actionLabel={record.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                onConfirm={toggle}
            >
                <Button size="sm" variant="ghost" disabled={form.processing}>
                    {record.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                </Button>
            </ConfirmDialog>
            {deleteUrl && (
                <ConfirmDialog
                    title={'Hapus ' + name + '?'}
                    description="Penghapusan permanen hanya diperbolehkan jika data belum digunakan. Data yang sudah digunakan harus dinonaktifkan."
                    actionLabel="Hapus data"
                    destructive
                    onConfirm={() => form.delete(deleteUrl, { preserveScroll: true })}
                >
                    <Button
                        size="sm"
                        variant="ghost"
                        aria-label={'Hapus ' + name}
                        disabled={form.processing}
                        className="text-destructive hover:text-destructive"
                    >
                        <Trash2 className="size-4" />
                    </Button>
                </ConfirmDialog>
            )}
        </div>
    );
}

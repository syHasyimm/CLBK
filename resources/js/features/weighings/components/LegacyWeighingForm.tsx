import { FormEvent, useState } from 'react';
import { useForm, usePage, Link } from '@inertiajs/react';
import FormField from '@/components/common/FormField';
import ReferencePicker from '@/components/common/ReferencePicker';
import UnsavedChangesGuard from '@/components/common/UnsavedChangesGuard';
import InputError from '@/components/InputError';
import { Button } from '@/components/ui/button';
import { kgInput } from '@/lib/weight';
import type { ReferenceOption, Weighing } from '@/types/operations';

export interface LegacyWeighingFormProps {
    record: Weighing | null;
    localNow: string;
    selectedTrip: ReferenceOption | null;
    replacement: { id: number; number: string; customer: ReferenceOption } | null;
}
export default function LegacyWeighingForm({
    record,
    localNow,
    selectedTrip,
    replacement,
}: LegacyWeighingFormProps) {
    const manager = usePage().props.auth.user.role === 'manager';
    const [customer, setCustomer] = useState<ReferenceOption | null>(
        record
            ? { id: record.customer_id, label: record.customer.name }
            : (replacement?.customer ?? null),
    );
    const trip = selectedTrip;
    const [workers, setWorkers] = useState<ReferenceOption[]>(
        record?.loaders.map((loader) => ({ id: loader.worker_id, label: loader.worker!.name })) ??
            [],
    );
    const form = useForm({
        customer_id: customer ? String(customer.id) : '',
        trip_id: trip ? String(trip.id) : '',
        tbs_kg: kgInput(record?.items.find((item) => item.fruit_type === 'TBS')?.weight_grams ?? 0),
        sort_kg: kgInput(
            record?.items.find((item) => item.fruit_type === 'SORT')?.weight_grams ?? 0,
        ),
        worker_ids: workers.map((worker) => worker.id),
        weighed_at: localNow,
        backdate_reason: record?.backdate_reason ?? '',
        replaces_weighing_id: replacement?.id ?? null,
        version: record?.version ?? null,
    });
    function updateWorkers(selected: ReferenceOption[]) {
        setWorkers(selected);
        form.setData(
            'worker_ids',
            selected.map((worker) => worker.id),
        );
    }
    function submit(e: FormEvent) {
        e.preventDefault();
        form.transform((data) => {
            const { weighed_at, backdate_reason, replaces_weighing_id, ...rest } = data;
            return manager ? data : rest;
        });
        if (record) form.patch(route('weighings.update', record.id));
    }
    return (
        <form onSubmit={submit} className="max-w-4xl rounded-xl border bg-card p-6 sm:p-8">
            <UnsavedChangesGuard dirty={form.isDirty && !form.processing} />
            {replacement && (
                <p className="mb-6 rounded-lg bg-secondary p-4 text-sm">
                    Pengganti tiket {replacement.number} yang dibatalkan.
                </p>
            )}
            <div className="grid gap-6 sm:grid-cols-2">
                <ReferencePicker
                    id="customer_id"
                    label="Konsumen"
                    url={route('references.index', 'customers')}
                    value={customer}
                    error={form.errors.customer_id}
                    onChange={(option) => {
                        setCustomer(option);
                        form.setData('customer_id', String(option?.id ?? ''));
                    }}
                />
                <div>
                    <p className="text-sm text-muted-foreground">Referensi historis</p>
                    <p className="mt-2 font-medium">{trip?.label}</p>
                </div>
                <FormField
                    field={{
                        key: 'tbs_kg',
                        label: 'Berat TBS (kg)',
                        type: 'weight',
                        required: true,
                    }}
                    value={form.data.tbs_kg}
                    error={form.errors.tbs_kg}
                    onChange={(value) => form.setData('tbs_kg', value)}
                />
                <FormField
                    field={{
                        key: 'sort_kg',
                        label: 'Berat sortir awal (kg)',
                        type: 'weight',
                        required: true,
                    }}
                    value={form.data.sort_kg}
                    error={form.errors.sort_kg}
                    onChange={(value) => form.setData('sort_kg', value)}
                />
                {manager ? (
                    <>
                        <FormField
                            field={{
                                key: 'weighed_at',
                                label: 'Waktu timbang (WIB)',
                                type: 'datetime-local',
                                required: true,
                            }}
                            value={form.data.weighed_at}
                            error={form.errors.weighed_at}
                            onChange={(value) => form.setData('weighed_at', value)}
                        />
                        <FormField
                            field={{
                                key: 'backdate_reason',
                                label: 'Alasan tanggal mundur',
                                type: 'textarea',
                                required: false,
                                maxLength: 1000,
                            }}
                            value={form.data.backdate_reason}
                            error={form.errors.backdate_reason}
                            onChange={(value) => form.setData('backdate_reason', value)}
                        />
                    </>
                ) : (
                    <p className="text-sm text-muted-foreground sm:col-span-2">
                        Waktu timbang mengikuti waktu usaha di server saat tiket disahkan.
                    </p>
                )}
            </div>
            <div className="mt-7 border-t pt-6">
                <h2 className="mb-2 text-base font-semibold">Anggota muat</h2>
                <p className="mb-5 text-sm leading-relaxed text-muted-foreground">
                    Dasar upah adalah TBS + sortir awal. Upah ditanggung toke dan dibagi rata kepada
                    anggota pada tiket ini.
                </p>
                <ReferencePicker
                    key={workers.length}
                    id="worker_ids"
                    label="Tambah anggota muat"
                    url={route('references.index', 'loading-workers')}
                    value={null}
                    onChange={(option) => {
                        if (option && !workers.some((worker) => worker.id === option.id))
                            updateWorkers([...workers, option]);
                    }}
                    error={form.errors.worker_ids}
                />
                <InputError
                    message={
                        Object.entries(form.errors).find(([key]) =>
                            key.startsWith('worker_ids.'),
                        )?.[1]
                    }
                    className="mt-2"
                />
                <ul className="mt-4 space-y-2">
                    {workers.map((worker) => (
                        <li
                            key={worker.id}
                            className="flex items-center justify-between gap-3 rounded-md bg-muted/60 px-4 py-2 text-sm"
                        >
                            <span>{worker.label}</span>
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                aria-label={'Hapus anggota ' + worker.label}
                                onClick={() =>
                                    updateWorkers(workers.filter((item) => item.id !== worker.id))
                                }
                            >
                                Hapus
                            </Button>
                        </li>
                    ))}
                </ul>
            </div>
            <InputError message={form.errors.replaces_weighing_id} className="mt-2" />
            <p className="mt-6 text-xs text-muted-foreground">
                Draft dapat disimpan sebelum harga tersedia. Harga dan nilai final diperiksa pada
                ringkasan pengesahan.
            </p>
            <div className="mt-7 flex flex-wrap justify-end gap-3 border-t pt-6">
                <Button variant="outline" asChild>
                    <Link
                        href={
                            record ? route('weighings.show', record.id) : route('weighings.index')
                        }
                    >
                        Kembali
                    </Link>
                </Button>
                <Button disabled={form.processing}>Simpan draft & lihat ringkasan</Button>
            </div>
        </form>
    );
}

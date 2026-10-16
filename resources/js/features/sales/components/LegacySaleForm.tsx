import FormField from '@/components/common/FormField';
import ReferencePicker from '@/components/common/ReferencePicker';
import UnsavedChangesGuard from '@/components/common/UnsavedChangesGuard';
import InputError from '@/components/InputError';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { rupiah } from '@/lib/currency';
import { operationError } from '@/lib/form';
import { kgInput } from '@/lib/weight';
import type { ReferenceOption } from '@/types/operations';
import type { SaleKind, SaleRecord } from '@/types/sales';
import { Link, useForm, usePage } from '@inertiajs/react';
import axios from 'axios';
import { FormEvent, useEffect, useState } from 'react';

export interface SaleFormProps {
    kind: SaleKind;
    record: SaleRecord | null;
    replacement: SaleRecord | null;
    localNow: string;
    selectedTrip: ReferenceOption | null;
    selectedVehicle: ReferenceOption | null;
    selectedFactory: ReferenceOption | null;
}
export default function SaleForm({
    kind,
    record,
    replacement,
    localNow,
    selectedTrip,
    selectedVehicle,
    selectedFactory,
}: SaleFormProps) {
    const manager = usePage().props.auth.user.role === 'manager';
    const source = record ?? replacement;
    const vehicle = selectedVehicle;
    const trip = selectedTrip;
    const [factory, setFactory] = useState(selectedFactory);
    const [reference, setReference] = useState<number | null>(null);
    const [referenceError, setReferenceError] = useState('');

    const form = useForm({
        trip_id: selectedTrip ? String(selectedTrip.id) : '',
        factory_id: selectedFactory ? String(selectedFactory.id) : '',
        invoice_number: source?.invoice_number ?? '',
        sort_kg:
            source?.sorted_out_grams !== undefined ? kgInput(source.sorted_out_grams ?? 0) : '',
        accepted_kg: source?.accepted_grams ? kgInput(source.accepted_grams) : '',
        buyer_name: source?.buyer_name ?? '',
        buyer_contact: source?.buyer_contact ?? '',
        sold_kg: source?.manual_sold_grams ? kgInput(source.manual_sold_grams) : '',
        sold_at: localNow,
        sell_price_snapshot_idr: source ? String(source.sell_price_snapshot_idr) : '',
        deduction_idr: String(source?.deduction_idr ?? 0),
        deduction_reason: source?.deduction_reason ?? '',
        notes: source?.notes ?? '',
        replaces_sale_id: replacement?.id ?? record?.replaces_sale_id ?? null,
        correction_reason: record?.correction_reason ?? '',
        version: record?.version ?? null,
    });
    useEffect(() => {
        setReference(null);
        setReferenceError('');
        if (kind !== 'factory' || !factory) return;
        const controller = new AbortController();
        const timer = setTimeout(() => {
            axios
                .get(route('factory-price-reference'), {
                    params: {
                        factory_id: factory.id,
                        sold_at: manager ? form.data.sold_at : undefined,
                    },
                    signal: controller.signal,
                })
                .then(({ data }) => setReference(data.price_idr))
                .catch((error) => {
                    if (!axios.isCancel(error))
                        setReferenceError(
                            'Harga pabrik belum dapat dimuat. Periksa harga pabrik dan coba kembali.',
                        );
                });
        }, 250);
        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [factory?.id, form.data.sold_at, kind, manager]);

    function field(key: keyof typeof form.data, label: string, type = 'text', required = true) {
        return (
            <FormField
                key={key}
                field={{
                    key,
                    label,
                    type,
                    required,
                    maxLength: type === 'textarea' ? 1000 : undefined,
                }}
                value={String(form.data[key] ?? '')}
                error={form.errors[key]}
                onChange={(value) => form.setData(key, value as never)}
            />
        );
    }
    function submit(e: FormEvent) {
        e.preventDefault();
        form.transform((data) => {
            const {
                factory_id,
                invoice_number,
                sort_kg,
                accepted_kg,
                buyer_name,
                buyer_contact,
                sold_kg,
                sold_at,
                sell_price_snapshot_idr,
                deduction_reason,
                ...common
            } = data;
            return {
                ...common,
                ...(manager ? { sold_at } : {}),
                ...(kind === 'factory'
                    ? { factory_id, invoice_number, sort_kg, accepted_kg }
                    : {
                          buyer_name,
                          buyer_contact,
                          sold_kg,
                          sell_price_snapshot_idr,
                          deduction_reason,
                      }),
            };
        });
        if (record) form.patch(route(kind + '-sales.update', { sale: record.id }));
    }
    return (
        <form onSubmit={submit} className="max-w-4xl rounded-xl border bg-card p-6 sm:p-8">
            <UnsavedChangesGuard dirty={form.isDirty && !form.processing} />
            {replacement && (
                <p className="mb-6 rounded-lg bg-secondary p-4 text-sm">
                    Nota pengganti {replacement.number}. Periksa kembali seluruh berat dan harga,
                    lalu isi alasan koreksi.
                </p>
            )}
            <p className="mb-6 text-sm text-muted-foreground">
                {kind === 'factory'
                    ? 'Isi berat sortir dan berat bersih diterima sesuai hasil pabrik. Harga jual mengikuti harga pabrik pada waktu penjualan.'
                    : 'Isi sendiri berat yang terjual dan harga per kg. Stok akan dibagi otomatis berdasarkan lot yang masuk lebih dahulu.'}
            </p>

            <div className="grid gap-6 sm:grid-cols-2">
                <div>
                    <p className="text-sm text-muted-foreground">Mobil / referensi historis</p>
                    <p className="mt-2 font-medium">
                        {vehicle?.label} · {trip?.label}
                    </p>
                </div>
                {kind === 'factory' ? (
                    <>
                        <ReferencePicker
                            id="factory_id"
                            label="Pabrik"
                            url={route('references.index', 'factories')}
                            value={factory}
                            error={form.errors.factory_id}
                            onChange={(option) => {
                                setFactory(option);
                                form.setData('factory_id', String(option?.id ?? ''));
                            }}
                        />
                        {field('invoice_number', 'Nomor nota pabrik')}
                        {field('sort_kg', 'Berat sortir pabrik (kg)', 'weight')}
                        {field('accepted_kg', 'Berat bersih diterima (kg)', 'weight')}
                    </>
                ) : (
                    <>
                        {field('buyer_name', 'Nama pembeli sortir')}
                        {field('buyer_contact', 'Kontak pembeli', 'text', false)}
                        {field('sold_kg', 'Berat sortir terjual (kg)', 'weight')}
                    </>
                )}
                {manager ? (
                    field('sold_at', 'Waktu penjualan (WIB)', 'datetime-local')
                ) : (
                    <p className="self-center text-sm text-muted-foreground">
                        Waktu penjualan memakai waktu server saat disahkan.
                    </p>
                )}
                {kind === 'factory' ? (
                    <div>
                        <Label htmlFor="factory_sale_price">Harga jual per kg (Rp)</Label>
                        <Input
                            id="factory_sale_price"
                            className="mt-2 bg-muted/40"
                            value={reference !== null ? rupiah(reference) : ''}
                            placeholder={
                                factory ? 'Harga belum tersedia' : 'Pilih pabrik terlebih dahulu'
                            }
                            readOnly
                            aria-describedby="factory_sale_price_help"
                        />
                        <p
                            id="factory_sale_price_help"
                            className="mt-2 text-sm text-muted-foreground"
                            role="status"
                        >
                            {referenceError ||
                                (reference !== null
                                    ? 'Harga otomatis dari informasi harga pabrik.'
                                    : factory
                                      ? 'Pastikan harga pabrik tersedia untuk waktu penjualan ini.'
                                      : 'Harga akan terisi setelah memilih pabrik.')}
                        </p>
                    </div>
                ) : (
                    field('sell_price_snapshot_idr', 'Harga jual aktual per kg (Rp)', 'text')
                )}
                {field('deduction_idr', 'Potongan penjualan (Rp)')}
                {kind === 'sort' &&
                    field(
                        'deduction_reason',
                        'Alasan potongan',
                        'textarea',
                        Number(form.data.deduction_idr) > 0,
                    )}
                {replacement && field('correction_reason', 'Alasan koreksi', 'textarea')}
                {field('notes', 'Catatan', 'textarea', false)}
            </div>
            <InputError message={operationError(form.errors)} className="mt-4" />
            <div className="mt-8 flex flex-wrap gap-3">
                <Button disabled={form.processing || (kind === 'factory' && reference === null)}>
                    Simpan draft
                </Button>
                <Button asChild variant="outline">
                    <Link href={route(kind + '-sales.index')}>Kembali</Link>
                </Button>
            </div>
        </form>
    );
}

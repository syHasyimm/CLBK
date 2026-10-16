import { useEffect } from 'react';
import { ArrowLeft, Info, Plus, ReceiptText, Wallet } from 'lucide-react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import PaymentStatusBadge from '@/features/finance/components/PaymentStatusBadge';
import AppLayout from '@/layouts/AppLayout';
import PageHeading from '@/components/common/PageHeading';
import ConfirmDialog from '@/components/common/ConfirmDialog';
import FormField from '@/components/common/FormField';
import UnsavedChangesGuard from '@/components/common/UnsavedChangesGuard';
import LoadingWagePreview from '@/features/weighings/components/LoadingWagePreview';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { dateTime } from '@/lib/dates';
import { rupiah } from '@/lib/currency';
import { weight } from '@/lib/weight';
import { requestKey } from '@/lib/idempotency';
import { statusLabels } from '@/types/operations';
import type { Weighing, Preview } from '@/types/operations';

export default function Show({
    record,
    preview,
    previewError,
    canEdit,
    canVoid,
}: {
    record: Weighing;
    preview: Preview | null;
    previewError: string | null;
    canEdit: boolean;
    canVoid: boolean;
}) {
    const role = usePage().props.auth.user.role;
    const canPay = role !== 'driver';
    const direct = record.workflow === 'direct';
    const post = useForm({
        version: record.version,
        price_schedule_id: preview?.price_schedule_id ?? 0,
        idempotency_key: requestKey(),
    });
    const deletion = useForm({ version: record.version });
    const voiding = useForm({ version: record.version, reason: '', idempotency_key: requestKey() });
    useEffect(() => {
        post.setData({
            version: record.version,
            price_schedule_id: preview?.price_schedule_id ?? 0,
            idempotency_key: requestKey(),
        });
        deletion.setData('version', record.version);
        voiding.setData('version', record.version);
    }, [record.id, record.version, preview?.price_schedule_id]);
    const result = record.status === 'draft' ? preview : record;
    const items = [...(result?.items ?? record.items)].sort(
        (a, b) => Number(a.fruit_type === 'SORT') - Number(b.fruit_type === 'SORT'),
    );
    const names = Object.fromEntries(
        record.loaders.map((loader) => [loader.worker_id, loader.worker!.name]),
    );
    const lots = record.items.filter((item) => item.lot);
    return (
        <AppLayout
            header={
                <PageHeading
                    title={record.number}
                    description={record.customer.name + ' · ' + record.vehicle.plate_number}
                    action={
                        <Badge variant={record.status === 'voided' ? 'outline' : 'secondary'}>
                            {statusLabels[record.status]}
                        </Badge>
                    }
                />
            }
        >
            <Head title={record.number} />
            <UnsavedChangesGuard
                dirty={!!voiding.data.reason && !voiding.processing && record.status === 'posted'}
            />
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3 text-sm">
                <span className="text-muted-foreground">
                    Timbang:{' '}
                    {dateTime(
                        record.status === 'draft' && preview
                            ? preview.weighed_at
                            : record.weighed_at,
                    )}{' '}
                    WIB · {record.vehicle.plate_number}
                </span>
                <div className="flex flex-wrap gap-3">
                    <Button asChild variant="outline" size="sm">
                        <Link href={route('weighings.index')}>
                            <ArrowLeft aria-hidden="true" className="size-4" />
                            Daftar timbangan
                        </Link>
                    </Button>
                    {role !== 'cashier' && (
                        <Button asChild size="sm">
                            <Link href={route('weighings.create')}>
                                <Plus aria-hidden="true" className="size-4" />
                                Catat Timbangan Baru
                            </Link>
                        </Button>
                    )}
                </div>
            </div>
            {previewError && (
                <p
                    role="alert"
                    className="mb-6 rounded-lg border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive"
                >
                    {previewError}
                </p>
            )}
            {record.status === 'draft' && preview && (
                <p className="mb-6 rounded-lg border bg-secondary p-4 text-sm">
                    Pratinjau harga versi #{preview.price_schedule_id}, berlaku{' '}
                    {dateTime(preview.effective_from)} WIB. Tarif diperiksa kembali saat pengesahan.
                </p>
            )}
            {record.status === 'voided' && (
                <p className="mb-6 rounded-lg border p-4 text-sm">
                    Tiket dibatalkan: {record.void_reason}. Nilai historis tetap tersimpan dan tidak
                    menjadi tagihan aktif.
                </p>
            )}
            <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
                <section className="min-w-0 overflow-hidden rounded-2xl border bg-card">
                    <div className="flex items-start gap-3 border-b px-5 py-5 sm:px-6">
                        <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-secondary text-primary">
                            <ReceiptText aria-hidden="true" className="size-5" />
                        </span>
                        <div>
                            <h2 className="text-base font-semibold tracking-tight">
                                Buah & hak konsumen
                            </h2>
                            <p className="mt-1 text-xs leading-relaxed text-muted-foreground">
                                Upah muat ditanggung toke; uang konsumen tidak dipotong.
                            </p>
                        </div>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <caption className="sr-only">
                                Berat buah, harga terkunci, nilai buah, dan biaya muat.
                            </caption>
                            <thead className="border-b bg-muted/40 text-xs text-muted-foreground">
                                <tr>
                                    {[
                                        'Buah',
                                        'Berat',
                                        'Harga / kg',
                                        'Nilai buah',
                                        'Biaya muat',
                                    ].map((label) => (
                                        <th
                                            key={label}
                                            scope="col"
                                            className="whitespace-nowrap px-5 py-3 font-medium"
                                        >
                                            {label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {items.map((item) => (
                                    <tr key={item.fruit_type} className="border-b">
                                        <th scope="row" className="px-5 py-4 text-left font-medium">
                                            {item.fruit_type === 'TBS' ? 'TBS' : 'Sortir konsumen'}
                                        </th>
                                        <td className="whitespace-nowrap px-5 py-4 tabular-nums">
                                            {weight(item.weight_grams)}
                                        </td>
                                        <td className="whitespace-nowrap px-5 py-4 tabular-nums">
                                            {rupiah(item.buy_price_snapshot_idr)}
                                        </td>
                                        <td className="whitespace-nowrap px-5 py-4 font-semibold tabular-nums text-primary">
                                            {rupiah(item.purchase_amount_idr)}
                                        </td>
                                        <td className="whitespace-nowrap px-5 py-4 tabular-nums">
                                            {rupiah(item.loading_cost_allocated_idr)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="flex flex-wrap items-center justify-between gap-4 bg-secondary/35 px-5 py-5 sm:px-6">
                        <span className="flex items-center gap-2 text-sm font-medium">
                            <Wallet aria-hidden="true" className="size-4 text-primary" />
                            Total hak konsumen
                        </span>
                        <strong className="break-words text-2xl font-semibold tracking-tight tabular-nums text-primary">
                            {rupiah(result?.total_purchase_idr ?? null)}
                        </strong>
                    </div>
                    {record.status === 'posted' && (
                        <div className="border-t px-5 py-5 sm:px-6">
                            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                                <p className="text-sm font-medium">Pembayaran konsumen</p>
                                <PaymentStatusBadge status={record.payment_status ?? 'unpaid'} />
                            </div>
                            <dl className="grid grid-cols-2 gap-4 text-xs">
                                <div>
                                    <dt className="text-muted-foreground">Sudah dibayar</dt>
                                    <dd className="mt-1.5 break-words text-sm font-semibold tabular-nums">
                                        {rupiah(record.paid_idr ?? 0)}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">Sisa pembayaran</dt>
                                    <dd className="mt-1.5 break-words text-sm font-semibold tabular-nums text-primary">
                                        {rupiah(record.remaining_idr ?? record.total_purchase_idr)}
                                    </dd>
                                </div>
                            </dl>
                            <p className="mt-4 flex items-start gap-2 text-[11px] text-muted-foreground">
                                <Info aria-hidden="true" className="size-3.5 shrink-0" />
                                Harga terkunci pada versi #{record.price_schedule_id}.
                            </p>
                            {canPay && (record.remaining_idr ?? 0) > 0 && (
                                <Link
                                    className="mt-4 inline-flex text-sm font-medium text-primary underline underline-offset-4"
                                    href={route('customer-payments.payee', record.customer_id)}
                                >
                                    Bayar konsumen
                                </Link>
                            )}
                        </div>
                    )}
                </section>
                {result && (
                    <LoadingWagePreview
                        loaders={result.loaders}
                        names={names}
                        basis={result.loading_basis_grams!}
                        rate={result.loading_rate_snapshot_idr!}
                        total={result.total_loading_wage_idr!}
                    />
                )}
            </div>
            {!direct && lots.length > 0 && (
                <section className="mt-6 rounded-xl border bg-card p-6">
                    <h2 className="text-lg font-semibold">Histori stok sortir lama</h2>
                    {lots.map((item) => (
                        <div
                            key={item.id}
                            className="mt-4 flex flex-wrap justify-between gap-3 text-sm"
                        >
                            <span>
                                Lot #{item.lot!.id} ·{' '}
                                {item.lot!.status === 'active' ? 'Tersedia' : 'Dibatalkan'}
                            </span>
                            <span>
                                Sisa{' '}
                                {weight(
                                    item.lot!.movements.reduce(
                                        (sum, movement) => sum + movement.quantity_delta_grams,
                                        0,
                                    ),
                                )}
                            </span>
                        </div>
                    ))}
                </section>
            )}
            {!direct && record.status === 'draft' && canEdit && (
                <div className="mt-8 flex flex-wrap justify-end gap-3">
                    <ConfirmDialog
                        title="Hapus draft timbang?"
                        description="Draft ini belum membentuk hak konsumen, hak upah, atau stok sortir."
                        actionLabel="Hapus draft"
                        destructive
                        onConfirm={() => deletion.delete(route('weighings.destroy', record.id))}
                    >
                        <Button variant="outline" disabled={deletion.processing}>
                            Hapus draft
                        </Button>
                    </ConfirmDialog>
                    <Button variant="outline" asChild>
                        <Link href={route('weighings.edit', record.id)}>Edit draft</Link>
                    </Button>
                    <ConfirmDialog
                        title="Sahkan tiket timbang?"
                        description={`${record.customer.name} menerima hak ${rupiah(preview?.total_purchase_idr ?? null)}. Harga dan pembagian upah pada ringkasan ini akan dikunci.`}
                        actionLabel="Ya, sahkan tiket"
                        onConfirm={() => post.post(route('weighings.post', record.id))}
                    >
                        <Button disabled={!preview || !record.loaders.length || post.processing}>
                            Sahkan tiket
                        </Button>
                    </ConfirmDialog>
                </div>
            )}
            {record.status === 'posted' &&
                canVoid &&
                (direct || record.trip?.status === 'loading') && (
                    <section className="mt-8 max-w-xl rounded-xl border bg-card p-6">
                        <h2 className="mb-4 text-lg font-semibold">Pembatalan tiket</h2>
                        <FormField
                            field={{
                                key: 'reason',
                                label: 'Alasan pembatalan',
                                type: 'textarea',
                                required: true,
                                maxLength: 1000,
                            }}
                            value={voiding.data.reason}
                            error={voiding.errors.reason}
                            onChange={(value) => voiding.setData('reason', value)}
                        />
                        <ConfirmDialog
                            title="Batalkan tiket sah?"
                            description={
                                direct
                                    ? 'Hak konsumen dan upah timbangan ini dinonaktifkan. Riwayat tetap tersimpan; pembayaran terkait harus dibalik terlebih dahulu.'
                                    : 'Hak konsumen dan hak upah tiket lama dinonaktifkan, stok sortir lama dibalik, dan riwayat tetap tersimpan.'
                            }
                            actionLabel="Batalkan tiket"
                            destructive
                            onConfirm={() =>
                                voiding.post(route('weighings.void', record.id), {
                                    onSuccess: () => voiding.reset('reason'),
                                })
                            }
                        >
                            <Button
                                className="mt-4"
                                variant="outline"
                                disabled={!voiding.data.reason.trim() || voiding.processing}
                            >
                                Batalkan tiket
                            </Button>
                        </ConfirmDialog>
                    </section>
                )}
            {record.status === 'voided' && canVoid && (
                <Button className="mt-6" asChild>
                    <Link
                        href={route('weighings.create', {
                            vehicle_id: record.vehicle_id,
                            replaces_weighing_id: record.id,
                        })}
                    >
                        Buat tiket pengganti
                    </Link>
                </Button>
            )}
        </AppLayout>
    );
}

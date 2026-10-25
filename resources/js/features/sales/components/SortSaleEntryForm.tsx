import UnsavedChangesGuard from '@/components/common/UnsavedChangesGuard';
import InputError from '@/components/InputError';
import { Button } from '@/components/ui/button';
import SaleSummary from '@/features/sales/components/SaleSummary';
import type { SaleFormModel } from '@/features/sales/hooks/use-sale-form';
import { operationError } from '@/lib/form';
import { Link } from '@inertiajs/react';
import SaleFormField from './SaleFormField';
import SaleVehiclePicker from './SaleVehiclePicker';
import { useState } from 'react';
import TransactionReview from '@/components/common/TransactionReview';
import { rupiah } from '@/lib/currency';
import { weight } from '@/lib/weight';

export default function SortSaleEntryForm({ model }: { model: SaleFormModel }) {
    const { kind, replacement, form, summary, ready, submit } = model;
    const [reviewOpen, setReviewOpen] = useState(false);

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                if (ready) setReviewOpen(true);
            }}
            className="grid items-start gap-6 lg:grid-cols-3"
        >
            <UnsavedChangesGuard dirty={form.isDirty && !form.processing} />
            <div className="rounded-xl border bg-card p-6 sm:p-8 lg:col-span-2">
                {replacement && (
                    <p className="mb-5 rounded-lg bg-secondary p-4 text-sm">
                        Pengganti {replacement.number}. Periksa isian dan jelaskan koreksinya.
                    </p>
                )}
                <h2 className="text-lg font-semibold">Buah sortir terjual</h2>
                <p className="mb-6 mt-2 text-sm text-muted-foreground">
                    Waktu dan petugas dicatat otomatis saat disimpan.
                </p>
                <div className="grid gap-6 sm:grid-cols-2">
                    <SaleVehiclePicker model={model} />
                    {
                        <SaleFormField
                            model={model}
                            name={'sold_kg'}
                            label={'Berat sortir terjual (kg)'}
                            type={'weight'}
                        />
                    }
                    {
                        <SaleFormField
                            model={model}
                            name={'sell_price_snapshot_idr'}
                            label={'Harga jual per kg (Rp)'}
                        />
                    }
                </div>
                <details
                    className="mt-7 border-t pt-5"
                    open={!!replacement || !!form.errors.invoice_number}
                >
                    <summary className="cursor-pointer text-sm font-medium">
                        Informasi tambahan (opsional)
                    </summary>
                    <div className="mt-5 grid gap-5 sm:grid-cols-2">
                        {
                            <SaleFormField
                                model={model}
                                name={'buyer_name'}
                                label={'Nama pembeli / tempat'}
                                type={'text'}
                                required={false}
                            />
                        }
                        {
                            <SaleFormField
                                model={model}
                                name={'buyer_contact'}
                                label={'Kontak pembeli'}
                                type={'text'}
                                required={false}
                            />
                        }
                        {
                            <SaleFormField
                                model={model}
                                name={'notes'}
                                label={'Catatan'}
                                type={'textarea'}
                                required={false}
                            />
                        }
                        {replacement && (
                            <SaleFormField
                                model={model}
                                name={'correction_reason'}
                                label={'Alasan koreksi'}
                                type={'textarea'}
                            />
                        )}
                    </div>
                </details>
            </div>
            <div className="space-y-4">
                <SaleSummary
                    preview={summary.data?.preview ?? null}
                    price={summary.data?.price.price_idr ?? null}
                />
                {summary.busy && (
                    <p role="status" className="text-sm text-muted-foreground">
                        Menghitung ringkasan...
                    </p>
                )}
                <InputError
                    message={
                        form.errors.factory_price_schedule_id ||
                        summary.errors.factory_price_schedule_id ||
                        summary.errors.operation
                    }
                />
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={summary.refresh}
                    disabled={summary.busy}
                >
                    Perbarui ringkasan
                </Button>
            </div>
            <div className="mobile-action-dock flex flex-wrap items-center gap-3 lg:col-span-3">
                <Button className="w-full lg:w-auto" disabled={!ready}>
                    {form.processing ? 'Menyimpan...' : 'Periksa penjualan sortir'}
                </Button>
                <Button asChild variant="outline" className="mobile-secondary-action">
                    <Link href={route(kind + '-sales.index')}>Kembali</Link>
                </Button>
                <InputError
                    message={
                        operationError(form.errors) ||
                        form.errors.replaces_sale_id ||
                        form.errors.idempotency_key
                    }
                />
            </div>
            <TransactionReview
                open={reviewOpen}
                onOpenChange={setReviewOpen}
                title="Periksa penjualan sortir"
                description="Periksa berat, harga jual, dan pembeli. Penerimaan uang dicatat terpisah oleh kasir atau manajer."
                rows={[
                    { label: 'Mobil', value: model.vehicle?.label ?? 'Belum dipilih' },
                    { label: 'Pembeli', value: form.data.buyer_name || 'Tidak diisi' },
                    {
                        label: 'Berat sortir',
                        value: summary.data?.preview
                            ? weight(summary.data.preview.grams)
                            : 'Belum dihitung',
                    },
                    {
                        label: 'Harga per kg',
                        value: summary.data
                            ? rupiah(summary.data.price.price_idr)
                            : 'Belum tersedia',
                    },
                    {
                        label: 'Nilai penjualan',
                        value: summary.data?.preview
                            ? rupiah(summary.data.preview.revenue_idr)
                            : 'Belum dihitung',
                    },
                ]}
                confirmLabel="Simpan penjualan sortir"
                onConfirm={() => submit()}
                disabled={!ready}
            />
        </form>
    );
}

import UnsavedChangesGuard from '@/components/common/UnsavedChangesGuard';
import InputError from '@/components/InputError';
import { Button } from '@/components/ui/button';
import SaleSummary from '@/features/sales/components/SaleSummary';
import type { SaleFormModel } from '@/features/sales/hooks/use-sale-form';
import { operationError } from '@/lib/form';
import { Link } from '@inertiajs/react';
import SaleFormField from './SaleFormField';
import SaleVehiclePicker from './SaleVehiclePicker';

export default function SortSaleEntryForm({ model }: { model: SaleFormModel }) {
    const { kind, replacement, form, summary, ready, submit } = model;

    return (
        <form onSubmit={submit} className="grid items-start gap-6 lg:grid-cols-3">
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
            <div className="flex flex-wrap items-center gap-3 lg:col-span-3">
                <Button disabled={!ready}>Simpan Penjualan Sortir</Button>
                <Button asChild variant="outline">
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
        </form>
    );
}

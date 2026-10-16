import ReferencePicker from '@/components/common/ReferencePicker';
import UnsavedChangesGuard from '@/components/common/UnsavedChangesGuard';
import InputError from '@/components/InputError';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import FactorySaleSummary from '@/features/sales/components/FactorySaleSummary';
import type { SaleFormModel } from '@/features/sales/hooks/use-sale-form';
import { rupiah } from '@/lib/currency';
import { operationError } from '@/lib/form';
import { Link } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    ChevronDown,
    Factory,
    LoaderCircle,
    RefreshCw,
    Scale,
    Truck,
} from 'lucide-react';
import SaleFormField from './SaleFormField';
import SaleVehiclePicker from './SaleVehiclePicker';

export default function FactorySaleEntryForm({ model }: { model: SaleFormModel }) {
    const { replacement, vehicleAssignment, factory, setFactory, form, summary, ready, submit } =
        model;

    return (
        <form
            onSubmit={submit}
            aria-label="Catat penjualan pabrik"
            aria-busy={form.processing}
            className="factory-sales grid items-start gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]"
        >
            <UnsavedChangesGuard dirty={form.isDirty && !form.processing} />
            <div className="factory-panel min-w-0 overflow-hidden">
                {replacement && (
                    <p className="border-b bg-secondary/50 px-5 py-4 text-sm text-primary sm:px-6">
                        Pengganti <span className="font-semibold">{replacement.number}</span>.
                        Periksa isian dan jelaskan koreksinya.
                    </p>
                )}
                <section aria-labelledby="factory-destination-title" className="p-5 sm:p-6">
                    <div className="mb-6 flex items-start gap-3">
                        <span className="factory-step">01</span>
                        <div>
                            <h2
                                id="factory-destination-title"
                                className="flex items-center gap-2 font-semibold"
                            >
                                <Truck className="size-4 text-primary" aria-hidden="true" />
                                Mobil & pabrik
                            </h2>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {vehicleAssignment
                                    ? 'Mobil ditetapkan oleh admin. Pilih pabrik tujuan.'
                                    : 'Tentukan mobil yang membawa buah dan pabrik tujuan.'}
                            </p>
                        </div>
                    </div>
                    <div className="grid gap-5 sm:grid-cols-2">
                        <SaleVehiclePicker model={model} />
                        <ReferencePicker
                            id="factory_id"
                            label="Pabrik tujuan"
                            url={route('references.index', 'factories')}
                            value={factory}
                            error={form.errors.factory_id || summary.errors.factory_id}
                            onChange={(value) => {
                                setFactory(value);
                                form.setData('factory_id', String(value?.id ?? ''));
                            }}
                        />
                    </div>
                </section>
                <section
                    aria-labelledby="factory-weight-title"
                    className="border-t bg-muted/20 p-5 sm:p-6"
                >
                    <div className="mb-6 flex items-start gap-3">
                        <span className="factory-step">02</span>
                        <div>
                            <h2
                                id="factory-weight-title"
                                className="flex items-center gap-2 font-semibold"
                            >
                                <Scale className="size-4 text-primary" aria-hidden="true" />
                                Hasil timbang pabrik
                            </h2>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Gunakan berat bersih yang diterima sesuai hasil timbang pabrik.
                            </p>
                        </div>
                    </div>
                    <div className="grid items-start gap-5 sm:grid-cols-2">
                        <div className="min-w-0">
                            <Label htmlFor="accepted_kg">Berat bersih diterima</Label>
                            <div className="relative mt-2">
                                <Input
                                    id="accepted_kg"
                                    name="accepted_kg"
                                    inputMode="decimal"
                                    type="text"
                                    required
                                    maxLength={15}
                                    autoComplete="off"
                                    placeholder="8500,500"
                                    className="h-16 bg-card pl-4 pr-14 text-xl font-semibold tabular-nums placeholder:text-sm placeholder:font-normal sm:text-2xl md:text-2xl"
                                    value={form.data.accepted_kg}
                                    onChange={(e) => form.setData('accepted_kg', e.target.value)}
                                    aria-invalid={
                                        !!(form.errors.accepted_kg || summary.errors.accepted_kg)
                                    }
                                    aria-describedby="factory-weight-help"
                                />
                                <span className="pointer-events-none absolute right-4 top-5 text-sm font-medium text-muted-foreground">
                                    kg
                                </span>
                            </div>
                            <div id="factory-weight-help" className="mt-2">
                                <InputError
                                    message={form.errors.accepted_kg || summary.errors.accepted_kg}
                                />
                                <p className="mt-1 text-[11px] text-muted-foreground">
                                    Maksimal 3 angka desimal. Koma atau titik dapat digunakan.
                                </p>
                            </div>
                        </div>
                        <div className="min-w-0">
                            <Label htmlFor="factory_sale_price">Harga jual per kg</Label>
                            <Input
                                id="factory_sale_price"
                                readOnly
                                className="mt-2 h-16 border-primary/15 bg-secondary/60 px-4 text-lg font-semibold tabular-nums text-primary placeholder:text-xs placeholder:font-normal md:text-lg"
                                value={summary.data ? rupiah(summary.data.price.price_idr) : ''}
                                placeholder={
                                    summary.busy ? 'Memuat harga...' : 'Pilih pabrik dahulu'
                                }
                                aria-describedby="factory-price-help"
                            />
                            <p
                                id="factory-price-help"
                                className="mt-2 text-[11px] leading-relaxed text-muted-foreground"
                            >
                                Harga otomatis dari jadwal harga pabrik yang berlaku.
                            </p>
                        </div>
                    </div>
                </section>
                <section aria-labelledby="factory-note-title" className="border-t p-5 sm:p-6">
                    <div className="mb-6 flex items-start gap-3">
                        <span className="factory-step">03</span>
                        <div>
                            <h2
                                id="factory-note-title"
                                className="flex items-center gap-2 font-semibold"
                            >
                                <Factory className="size-4 text-primary" aria-hidden="true" />
                                Informasi nota
                            </h2>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Tambahkan nomor nota dan potongan jika ada.
                            </p>
                        </div>
                    </div>
                    <div className="grid gap-5 sm:grid-cols-2">
                        {
                            <SaleFormField
                                model={model}
                                name={'invoice_number'}
                                label={'Nomor nota pabrik'}
                                type={'text'}
                                required={false}
                            />
                        }
                        {
                            <SaleFormField
                                model={model}
                                name={'deduction_idr'}
                                label={'Potongan (Rp)'}
                                type={'text'}
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
                    <details className="mt-5 border-t pt-4" open={!!form.errors.notes}>
                        <summary className="flex w-fit cursor-pointer list-none items-center gap-2 rounded-sm text-xs font-medium text-muted-foreground focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring">
                            <ChevronDown className="size-3.5" aria-hidden="true" />
                            Tambahkan catatan
                        </summary>
                        <div className="mt-4">
                            {
                                <SaleFormField
                                    model={model}
                                    name={'notes'}
                                    label={'Catatan'}
                                    type={'textarea'}
                                    required={false}
                                />
                            }
                        </div>
                    </details>
                </section>
            </div>
            <aside
                className="min-w-0 space-y-4 xl:sticky xl:top-6"
                aria-label="Periksa dan simpan penjualan"
            >
                <FactorySaleSummary
                    preview={summary.data?.preview ?? null}
                    price={summary.data?.price.price_idr ?? null}
                />
                <div className="px-1">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <p
                            role="status"
                            className="flex items-center gap-2 text-[11px] text-muted-foreground"
                        >
                            {summary.busy ? (
                                <>
                                    <LoaderCircle
                                        className="size-3.5 animate-spin motion-reduce:animate-none"
                                        aria-hidden="true"
                                    />
                                    Menghitung ringkasan...
                                </>
                            ) : summary.ready ? (
                                <>
                                    <Check className="size-3.5 text-primary" aria-hidden="true" />
                                    Ringkasan siap diperiksa
                                </>
                            ) : (
                                'Lengkapi data untuk menghitung ringkasan.'
                            )}
                        </p>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="h-8 px-2 text-xs"
                            onClick={summary.refresh}
                            disabled={summary.busy || form.processing}
                        >
                            <RefreshCw className="size-3" aria-hidden="true" />
                            Perbarui
                        </Button>
                    </div>
                    <InputError
                        className="mt-2"
                        message={
                            form.errors.factory_price_schedule_id ||
                            summary.errors.factory_price_schedule_id ||
                            summary.errors.operation
                        }
                    />
                </div>
                <Button
                    type="submit"
                    size="lg"
                    className="h-12 w-full rounded-xl"
                    disabled={!ready}
                >
                    {form.processing ? (
                        <LoaderCircle
                            className="size-4 animate-spin motion-reduce:animate-none"
                            aria-hidden="true"
                        />
                    ) : (
                        <Check className="size-4" aria-hidden="true" />
                    )}
                    {form.processing ? 'Menyimpan...' : 'Simpan Penjualan'}
                </Button>
                <InputError
                    message={
                        operationError(form.errors) ||
                        form.errors.replaces_sale_id ||
                        form.errors.idempotency_key
                    }
                />
                <p className="px-1 text-center text-[11px] leading-relaxed text-muted-foreground">
                    Waktu transaksi, petugas, dan harga dicatat otomatis saat disimpan.
                </p>
                <Link
                    className="factory-text-link flex w-full justify-center py-2"
                    href={route('factory-sales.index')}
                >
                    <ArrowLeft className="size-3.5" aria-hidden="true" />
                    Kembali ke daftar penjualan
                </Link>
            </aside>
        </form>
    );
}

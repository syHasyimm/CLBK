import AssignedVehicle from '@/components/common/AssignedVehicle';
import ReferencePicker from '@/components/common/ReferencePicker';
import UnsavedChangesGuard from '@/components/common/UnsavedChangesGuard';
import InputError from '@/components/InputError';
import { Button } from '@/components/ui/button';
import LoadingWagePreview from '@/features/weighings/components/LoadingWagePreview';
import TransactionReview from '@/components/common/TransactionReview';
import { useState } from 'react';
import { rupiah } from '@/lib/currency';
import { weight } from '@/lib/weight';
import WeighingSummary from '@/features/weighings/components/WeighingSummary';
import useWeighingForm from '@/features/weighings/hooks/use-weighing-form';
import type { DriverVehicleAssignment, ReferenceOption } from '@/types/operations';
import { Link } from '@inertiajs/react';
import { Check, Info, LoaderCircle, Plus, Scale } from 'lucide-react';
import WeighingPriceSection from './WeighingPriceSection';
import WeighingWeightsSection from './WeighingWeightsSection';
import WeighingWorkersSection from './WeighingWorkersSection';

export interface WeighingFormProps {
    selectedCustomer: ReferenceOption | null;
    availableWorkers: ReferenceOption[];
    selectedVehicle: ReferenceOption | null;
    vehicleAssignment: DriverVehicleAssignment | null;
    replacement: { id: number; number: string; customer: ReferenceOption } | null;
}

export default function WeighingForm(props: WeighingFormProps) {
    const model = useWeighingForm(props);
    const {
        replacement,
        vehicleAssignment,
        driver,
        customer,
        setCustomer,
        vehicle,
        setVehicle,
        workers,
        form,
        summary,
        errors,
        names,
        submit,
    } = model;
    const [reviewOpen, setReviewOpen] = useState(false);
    const ready =
        !form.processing &&
        summary.ready &&
        !!summary.preview &&
        !!form.data.customer_id &&
        !!form.data.vehicle_id &&
        !vehicleAssignment?.error &&
        workers.length > 0;
    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                if (ready) setReviewOpen(true);
            }}
            aria-label="Catat timbangan sawit"
            aria-busy={form.processing}
            className="weighing-form grid items-start gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]"
        >
            <UnsavedChangesGuard dirty={form.isDirty && !form.processing} />
            <section
                aria-labelledby="weighing-data-heading"
                className="min-w-0 overflow-hidden rounded-2xl border bg-card"
            >
                <div className="flex items-start gap-3.5 border-b px-5 py-5 sm:px-6">
                    <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-secondary text-primary">
                        <Scale aria-hidden="true" className="size-5" />
                    </span>
                    <div>
                        <h2
                            id="weighing-data-heading"
                            className="text-base font-semibold tracking-tight"
                        >
                            Data penimbangan
                        </h2>
                        <p className="mt-1 text-xs leading-relaxed text-muted-foreground">
                            Catat buah masuk dan anggota yang mengerjakan muatan.
                        </p>
                    </div>
                </div>
                {replacement && (
                    <p className="border-b bg-secondary/50 px-5 py-4 text-sm text-primary sm:px-6">
                        Pengganti timbangan{' '}
                        <span className="font-semibold">{replacement.number}</span> yang dibatalkan.
                    </p>
                )}
                <div className="grid gap-5 px-5 py-6 sm:grid-cols-2 sm:px-6">
                    <div>
                        <ReferencePicker
                            id="customer_id"
                            label="Konsumen"
                            url={route('references.index', 'customers')}
                            value={customer}
                            error={errors.customer_id}
                            onChange={(option) => {
                                setCustomer(option);
                                form.setData('customer_id', String(option?.id ?? ''));
                            }}
                        />
                        {driver && (
                            <Link
                                href={route('customers.create')}
                                className="mt-3 inline-flex min-h-11 items-center gap-2 rounded-lg px-2 text-sm font-medium text-primary hover:bg-secondary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                            >
                                <Plus aria-hidden="true" className="size-4" />
                                Tambah konsumen
                            </Link>
                        )}
                    </div>
                    {vehicleAssignment ? (
                        <AssignedVehicle
                            vehicle={vehicle}
                            blocked={vehicleAssignment.error}
                            error={errors.vehicle_id || errors.vehicle_assignment_version}
                        />
                    ) : (
                        <ReferencePicker
                            id="vehicle_id"
                            label="Mobil yang mengambil buah"
                            url={route('operational-references.index', 'vehicles')}
                            value={vehicle}
                            error={errors.vehicle_id}
                            onChange={(option) => {
                                setVehicle(option);
                                form.setData('vehicle_id', String(option?.id ?? ''));
                            }}
                        />
                    )}
                </div>
                <WeighingWeightsSection model={model} />
                <WeighingWorkersSection model={model} />
                <WeighingPriceSection model={model} />
                {(errors.price_schedule_id ||
                    errors.replaces_weighing_id ||
                    errors.operation ||
                    errors.idempotency_key) && (
                    <div className="border-t px-5 py-4 sm:px-6">
                        {errors.price_schedule_id && (
                            <p
                                role="alert"
                                className="rounded-lg border border-destructive/20 bg-destructive/5 p-4 text-sm text-destructive"
                            >
                                {errors.price_schedule_id}
                            </p>
                        )}
                        <InputError
                            message={
                                errors.replaces_weighing_id ??
                                errors.operation ??
                                errors.idempotency_key
                            }
                            className="mt-3"
                        />
                    </div>
                )}
            </section>
            <aside
                className="min-w-0 space-y-6 xl:sticky xl:top-6"
                aria-label="Ringkasan timbangan"
            >
                <WeighingSummary preview={summary.preview} busy={summary.busy} />
                {summary.preview && (
                    <LoadingWagePreview
                        loaders={summary.preview.loaders}
                        names={names}
                        basis={summary.preview.loading_basis_grams}
                        rate={summary.preview.loading_rate_snapshot_idr}
                        total={summary.preview.total_loading_wage_idr}
                    />
                )}
            </aside>
            <TransactionReview
                open={reviewOpen}
                onOpenChange={setReviewOpen}
                title="Periksa timbangan"
                description="Pastikan konsumen, berat buah, harga, dan pembagian upah sudah benar. Harga dan upah dikunci saat disimpan."
                rows={[
                    { label: 'Konsumen', value: customer?.label ?? 'Belum dipilih' },
                    { label: 'Mobil', value: vehicle?.label ?? 'Belum dipilih' },
                    ...(summary.preview?.items.map((item) => ({
                        label: item.fruit_type === 'TBS' ? 'TBS' : 'Sortir',
                        value: `${weight(item.weight_grams)} × ${rupiah(item.buy_price_snapshot_idr ?? 0)}/kg`,
                    })) ?? []),
                    {
                        label: 'Anggota muat',
                        value: workers.map((worker) => worker.label).join(', '),
                    },
                    {
                        label: 'Total upah muat',
                        value: summary.preview
                            ? rupiah(summary.preview.total_loading_wage_idr)
                            : 'Belum dihitung',
                    },
                    {
                        label: 'Total hak konsumen',
                        value: summary.preview
                            ? rupiah(summary.preview.total_purchase_idr)
                            : 'Belum dihitung',
                    },
                ]}
                confirmLabel="Simpan timbangan"
                onConfirm={() => submit()}
                disabled={!ready}
            />
            <div className="weighing-actions-dock mobile-action-dock print:hidden">
                <div className="mx-auto min-w-0 max-w-7xl px-4 sm:px-8">
                    <div
                        role="group"
                        aria-label="Tindakan timbangan"
                        className="flex min-w-0 items-center justify-between gap-4 rounded-2xl border border-primary/15 bg-card px-3 py-3 shadow-lg shadow-primary/10 sm:px-5"
                    >
                        <p className="hidden max-w-md items-start gap-2 text-xs leading-relaxed text-muted-foreground lg:flex">
                            <Info aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
                            Periksa uang konsumen dan pembagian upah sebelum menyimpan.
                        </p>
                        <div className="grid min-w-0 w-full grid-cols-[auto_minmax(0,1fr)] gap-2 lg:w-auto lg:shrink-0 lg:gap-3">
                            <Button
                                variant="outline"
                                size="lg"
                                className="h-12 min-w-0 px-3 text-xs sm:px-5 sm:text-sm"
                                asChild
                            >
                                <Link href={route('weighings.index')}>Batal</Link>
                            </Button>
                            <Button
                                type="submit"
                                size="lg"
                                className="h-12 min-w-0 px-3 text-xs active:translate-y-px sm:px-5 sm:text-sm"
                                disabled={
                                    form.processing ||
                                    !summary.ready ||
                                    !summary.preview ||
                                    !form.data.customer_id ||
                                    !form.data.vehicle_id ||
                                    !!vehicleAssignment?.error ||
                                    !workers.length
                                }
                            >
                                {form.processing ? (
                                    <LoaderCircle
                                        aria-hidden="true"
                                        className="size-4 animate-spin motion-reduce:animate-none"
                                    />
                                ) : (
                                    <Check aria-hidden="true" className="size-4" />
                                )}
                                {form.processing ? 'Menyimpan...' : 'Periksa timbangan'}
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    );
}

import { FormEvent, useState } from 'react';
import { useForm } from '@inertiajs/react';
import { ArrowUpRight, Factory, Info, LoaderCircle, Sprout } from 'lucide-react';
import FormField from '@/components/common/FormField';
import ReferencePicker from '@/components/common/ReferencePicker';
import UnsavedChangesGuard from '@/components/common/UnsavedChangesGuard';
import InputError from '@/components/InputError';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import type { ReferenceOption } from '@/types/operations';

export default function PriceForm({
    localNow,
    factory = false,
}: {
    localNow: string;
    factory?: boolean;
}) {
    const [selected, setSelected] = useState<ReferenceOption | null>(null);
    const form = useForm({
        effective_from: localNow,
        tbs_buy_price_idr: '',
        sort_buy_price_idr: '',
        loading_rate_idr: '',
        notes: '',
        factory_id: '',
        tbs_sell_price_idr: '',
    });
    const fields = factory
        ? [{ key: 'tbs_sell_price_idr' as const, label: 'Harga TBS pabrik', min: 1 }]
        : [
              { key: 'tbs_buy_price_idr' as const, label: 'Harga beli TBS', min: 1 },
              { key: 'sort_buy_price_idr' as const, label: 'Harga beli sortir', min: 1 },
              { key: 'loading_rate_idr' as const, label: 'Upah muat', min: 0 },
          ];
    const Icon = factory ? Factory : Sprout;
    const titleId = factory ? 'factory-price-form-heading' : 'purchase-price-form-heading';

    function submit(e: FormEvent) {
        e.preventDefault();
        form.transform((data) =>
            factory
                ? {
                      effective_from: data.effective_from,
                      factory_id: data.factory_id,
                      tbs_sell_price_idr: data.tbs_sell_price_idr,
                  }
                : {
                      effective_from: data.effective_from,
                      tbs_buy_price_idr: data.tbs_buy_price_idr,
                      sort_buy_price_idr: data.sort_buy_price_idr,
                      loading_rate_idr: data.loading_rate_idr,
                      notes: data.notes,
                  },
        );
        form.post(route(factory ? 'prices.factory-store' : 'prices.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setSelected(null);
            },
        });
    }

    return (
        <form
            onSubmit={submit}
            aria-labelledby={titleId}
            aria-busy={form.processing}
            className="flex min-w-0 flex-col overflow-hidden rounded-2xl border bg-card"
        >
            <UnsavedChangesGuard dirty={form.isDirty && !form.processing} />
            <div className="flex items-start gap-3.5 border-b px-5 py-5 sm:px-6">
                <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-secondary text-primary">
                    <Icon aria-hidden="true" className="size-5" />
                </span>
                <div>
                    <h2 id={titleId} className="text-base font-semibold tracking-tight">
                        {factory ? 'Harga referensi pabrik' : 'Harga beli & upah muat'}
                    </h2>
                    <p className="mt-1 text-xs leading-relaxed text-muted-foreground">
                        {factory
                            ? 'Terbitkan tarif TBS untuk pabrik tujuan.'
                            : 'Terbitkan tarif pembelian dan upah per kilogram.'}
                    </p>
                </div>
            </div>
            <div className="flex-1 space-y-5 px-5 py-6 sm:px-6">
                {factory && (
                    <ReferencePicker
                        id="price_factory_id"
                        label="Pabrik"
                        url={route('references.index', 'factories')}
                        value={selected}
                        onChange={(option) => {
                            setSelected(option);
                            form.setData('factory_id', String(option?.id ?? ''));
                        }}
                        error={form.errors.factory_id}
                    />
                )}
                <FormField
                    field={{
                        key: factory ? 'factory_effective_from' : 'effective_from',
                        label: 'Mulai berlaku (WIB)',
                        type: 'datetime-local',
                        required: true,
                    }}
                    value={form.data.effective_from}
                    error={form.errors.effective_from}
                    onChange={(value) => form.setData('effective_from', value)}
                />
                <div className={cn('grid gap-4', !factory && 'sm:grid-cols-3')}>
                    {fields.map((field) => (
                        <div key={field.key} className="min-w-0">
                            <Label htmlFor={field.key}>{field.label}</Label>
                            <div className="relative mt-2">
                                <span
                                    aria-hidden="true"
                                    className="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-muted-foreground"
                                >
                                    Rp
                                </span>
                                <Input
                                    id={field.key}
                                    name={field.key}
                                    type="number"
                                    inputMode="numeric"
                                    min={field.min}
                                    max={100000000}
                                    step={1}
                                    required
                                    autoComplete="off"
                                    placeholder="0"
                                    value={form.data[field.key]}
                                    aria-invalid={!!form.errors[field.key]}
                                    aria-describedby={
                                        field.key +
                                        '-unit' +
                                        (form.errors[field.key] ? ' ' + field.key + '-error' : '')
                                    }
                                    onChange={(e) => form.setData(field.key, e.target.value)}
                                    className="h-11 pl-10 font-medium tabular-nums"
                                />
                            </div>
                            <p
                                id={field.key + '-unit'}
                                className="mt-1.5 text-[11px] text-muted-foreground"
                            >
                                Rupiah per kilogram
                            </p>
                            <InputError
                                id={field.key + '-error'}
                                message={form.errors[field.key]}
                                className="mt-2"
                            />
                        </div>
                    ))}
                </div>
                {!factory && (
                    <FormField
                        field={{
                            key: 'notes',
                            label: 'Catatan',
                            type: 'textarea',
                            required: false,
                            maxLength: 1000,
                        }}
                        value={form.data.notes}
                        error={form.errors.notes}
                        onChange={(value) => form.setData('notes', value)}
                    />
                )}
            </div>
            <div className="space-y-4 border-t bg-muted/25 px-5 py-5 sm:px-6">
                <p className="flex items-start gap-2 text-xs leading-relaxed text-muted-foreground">
                    <Info aria-hidden="true" className="mt-0.5 size-3.5 shrink-0" />
                    <span>
                        {factory
                            ? 'Harga referensi disimpan per pabrik dan waktu berlaku.'
                            : 'Setiap publikasi menambah versi. Tarif pada tiket sah tetap memakai harga yang dikunci sebelumnya.'}
                    </span>
                </p>
                <Button
                    type="submit"
                    size="lg"
                    className="w-full justify-between active:translate-y-px motion-reduce:transition-none"
                    disabled={form.processing}
                >
                    {form.processing ? 'Menerbitkan harga...' : 'Terbitkan versi harga'}
                    {form.processing ? (
                        <LoaderCircle
                            aria-hidden="true"
                            className="size-4 animate-spin motion-reduce:animate-none"
                        />
                    ) : (
                        <ArrowUpRight aria-hidden="true" className="size-4" />
                    )}
                </Button>
            </div>
        </form>
    );
}

import InputError from '@/components/InputError';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Info, Leaf } from 'lucide-react';

import type { WeighingFormModel } from '@/features/weighings/hooks/use-weighing-form';

export default function WeighingWeightsSection({ model }: { model: WeighingFormModel }) {
    const { form, summary, errors } = model;

    return (
        <>
            <section
                aria-labelledby="fruit-weights-heading"
                className="border-t bg-muted/20 px-5 py-6 sm:px-6"
            >
                <h3
                    id="fruit-weights-heading"
                    className="mb-5 flex items-center gap-2 text-sm font-semibold"
                >
                    <Leaf aria-hidden="true" className="size-4 text-primary" />
                    Berat buah
                </h3>
                <div className="grid gap-5 sm:grid-cols-2">
                    {(
                        [
                            { key: 'tbs_kg', label: 'Berat TBS (kg)' },
                            { key: 'sort_kg', label: 'Berat sortir (kg)' },
                        ] as const
                    ).map(({ key, label }) => {
                        const error = errors[key] ?? summary.errors[key];
                        return (
                            <div key={key} className="min-w-0">
                                <Label htmlFor={key}>{label}</Label>
                                <div className="relative mt-2">
                                    <Input
                                        id={key}
                                        name={key}
                                        type="text"
                                        inputMode="decimal"
                                        required
                                        maxLength={15}
                                        autoComplete="off"
                                        placeholder="Contoh: 1000,500"
                                        value={form.data[key]}
                                        aria-invalid={!!error}
                                        aria-describedby={
                                            key + '-hint' + (error ? ' ' + key + '-error' : '')
                                        }
                                        onChange={(e) => form.setData(key, e.target.value)}
                                        className="h-12 bg-card pr-12 text-lg font-semibold tabular-nums md:text-lg"
                                    />
                                    <span
                                        aria-hidden="true"
                                        className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-muted-foreground"
                                    >
                                        kg
                                    </span>
                                </div>
                                <p
                                    id={key + '-hint'}
                                    className="mt-2 text-[11px] text-muted-foreground"
                                >
                                    {key === 'tbs_kg'
                                        ? 'Berat tandan buah segar.'
                                        : 'Isi 0 bila tidak ada buah sortir.'}
                                </p>
                                <InputError id={key + '-error'} message={error} className="mt-2" />
                            </div>
                        );
                    })}
                </div>
                <p className="mt-4 flex items-start gap-2 text-xs leading-relaxed text-muted-foreground">
                    <Info aria-hidden="true" className="mt-0.5 size-3.5 shrink-0" />
                    Waktu timbang dan petugas dicatat otomatis saat disimpan.
                </p>
            </section>
        </>
    );
}

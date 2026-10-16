import ReferencePicker from '@/components/common/ReferencePicker';
import InputError from '@/components/InputError';
import { Button } from '@/components/ui/button';
import LoadingWorkerTags from '@/features/weighings/components/LoadingWorkerTags';
import { Users, X } from 'lucide-react';

import type { WeighingFormModel } from '@/features/weighings/hooks/use-weighing-form';

export default function WeighingWorkersSection({ model }: { model: WeighingFormModel }) {
    const { availableWorkers, driver, workers, form, summary, errors, workerError, updateWorkers } =
        model;

    return (
        <>
            <section
                aria-labelledby="loading-members-heading"
                className="border-t px-5 py-6 sm:px-6"
            >
                <div className="mb-2 flex items-center justify-between gap-3">
                    <h3
                        id="loading-members-heading"
                        className="flex items-center gap-2 text-sm font-semibold"
                    >
                        <Users aria-hidden="true" className="size-4 text-primary" />
                        Anggota muat
                    </h3>
                    <span
                        aria-live="polite"
                        aria-atomic="true"
                        className="rounded-md bg-secondary px-2 py-1 text-[11px] font-medium tabular-nums text-primary"
                    >
                        {workers.length} dipilih
                    </span>
                </div>
                <p
                    id="loading-members-help"
                    className="mb-5 text-xs leading-relaxed text-muted-foreground"
                >
                    {driver && 'Ketuk nama untuk memilih atau membatalkan pilihan. '}
                    Pilih minimal satu anggota. Upah TBS dan sortir dibagi rata kepada anggota yang
                    dipilih.
                </p>
                {driver ? (
                    <LoadingWorkerTags
                        options={availableWorkers}
                        selected={workers}
                        onChange={updateWorkers}
                        disabled={form.processing}
                        error={workerError}
                    />
                ) : (
                    <>
                        <ReferencePicker
                            key={workers.length}
                            id="worker_ids"
                            label="Tambah anggota muat"
                            url={route('references.index', 'loading-workers')}
                            value={null}
                            error={errors.worker_ids ?? summary.errors.worker_ids}
                            onChange={(option) => {
                                if (option && !workers.some((worker) => worker.id === option.id))
                                    updateWorkers([...workers, option]);
                            }}
                        />
                        <InputError
                            message={
                                Object.entries(errors).find(([key]) =>
                                    key.startsWith('worker_ids.'),
                                )?.[1]
                            }
                            className="mt-2"
                        />
                        {workers.length ? (
                            <ul className="mt-4 grid gap-2 sm:grid-cols-2">
                                {workers.map((worker) => (
                                    <li
                                        key={worker.id}
                                        className="flex min-w-0 items-center justify-between gap-2 rounded-lg border bg-muted/30 px-3 py-2"
                                    >
                                        <div className="flex min-w-0 items-center gap-2.5">
                                            <span
                                                aria-hidden="true"
                                                className="flex size-7 shrink-0 items-center justify-center rounded-md bg-secondary text-xs font-semibold text-primary"
                                            >
                                                {worker.label
                                                    .trim()
                                                    .charAt(0)
                                                    .toLocaleUpperCase('id-ID')}
                                            </span>
                                            <span className="break-words text-xs font-medium">
                                                {worker.label}
                                            </span>
                                        </div>
                                        <Button
                                            type="button"
                                            size="icon-sm"
                                            variant="ghost"
                                            aria-label={'Hapus anggota ' + worker.label}
                                            onClick={() =>
                                                updateWorkers(
                                                    workers.filter((item) => item.id !== worker.id),
                                                )
                                            }
                                        >
                                            <X aria-hidden="true" className="size-3.5" />
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="mt-4 rounded-lg border border-dashed px-4 py-3 text-xs text-muted-foreground">
                                Belum ada anggota dipilih. Tambahkan anggota untuk melihat pembagian
                                upah.
                            </p>
                        )}
                    </>
                )}
            </section>
        </>
    );
}

import { Check, Plus } from 'lucide-react';
import InputError from '@/components/InputError';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { ReferenceOption } from '@/types/operations';

export default function LoadingWorkerTags({
    options,
    selected,
    onChange,
    disabled = false,
    error,
}: {
    options: ReferenceOption[];
    selected: ReferenceOption[];
    onChange: (workers: ReferenceOption[]) => void;
    disabled?: boolean;
    error?: string;
}) {
    return (
        <fieldset
            disabled={disabled}
            aria-describedby={'loading-members-help' + (error ? ' loading-members-error' : '')}
            aria-invalid={!!error}
            className="min-w-0"
        >
            <legend className="sr-only">Pilih anggota muat</legend>
            {options.length ? (
                <div className="flex flex-wrap gap-2.5">
                    {options.map((worker) => {
                        const active = selected.some((item) => item.id === worker.id);
                        const Icon = active ? Check : Plus;
                        return (
                            <Button
                                key={worker.id}
                                type="button"
                                variant="outline"
                                aria-pressed={active}
                                disabled={disabled}
                                onClick={() =>
                                    onChange(
                                        active
                                            ? selected.filter((item) => item.id !== worker.id)
                                            : [...selected, worker],
                                    )
                                }
                                className={cn(
                                    'h-auto min-h-11 max-w-full shrink rounded-full px-4 py-2.5 text-left leading-snug whitespace-normal shadow-none transition-colors duration-150 active:scale-[0.98] motion-reduce:transform-none',
                                    active
                                        ? 'border-primary bg-primary text-primary-foreground hover:border-primary hover:bg-primary/90 hover:text-primary-foreground'
                                        : 'border-input bg-background text-foreground hover:border-primary/40 hover:bg-secondary hover:text-primary',
                                )}
                            >
                                <Icon aria-hidden="true" className="size-4 shrink-0" />
                                <span className="min-w-0 [overflow-wrap:anywhere]">
                                    {worker.label}
                                </span>
                            </Button>
                        );
                    })}
                </div>
            ) : (
                <p className="rounded-lg border border-dashed px-4 py-3 text-sm leading-relaxed text-muted-foreground">
                    Belum ada anggota muat aktif. Minta manajer menambahkan anggota muat.
                </p>
            )}
            <InputError id="loading-members-error" message={error} className="mt-3" />
        </fieldset>
    );
}

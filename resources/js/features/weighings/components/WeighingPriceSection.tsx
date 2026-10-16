import InputError from '@/components/InputError';
import { Button } from '@/components/ui/button';
import { rupiah } from '@/lib/currency';
import { dateTime } from '@/lib/dates';
import { Link } from '@inertiajs/react';
import { RefreshCw, Truck } from 'lucide-react';

import type { WeighingFormModel } from '@/features/weighings/hooks/use-weighing-form';

export default function WeighingPriceSection({ model }: { model: WeighingFormModel }) {
    const { manager, form, summary } = model;

    return (
        <>
            <section
                aria-labelledby="weighing-price-heading"
                className="border-t bg-secondary/25 px-5 py-6 sm:px-6"
            >
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h3
                        id="weighing-price-heading"
                        className="flex items-center gap-2 text-sm font-semibold"
                    >
                        <Truck aria-hidden="true" className="size-4 text-primary" />
                        Harga & upah hari ini
                    </h3>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        disabled={summary.busy || form.processing}
                        onClick={summary.refresh}
                    >
                        <RefreshCw
                            aria-hidden="true"
                            className={
                                'size-3.5 ' +
                                (summary.busy ? 'animate-spin motion-reduce:animate-none' : '')
                            }
                        />
                        Perbarui
                    </Button>
                </div>
                {summary.busy && (
                    <p role="status" className="mt-3 text-xs text-muted-foreground">
                        Memperbarui ringkasan...
                    </p>
                )}
                {summary.price && (
                    <>
                        <dl className="mt-4 grid gap-4 sm:grid-cols-3">
                            {(
                                [
                                    ['Harga TBS / kg', summary.price.tbs_buy_price_idr],
                                    ['Harga sortir / kg', summary.price.sort_buy_price_idr],
                                    ['Upah muat / kg', summary.price.loading_rate_idr],
                                ] as const
                            ).map(([label, value]) => (
                                <div key={label} className="min-w-0">
                                    <dt className="text-[11px] text-muted-foreground">{label}</dt>
                                    <dd className="mt-1.5 break-words text-sm font-semibold tabular-nums text-primary">
                                        {rupiah(value)}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                        <p className="mt-4 text-xs leading-relaxed text-muted-foreground">
                            Berlaku mulai {dateTime(summary.price.effective_from)} WIB. Harga dan
                            upah dikunci saat disimpan.
                        </p>
                    </>
                )}
                <InputError
                    message={summary.errors.price_schedule_id ?? summary.errors.operation}
                    className="mt-3"
                />
                {summary.errors.price_schedule_id && manager && (
                    <Link
                        className="mt-3 inline-block text-sm font-medium text-primary underline underline-offset-4"
                        href={route('prices.index')}
                    >
                        Buka Harga & Upah Muat
                    </Link>
                )}
            </section>
        </>
    );
}

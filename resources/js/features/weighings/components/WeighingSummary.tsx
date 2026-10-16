import { Info, Scale, Wallet } from 'lucide-react';
import { rupiah } from '@/lib/currency';
import { weight } from '@/lib/weight';
import type { Preview } from '@/types/operations';

export default function WeighingSummary({
    preview,
    busy = false,
}: {
    preview: Preview | null;
    busy?: boolean;
}) {
    return (
        <section
            className="overflow-hidden rounded-2xl border bg-card"
            aria-label="Ringkasan uang konsumen"
            aria-busy={busy}
        >
            <div className="flex items-center gap-3 border-b px-5 py-5 sm:px-6">
                <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-secondary text-primary">
                    <Wallet aria-hidden="true" className="size-4" />
                </span>
                <h2 className="text-base font-semibold tracking-tight">Uang konsumen</h2>
            </div>
            <div className="border-b bg-secondary/35 px-5 py-5 sm:px-6">
                <p className="text-xs text-muted-foreground">Total hak konsumen</p>
                {busy ? (
                    <div role="status" className="mt-3">
                        <span className="sr-only">Menghitung uang konsumen...</span>
                        <div
                            aria-hidden="true"
                            className="h-8 w-3/4 animate-pulse rounded-md bg-primary/10 motion-reduce:animate-none"
                        />
                    </div>
                ) : (
                    <p className="mt-2 break-words text-3xl font-semibold tracking-tight tabular-nums text-primary">
                        {preview ? rupiah(preview.total_purchase_idr) : 'Belum dihitung'}
                    </p>
                )}
            </div>
            {preview ? (
                <dl className="divide-y px-5 sm:px-6">
                    {preview.items.map((item) => (
                        <div
                            key={item.fruit_type}
                            className="flex flex-wrap items-start justify-between gap-3 py-4 text-sm"
                        >
                            <dt className="min-w-0">
                                <span className="font-medium">
                                    {item.fruit_type === 'TBS' ? 'TBS' : 'Sortir'}
                                </span>
                                <span className="mt-1.5 block text-[11px] leading-relaxed text-muted-foreground">
                                    {weight(item.weight_grams)} ×{' '}
                                    {rupiah(item.buy_price_snapshot_idr)} / kg
                                </span>
                            </dt>
                            <dd className="break-words font-semibold tabular-nums text-primary">
                                {rupiah(item.purchase_amount_idr)}
                            </dd>
                        </div>
                    ))}
                </dl>
            ) : (
                !busy && (
                    <div className="flex items-start gap-2.5 px-5 py-5 text-xs leading-relaxed text-muted-foreground sm:px-6">
                        <Scale aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
                        Isi berat buah untuk melihat nilai timbang dan rincian uang konsumen.
                    </div>
                )
            )}
            <p className="flex items-start gap-2 border-t px-5 py-4 text-xs leading-relaxed text-muted-foreground sm:px-6">
                <Info aria-hidden="true" className="mt-0.5 size-3.5 shrink-0" />
                Upah muat ditanggung toke. Pembayaran konsumen dicatat terpisah di Uang Penimbang.
            </p>
        </section>
    );
}

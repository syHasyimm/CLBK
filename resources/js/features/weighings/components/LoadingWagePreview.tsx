import { Coins, Users } from 'lucide-react';
import { rupiah } from '@/lib/currency';
import { weight } from '@/lib/weight';
import type { Loader } from '@/types/operations';

export default function LoadingWagePreview({
    loaders,
    names,
    basis,
    rate,
    total,
}: {
    loaders: Loader[];
    names: Record<number, string>;
    basis: number;
    rate: number;
    total: number;
}) {
    return (
        <section
            className="overflow-hidden rounded-2xl border bg-card"
            aria-label="Pembagian upah muat"
        >
            <div className="flex items-center gap-3 border-b px-5 py-5 sm:px-6">
                <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-secondary text-primary">
                    <Users aria-hidden="true" className="size-4" />
                </span>
                <div>
                    <h2 className="text-base font-semibold tracking-tight">Pembagian upah muat</h2>
                    <p className="mt-1 text-[11px] text-muted-foreground">
                        {loaders.length} anggota muat
                    </p>
                </div>
            </div>
            <div className="border-b bg-muted/25 px-5 py-4 sm:px-6">
                <dl className="flex flex-wrap items-center justify-between gap-2">
                    <dt className="flex items-center gap-2 text-xs text-muted-foreground">
                        <Coins aria-hidden="true" className="size-4" />
                        Total upah
                    </dt>
                    <dd className="break-words text-lg font-semibold tabular-nums text-primary">
                        {rupiah(total)}
                    </dd>
                </dl>
                <p className="mt-2 text-[11px] leading-relaxed tabular-nums text-muted-foreground">
                    {weight(basis)} × {rupiah(rate)} / kg
                </p>
            </div>
            <ul className="divide-y px-5 sm:px-6">
                {loaders.map((loader) => (
                    <li
                        key={loader.worker_id}
                        className="flex flex-wrap items-center justify-between gap-3 py-4 text-sm"
                    >
                        <div className="min-w-0">
                            <p className="break-words font-medium">{names[loader.worker_id]}</p>
                            <p className="mt-1 text-[11px] tabular-nums text-muted-foreground">
                                Bagian 1/{loader.member_count_snapshot}
                            </p>
                        </div>
                        <span className="break-words font-semibold tabular-nums text-primary">
                            {rupiah(loader.share_amount_idr)}
                        </span>
                    </li>
                ))}
            </ul>
            {!loaders.length && (
                <p role="alert" className="px-5 py-4 text-xs text-destructive sm:px-6">
                    Pilih minimal satu anggota muat.
                </p>
            )}
            <p className="border-t px-5 py-4 text-[11px] leading-relaxed text-muted-foreground sm:px-6">
                Jika ada sisa pembulatan rupiah, sistem membaginya otomatis agar jumlah upah tetap
                sesuai total.
            </p>
        </section>
    );
}

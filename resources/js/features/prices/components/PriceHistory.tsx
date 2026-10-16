import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Clock3, History } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { dateTime } from '@/lib/dates';
import { rupiah } from '@/lib/currency';
import type { Price, FactoryPrice, PaginatedData } from '@/types/operations';

type Props = {
    serverNow: string;
    currentPriceId?: number;
} & (
    | { kind: 'purchase'; records: PaginatedData<Price> }
    | { kind: 'factory'; records: PaginatedData<FactoryPrice> }
);

export default function PriceHistory(props: Props) {
    const { records, serverNow, currentPriceId } = props;
    const purchase = props.kind === 'purchase';
    const title = purchase ? 'Versi harga beli & upah muat' : 'Riwayat harga pabrik';
    const rows =
        props.kind === 'purchase'
            ? props.records.data.map((price) => ({
                  id: price.id,
                  effectiveFrom: price.effective_from,
                  factory: null,
                  notes: price.notes,
                  amounts: [
                      { label: 'TBS / kg', value: price.tbs_buy_price_idr },
                      { label: 'Sortir / kg', value: price.sort_buy_price_idr },
                      { label: 'Upah / kg', value: price.loading_rate_idr },
                  ],
              }))
            : props.records.data.map((price) => ({
                  id: price.id,
                  effectiveFrom: price.effective_from,
                  factory: price.factory.name,
                  notes: null,
                  amounts: [{ label: 'TBS / kg', value: price.tbs_sell_price_idr }],
              }));
    const now = new Date(serverNow).getTime();

    function status(id: number, effectiveFrom: string) {
        if (purchase && id === currentPriceId) {
            return (
                <span className="rounded-md bg-secondary px-2 py-1 text-[11px] font-medium text-primary">
                    Aktif hari ini
                </span>
            );
        }
        if (new Date(effectiveFrom).getTime() > now) {
            return (
                <span className="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-[11px] font-medium text-muted-foreground">
                    <Clock3 aria-hidden="true" className="size-3" />
                    Terjadwal
                </span>
            );
        }
        return (
            <span className="text-[11px] text-muted-foreground">
                {purchase ? 'Riwayat' : 'Diterbitkan'}
            </span>
        );
    }

    return (
        <div className="overflow-hidden rounded-2xl border bg-card">
            {rows.length ? (
                <>
                    <div className="hidden overflow-x-auto lg:block">
                        <table className="w-full text-left text-sm">
                            <caption className="sr-only">{title}</caption>
                            <thead className="border-b bg-muted/50 text-xs text-muted-foreground">
                                <tr>
                                    <th
                                        scope="col"
                                        className="whitespace-nowrap px-6 py-4 font-medium"
                                    >
                                        Versi & waktu berlaku (WIB)
                                    </th>
                                    {!purchase && (
                                        <th scope="col" className="px-5 py-4 font-medium">
                                            Pabrik
                                        </th>
                                    )}
                                    {(purchase
                                        ? ['TBS / kg', 'Sortir / kg', 'Upah / kg']
                                        : ['TBS / kg']
                                    ).map((label) => (
                                        <th
                                            key={label}
                                            scope="col"
                                            className="whitespace-nowrap px-5 py-4 text-right font-medium"
                                        >
                                            {label}
                                        </th>
                                    ))}
                                    {purchase && (
                                        <th scope="col" className="px-6 py-4 font-medium">
                                            Catatan
                                        </th>
                                    )}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {rows.map((row) => (
                                    <tr
                                        key={row.id}
                                        className={cn(
                                            'transition-colors hover:bg-muted/35',
                                            purchase &&
                                                row.id === currentPriceId &&
                                                'bg-secondary/20',
                                        )}
                                    >
                                        <th scope="row" className="px-6 py-5 text-left font-normal">
                                            <div className="flex items-center gap-2.5">
                                                <span className="whitespace-nowrap font-medium">
                                                    Versi #{row.id}
                                                </span>
                                                {status(row.id, row.effectiveFrom)}
                                            </div>
                                            <p className="mt-2 whitespace-nowrap text-xs text-muted-foreground">
                                                {dateTime(row.effectiveFrom)}
                                            </p>
                                        </th>
                                        {!purchase && (
                                            <td className="px-5 py-5 font-medium">{row.factory}</td>
                                        )}
                                        {row.amounts.map((amount) => (
                                            <td
                                                key={amount.label}
                                                className="whitespace-nowrap px-5 py-5 text-right font-semibold tabular-nums text-primary"
                                            >
                                                {rupiah(amount.value)}
                                            </td>
                                        ))}
                                        {purchase && (
                                            <td className="max-w-64 px-6 py-5 text-xs leading-relaxed break-words text-muted-foreground">
                                                {row.notes || 'Tanpa catatan'}
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="lg:hidden">
                        <h3 className="sr-only">{title}</h3>
                        <ul className="divide-y">
                            {rows.map((row) => (
                                <li
                                    key={row.id}
                                    className={cn(
                                        'px-5 py-5',
                                        purchase && row.id === currentPriceId && 'bg-secondary/20',
                                    )}
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <p className="text-sm font-semibold">
                                            {row.factory ?? `Versi #${row.id}`}
                                        </p>
                                        {status(row.id, row.effectiveFrom)}
                                    </div>
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        {dateTime(row.effectiveFrom)} WIB
                                        {!purchase && ` / Versi #${row.id}`}
                                    </p>
                                    <dl
                                        className={cn('mt-4 grid gap-3', purchase && 'grid-cols-3')}
                                    >
                                        {row.amounts.map((amount) => (
                                            <div key={amount.label}>
                                                <dt className="text-[11px] text-muted-foreground">
                                                    {amount.label}
                                                </dt>
                                                <dd className="mt-1 text-sm font-semibold break-all tabular-nums text-primary">
                                                    {rupiah(amount.value)}
                                                </dd>
                                            </div>
                                        ))}
                                    </dl>
                                    {row.notes && (
                                        <p className="mt-4 text-xs leading-relaxed break-words text-muted-foreground">
                                            {row.notes}
                                        </p>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </div>
                </>
            ) : (
                <div className="px-6 py-14 text-center">
                    <span className="mx-auto mb-4 flex size-12 items-center justify-center rounded-2xl bg-secondary text-primary">
                        <History aria-hidden="true" className="size-5" />
                    </span>
                    <h3 className="text-sm font-semibold">
                        {purchase
                            ? 'Belum ada versi harga beli'
                            : 'Belum ada harga referensi pabrik'}
                    </h3>
                    <p className="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-muted-foreground">
                        Riwayat akan tampil setelah harga diterbitkan.
                    </p>
                </div>
            )}
            <div className="flex flex-wrap items-center justify-between gap-3 border-t px-5 py-4 text-xs text-muted-foreground sm:px-6">
                <p className="tabular-nums">
                    {records.from != null && records.to != null
                        ? `${records.from}-${records.to} dari ${records.total} versi`
                        : `0 dari ${records.total} versi`}
                </p>
                <div className="flex items-center gap-2">
                    <span className="mr-1 tabular-nums">
                        Halaman {records.current_page} / {records.last_page}
                    </span>
                    {[
                        {
                            label: 'Halaman sebelumnya',
                            url: records.prev_page_url,
                            icon: ChevronLeft,
                        },
                        {
                            label: 'Halaman berikutnya',
                            url: records.next_page_url,
                            icon: ChevronRight,
                        },
                    ].map(({ label, url, icon: Icon }) =>
                        url ? (
                            <Button key={label} asChild size="icon-sm" variant="outline">
                                <Link href={url} preserveScroll preserveState aria-label={label}>
                                    <Icon aria-hidden="true" className="size-4" />
                                </Link>
                            </Button>
                        ) : (
                            <Button
                                key={label}
                                size="icon-sm"
                                variant="outline"
                                disabled
                                aria-label={label}
                            >
                                <Icon aria-hidden="true" className="size-4" />
                            </Button>
                        ),
                    )}
                </div>
            </div>
        </div>
    );
}

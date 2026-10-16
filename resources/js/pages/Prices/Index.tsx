import { useState } from 'react';
import { Head } from '@inertiajs/react';
import { CalendarDays, Check, Coins, History, Leaf, Scale, Truck } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import PageHeading from '@/components/common/PageHeading';
import PriceForm from '@/features/prices/components/PriceForm';
import PriceHistory from '@/features/prices/components/PriceHistory';
import { dateTime } from '@/lib/dates';
import { rupiah } from '@/lib/currency';
import { cn } from '@/lib/utils';
import type { Price, FactoryPrice, PaginatedData } from '@/types/operations';

export default function Index({
    prices,
    factoryPrices,
    currentPrice,
    serverNow,
    localNow,
    can,
}: {
    prices: PaginatedData<Price>;
    factoryPrices: PaginatedData<FactoryPrice>;
    currentPrice: Price | null;
    serverNow: string;
    localNow: string;
    can: { publishPrice: boolean; publishFactoryPrice: boolean };
}) {
    const [history, setHistory] = useState<'purchase' | 'factory'>('purchase');
    const today = new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        timeZone: 'Asia/Jakarta',
    }).format(new Date(serverNow));

    return (
        <AppLayout
            header={
                <PageHeading
                    title="Harga Sawit & Upah Muat"
                    description="Kelola tarif pembelian, upah muat, dan harga referensi pabrik."
                    action={
                        <div className="flex items-center gap-2 rounded-lg border bg-card px-3.5 py-2.5 text-sm text-muted-foreground">
                            <CalendarDays aria-hidden="true" className="size-4 text-primary" />
                            {today}
                            <span className="border-l pl-2 text-xs">WIB</span>
                        </div>
                    }
                />
            }
        >
            <Head title="Harga Sawit & Upah Muat" />
            <section
                aria-labelledby="current-prices-heading"
                className="mb-8 overflow-hidden rounded-2xl border bg-card"
            >
                <div className="flex flex-wrap items-center justify-between gap-3 border-b bg-secondary/40 px-5 py-4 sm:px-6">
                    <div className="flex items-center gap-2.5">
                        <Coins aria-hidden="true" className="size-4 text-primary" />
                        <h2 id="current-prices-heading" className="text-sm font-semibold">
                            Tarif berlaku hari ini
                        </h2>
                    </div>
                    <span className="inline-flex items-center gap-1.5 text-xs font-medium text-primary">
                        {currentPrice && <Check aria-hidden="true" className="size-3.5" />}
                        {currentPrice ? `Versi #${currentPrice.id} aktif` : 'Belum ada tarif aktif'}
                    </span>
                </div>
                <dl className="grid divide-y lg:grid-cols-3 lg:divide-x lg:divide-y-0">
                    {[
                        {
                            label: 'Harga beli TBS',
                            value: currentPrice?.tbs_buy_price_idr,
                            icon: Leaf,
                        },
                        {
                            label: 'Harga beli sortir',
                            value: currentPrice?.sort_buy_price_idr,
                            icon: Scale,
                        },
                        { label: 'Upah muat', value: currentPrice?.loading_rate_idr, icon: Truck },
                    ].map(({ label, value, icon: Icon }) => (
                        <div
                            key={label}
                            className="flex items-start justify-between gap-3 px-5 py-5 sm:px-6 sm:py-6"
                        >
                            <div className="min-w-0">
                                <dt className="text-sm text-muted-foreground">{label}</dt>
                                <dd className="mt-2 flex flex-wrap items-baseline gap-x-2 gap-y-1">
                                    <span
                                        className={cn(
                                            'min-w-0 break-words font-semibold tracking-tight tabular-nums',
                                            value == null
                                                ? 'text-xl text-muted-foreground'
                                                : 'text-2xl text-primary lg:text-3xl',
                                        )}
                                    >
                                        {value == null ? 'Belum tersedia' : rupiah(value)}
                                    </span>
                                    {value != null && (
                                        <span className="text-xs text-muted-foreground">/ kg</span>
                                    )}
                                </dd>
                            </div>
                            <Icon
                                aria-hidden="true"
                                className="mt-1 size-5 shrink-0 text-primary/45"
                            />
                        </div>
                    ))}
                </dl>
                <p className="border-t px-5 py-3 text-xs leading-relaxed text-muted-foreground sm:px-6">
                    {currentPrice
                        ? `Berlaku sejak ${dateTime(currentPrice.effective_from)} WIB. Tarif mengikuti tanggal usaha dan waktu timbang.`
                        : 'Harga beli wajib diterbitkan untuk setiap tanggal usaha. Tarif hari sebelumnya tidak otomatis digunakan.'}
                </p>
            </section>
            {(can.publishPrice || can.publishFactoryPrice) && (
                <div
                    className={cn(
                        'mb-10 grid items-stretch gap-6',
                        can.publishPrice && can.publishFactoryPrice && 'xl:grid-cols-[1.2fr_1fr]',
                    )}
                >
                    {can.publishPrice && <PriceForm localNow={localNow} />}
                    {can.publishFactoryPrice && <PriceForm localNow={localNow} factory />}
                </div>
            )}
            <section aria-labelledby="price-history-heading">
                <div className="mb-5 flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h2
                            id="price-history-heading"
                            className="flex items-center gap-2.5 text-lg font-semibold tracking-tight"
                        >
                            <History aria-hidden="true" className="size-5 text-primary" />
                            Riwayat penerbitan harga
                        </h2>
                        <p className="mt-1.5 text-sm text-muted-foreground">
                            Setiap perubahan tersimpan sebagai versi harga baru.
                        </p>
                    </div>
                    <div
                        role="group"
                        aria-label="Jenis riwayat harga"
                        className="inline-flex w-full gap-1 rounded-xl border bg-muted/70 p-1 sm:w-auto"
                    >
                        {(
                            [
                                { key: 'purchase', label: 'Harga beli', count: prices.total },
                                {
                                    key: 'factory',
                                    label: 'Harga pabrik',
                                    count: factoryPrices.total,
                                },
                            ] as const
                        ).map(({ key, label, count }) => (
                            <button
                                key={key}
                                type="button"
                                aria-pressed={history === key}
                                aria-controls="price-history-content"
                                onClick={() => setHistory(key)}
                                className={cn(
                                    'flex flex-1 items-center justify-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring sm:flex-none',
                                    history === key
                                        ? 'bg-card text-primary shadow-xs'
                                        : 'text-muted-foreground hover:bg-card/60 hover:text-foreground',
                                )}
                            >
                                {label}
                                <span className="rounded-md bg-secondary px-1.5 py-0.5 text-[11px] tabular-nums text-primary">
                                    {count}
                                </span>
                            </button>
                        ))}
                    </div>
                </div>
                <div id="price-history-content">
                    {history === 'purchase' ? (
                        <PriceHistory
                            kind="purchase"
                            records={prices}
                            currentPriceId={currentPrice?.id}
                            serverNow={serverNow}
                        />
                    ) : (
                        <PriceHistory
                            kind="factory"
                            records={factoryPrices}
                            serverNow={serverNow}
                        />
                    )}
                </div>
            </section>
        </AppLayout>
    );
}

import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowUpRight, Check, Plus, ReceiptText } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import PageHeading from '@/components/common/PageHeading';
import WeighingOverview, {
    type WeighingListSummary,
} from '@/features/weighings/components/WeighingOverview';
import WeighingFilters from '@/features/weighings/components/WeighingFilters';
import WeighingsTable from '@/features/weighings/components/WeighingsTable';
import { Button } from '@/components/ui/button';
import { rupiah } from '@/lib/currency';
import type { Weighing, PaginatedData } from '@/types/operations';

export default function Index({
    weighings,
    filters,
    savedWeighing,
    summary,
}: {
    weighings: PaginatedData<Weighing>;
    filters: Record<string, string>;
    savedWeighing: { id: number; number: string; total_purchase_idr: number } | null;
    summary: WeighingListSummary;
}) {
    const cashier = usePage().props.auth.user.role === 'cashier';
    const filtered = Object.values(filters).some(Boolean);

    return (
        <AppLayout
            header={
                <PageHeading
                    title="Timbang Sawit"
                    description="Pantau berat buah, hak konsumen, dan upah anggota pada setiap tiket timbangan."
                    action={
                        !cashier ? (
                            <Button
                                asChild
                                size="lg"
                                className="h-11 rounded-lg active:translate-y-px"
                            >
                                <Link href={route('weighings.create')}>
                                    <Plus aria-hidden="true" className="size-4" />
                                    Timbang sawit
                                </Link>
                            </Button>
                        ) : undefined
                    }
                />
            }
        >
            <Head title="Timbang Sawit" />
            {savedWeighing && (
                <section
                    role="status"
                    className="mb-6 flex flex-wrap items-center justify-between gap-5 rounded-2xl border border-primary/20 bg-secondary/60 px-5 py-5 sm:px-6"
                >
                    <div className="flex items-start gap-3">
                        <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-card text-primary">
                            <Check aria-hidden="true" className="size-5" />
                        </span>
                        <div>
                            <h2 className="text-sm font-semibold text-primary">
                                Timbangan {savedWeighing.number} tersimpan
                            </h2>
                            <p className="mt-1.5 text-xs leading-relaxed text-muted-foreground">
                                Uang konsumen{' '}
                                <span className="font-semibold tabular-nums text-primary">
                                    {rupiah(savedWeighing.total_purchase_idr)}
                                </span>
                                . Pembayaran dilanjutkan di Uang Penimbang.
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {!cashier && (
                            <Button asChild size="sm">
                                <Link href={route('weighings.create')}>
                                    <Plus aria-hidden="true" className="size-4" />
                                    Catat Timbangan Baru
                                </Link>
                            </Button>
                        )}
                        <Button asChild size="sm" variant="outline">
                            <Link href={route('weighings.show', savedWeighing.id)}>
                                Lihat Rincian
                                <ArrowUpRight aria-hidden="true" className="size-4" />
                            </Link>
                        </Button>
                    </div>
                </section>
            )}
            <WeighingOverview summary={summary} />
            <section
                aria-labelledby="weighing-list-heading"
                className="overflow-hidden rounded-2xl border bg-card"
            >
                <div className="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-5 sm:px-6">
                    <div className="flex items-start gap-3">
                        <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-secondary text-primary">
                            <ReceiptText aria-hidden="true" className="size-5" />
                        </span>
                        <div>
                            <h2
                                id="weighing-list-heading"
                                className="text-base font-semibold tracking-tight"
                            >
                                Daftar timbangan
                            </h2>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Harga dan pembagian upah terkunci pada tiket sah.
                            </p>
                        </div>
                    </div>
                    <span className="rounded-lg bg-muted px-3 py-1.5 text-xs font-medium tabular-nums text-muted-foreground">
                        {weighings.total.toLocaleString('id-ID')} tiket
                    </span>
                </div>
                <WeighingFilters filters={filters} cashier={cashier} />
                <WeighingsTable records={weighings} cashier={cashier} filtered={filtered} />
            </section>
        </AppLayout>
    );
}

import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import PageHeading from '@/components/common/PageHeading';
import OperationalFilters from '@/components/common/OperationalFilters';
import PayeeSummaryCard from '@/features/finance/components/PayeeSummaryCard';
import PayrollWorklist from '@/features/finance/components/PayrollWorklist';
import { Button } from '@/components/ui/button';
import type { FinanceKind, PayeeCard, PaginatedData } from '@/types/finance';
import { financeLabels, worklistRoute } from '@/types/finance';
export default function Worklist({
    kind,
    cards,
    filters,
}: {
    kind: FinanceKind;
    cards: PaginatedData<PayeeCard>;
    filters: Record<string, string>;
}) {
    if (kind === 'payroll') return <PayrollWorklist cards={cards} filters={filters} />;
    return (
        <AppLayout
            header={
                <PageHeading
                    title={kind === 'customer' ? 'Uang Penimbang' : financeLabels[kind]}
                    description={
                        kind === 'customer'
                            ? 'Timbangan yang belum lunas. Pembayaran sebagian tetap tampil sampai seluruh hak konsumen dibayar.'
                            : 'Hak upah berasal dari pembagian per tiket. Pilih periode pekerjaan untuk pembayaran.'
                    }
                    action={
                        <Button variant="outline" asChild>
                            <Link href={route('finance.index', { kind })}>Riwayat voucher</Link>
                        </Button>
                    }
                />
            }
        >
            <Head title={financeLabels[kind]} />
            <OperationalFilters
                url={route(worklistRoute(kind))}
                filters={filters}
                statuses={[]}
                dates
            />
            <div className="grid items-start gap-5 lg:grid-cols-2 xl:grid-cols-3">
                {cards.data.map((card) => (
                    <PayeeSummaryCard key={card.id} kind={kind} card={card} filters={filters} />
                ))}
            </div>
            {!cards.data.length && (
                <div className="rounded-xl border bg-card p-12 text-center text-sm text-muted-foreground">
                    Tidak ada timbangan yang belum lunas pada filter ini. Pembayaran yang sudah
                    dicatat tersedia di Riwayat voucher.
                </div>
            )}
            <div className="mt-5 flex flex-wrap justify-between gap-3 text-sm">
                <p>
                    {cards.total} penerima · Halaman {cards.current_page} / {cards.last_page}
                </p>
                <div className="flex gap-2">
                    {[
                        ['Sebelumnya', cards.prev_page_url],
                        ['Berikutnya', cards.next_page_url],
                    ].map(([label, url]) => (
                        <Button
                            key={label}
                            variant="outline"
                            size="sm"
                            disabled={!url}
                            asChild={!!url}
                        >
                            {url ? <Link href={url}>{label}</Link> : label}
                        </Button>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}

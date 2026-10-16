import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import PageHeading from '@/components/common/PageHeading';
import OperationalFilters from '@/components/common/OperationalFilters';
import FinanceHistoryTable from '@/features/finance/components/FinanceHistoryTable';
import { Button } from '@/components/ui/button';
import type { FinanceKind, FinanceDocument, PaginatedData } from '@/types/finance';
import { financeLabels, worklistRoute } from '@/types/finance';
export default function Index({
    kind,
    documents,
    filters,
}: {
    kind: FinanceKind;
    documents: PaginatedData<FinanceDocument>;
    filters: Record<string, string>;
}) {
    return (
        <AppLayout
            header={
                <PageHeading
                    title={financeLabels[kind]}
                    description={
                        kind === 'expense'
                            ? 'Biaya operasional harian beserta pembayaran kasnya.'
                            : 'Riwayat voucher sah dan pembalikan tetap tersimpan.'
                    }
                    action={
                        kind === 'expense' ? (
                            <Button asChild>
                                <Link href={route('daily-expenses.create')}>Catat pengeluaran</Link>
                            </Button>
                        ) : kind === 'customer' || kind === 'payroll' ? (
                            <Button asChild>
                                <Link href={route(worklistRoute(kind))}>Daftar penerima</Link>
                            </Button>
                        ) : (
                            <div className="flex flex-wrap gap-2">
                                <Button variant="outline" asChild>
                                    <Link href={route('factory-sales.index')}>Nota pabrik</Link>
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href={route('sort-sales.index')}>Nota sortir</Link>
                                </Button>
                            </div>
                        )
                    }
                />
            }
        >
            <Head title={financeLabels[kind]} />
            <OperationalFilters
                url={route('finance.index', { kind })}
                filters={filters}
                statuses={['posted', 'reversed']}
                dates
            />
            <FinanceHistoryTable kind={kind} documents={documents} />
        </AppLayout>
    );
}

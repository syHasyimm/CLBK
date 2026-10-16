import { Link } from '@inertiajs/react';
import DataTable from '@/components/common/DataTable';
import { Badge } from '@/components/ui/badge';
import { dateTime } from '@/lib/dates';
import { rupiah } from '@/lib/currency';
import type { FinanceKind, FinanceDocument, PaginatedData } from '@/types/finance';
export default function FinanceHistoryTable({
    kind,
    documents,
}: {
    kind: FinanceKind;
    documents: PaginatedData<FinanceDocument>;
}) {
    return (
        <DataTable
            records={documents}
            columns={[
                {
                    label: 'Voucher',
                    render: (r) => (
                        <Link
                            className="font-medium text-primary underline"
                            href={route('finance.show', { kind, document: r.id })}
                        >
                            {r.number}
                        </Link>
                    ),
                },
                ...(kind === 'receipt'
                    ? [
                          {
                              label: 'Penjualan',
                              render: (r: FinanceDocument) => {
                                  const saleId = r.factory_sale_id ?? r.sort_sale_id;
                                  if (saleId == null) return '—';
                                  return (
                                      <Link
                                          className="text-primary hover:underline"
                                          href={route(
                                              r.factory_sale_id
                                                  ? 'factory-sales.show'
                                                  : 'sort-sales.show',
                                              saleId,
                                          )}
                                      >
                                          {r.factory_sale_id ? 'Nota pabrik' : 'Nota sortir'}
                                      </Link>
                                  );
                              },
                          },
                      ]
                    : []),
                { label: 'Tanggal', render: (r) => dateTime(r.occurred_at) },
                { label: 'Nominal', render: (r) => rupiah(r.amount_idr) },
                { label: 'Kas', render: (r) => r.cash_account.name },
                {
                    label: 'Status',
                    render: (r) => (
                        <Badge variant={r.status === 'posted' ? 'secondary' : 'outline'}>
                            {r.status === 'posted' ? 'Sah' : 'Dibalik'}
                        </Badge>
                    ),
                },
            ]}
        />
    );
}

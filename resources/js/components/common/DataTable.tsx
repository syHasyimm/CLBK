import { ReactNode } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import type { PaginatedData } from '@/types/master-data';
import { cn } from '@/lib/utils';

export default function DataTable<T extends { id: number | string }>({
    records,
    columns,
    empty = 'Belum ada transaksi.',
    mobileCards: useMobileCards,
    showPagination = true,
}: {
    records: PaginatedData<T>;
    columns: { label: string; render: (row: T) => ReactNode }[];
    empty?: string;
    mobileCards?: boolean;
    showPagination?: boolean;
}) {
    const role = usePage().props.auth.user.role;
    const mobileCards = useMobileCards ?? role !== 'manager';
    return (
        <div className="min-w-0 overflow-hidden rounded-xl border bg-card">
            {mobileCards && (
                <div className="md:hidden print:hidden">
                    {records.data.length ? (
                        <ul className="divide-y">
                            {records.data.map((row) => (
                                <li key={row.id} className="px-4 py-5">
                                    <dl className="space-y-3">
                                        {columns.map((column, i) => (
                                            <div
                                                key={i}
                                                className="grid min-w-0 grid-cols-[minmax(0,0.85fr)_minmax(0,1.35fr)] items-start gap-3"
                                            >
                                                <dt className="pt-0.5 text-xs leading-relaxed text-muted-foreground">
                                                    {column.label || 'Aksi'}
                                                </dt>
                                                <dd className="staff-record-value min-w-0 text-sm break-words [overflow-wrap:anywhere]">
                                                    {column.render(row)}
                                                </dd>
                                            </div>
                                        ))}
                                    </dl>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="px-5 py-12 text-center text-sm text-muted-foreground">
                            {empty}
                        </p>
                    )}
                </div>
            )}
            <div className={cn('overflow-x-auto', mobileCards && 'hidden md:block print:block')}>
                <table className="w-full text-left text-sm">
                    <thead className="border-b bg-muted/60 text-muted-foreground">
                        <tr>
                            {columns.map((column, i) => (
                                <th
                                    key={i}
                                    scope="col"
                                    className="whitespace-nowrap px-5 py-4 font-medium"
                                >
                                    {column.label}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {records.data.map((row) => (
                            <tr key={row.id}>
                                {columns.map((column, i) => (
                                    <td key={i} className="px-5 py-4">
                                        {column.render(row)}
                                    </td>
                                ))}
                            </tr>
                        ))}
                        {!records.data.length && (
                            <tr>
                                <td
                                    colSpan={columns.length}
                                    className="px-5 py-12 text-center text-muted-foreground"
                                >
                                    {empty}
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
            {showPagination && (
                <div className="flex flex-wrap items-center justify-between gap-3 border-t px-5 py-4 text-xs text-muted-foreground print:hidden">
                    <span>
                        {records.total} data · Halaman {records.current_page} dari{' '}
                        {records.last_page}
                    </span>
                    <div className="flex gap-2">
                        {[
                            ['Sebelumnya', records.prev_page_url],
                            ['Berikutnya', records.next_page_url],
                        ].map(([label, url]) =>
                            url ? (
                                <Button key={label} asChild size="sm" variant="outline">
                                    <Link href={url} preserveScroll>
                                        {label}
                                    </Link>
                                </Button>
                            ) : (
                                <Button key={label} size="sm" variant="outline" disabled>
                                    {label}
                                </Button>
                            ),
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}

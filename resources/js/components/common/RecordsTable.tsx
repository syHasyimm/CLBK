import { Link } from '@inertiajs/react';
import { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ChevronLeft, ChevronRight, FolderOpen } from 'lucide-react';
import type { MasterRecord, PaginatedData } from '@/types/master-data';

export default function RecordsTable({
    columns,
    records,
    actions,
    emptyLabel,
}: {
    columns: Record<string, string>;
    records: PaginatedData<MasterRecord>;
    actions: (record: MasterRecord) => ReactNode;
    emptyLabel: string;
}) {
    return (
        <div className="overflow-hidden rounded-xl border bg-card">
            <div className="divide-y lg:hidden" aria-label={emptyLabel}>
                {records.data.map((record) => (
                    <article key={record.id} className="p-4">
                        <div className="mb-4 flex items-start justify-between gap-3">
                            <h2 className="min-w-0 break-words text-base font-semibold">
                                {formatValue(
                                    Object.keys(columns)[0],
                                    record[Object.keys(columns)[0]],
                                )}
                            </h2>
                            <Badge variant={record.is_active ? 'secondary' : 'outline'}>
                                {record.is_active ? 'Aktif' : 'Nonaktif'}
                            </Badge>
                        </div>
                        <dl className="space-y-3">
                            {Object.entries(columns)
                                .slice(1)
                                .map(([key, label]) => (
                                    <div
                                        key={key}
                                        className="grid grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)] gap-3 text-sm"
                                    >
                                        <dt className="text-muted-foreground">{label}</dt>
                                        <dd className="min-w-0 break-words">
                                            {formatValue(key, record[key])}
                                        </dd>
                                    </div>
                                ))}
                        </dl>
                        <div className="mt-4 flex flex-wrap gap-2 border-t pt-3">
                            {actions(record)}
                        </div>
                    </article>
                ))}
                {!records.data.length && (
                    <div className="px-5 py-10 text-center">
                        <FolderOpen
                            className="mx-auto mb-3 size-7 text-primary"
                            aria-hidden="true"
                        />
                        <p className="text-sm font-medium">Belum ada data yang ditampilkan.</p>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Tambahkan data atau sesuaikan pencarian Anda.
                        </p>
                    </div>
                )}
            </div>
            <div className="hidden overflow-x-auto lg:block print:block">
                <table className="w-full text-left text-sm">
                    <caption className="sr-only">{emptyLabel}</caption>
                    <thead className="border-b bg-muted/60 text-xs text-muted-foreground">
                        <tr>
                            {Object.entries(columns).map(([key, label]) => (
                                <th
                                    key={key}
                                    scope="col"
                                    className="whitespace-nowrap px-5 py-4 font-medium"
                                >
                                    {label}
                                </th>
                            ))}
                            <th scope="col" className="px-5 py-4 font-medium">
                                Status
                            </th>
                            <th scope="col" className="px-5 py-4 text-right font-medium">
                                Tindakan
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {records.data.map((record) => (
                            <tr key={record.id} className="hover:bg-muted/25">
                                {Object.keys(columns).map((key, index) => (
                                    <td
                                        key={key}
                                        className={
                                            'max-w-60 px-5 py-4 ' +
                                            (index === 0 ? 'font-medium' : '')
                                        }
                                    >
                                        <span
                                            className={
                                                key === 'code'
                                                    ? 'whitespace-nowrap font-mono text-xs text-muted-foreground'
                                                    : 'line-clamp-2'
                                            }
                                        >
                                            {formatValue(key, record[key])}
                                        </span>
                                    </td>
                                ))}
                                <td className="px-5 py-4">
                                    <Badge variant={record.is_active ? 'secondary' : 'outline'}>
                                        {record.is_active ? 'Aktif' : 'Nonaktif'}
                                    </Badge>
                                </td>
                                <td className="whitespace-nowrap px-3 py-3">{actions(record)}</td>
                            </tr>
                        ))}
                        {records.data.length === 0 && (
                            <tr>
                                <td
                                    colSpan={Object.keys(columns).length + 2}
                                    className="px-6 py-16 text-center"
                                >
                                    <FolderOpen className="mx-auto mb-4 size-8 text-muted-foreground" />
                                    <p className="font-medium">Belum ada data yang ditampilkan.</p>
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        Tambahkan data atau sesuaikan pencarian Anda.
                                    </p>
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
            <div className="flex flex-wrap items-center justify-between gap-4 border-t px-5 py-4 text-xs text-muted-foreground">
                <p>
                    {records.total
                        ? records.from + '–' + records.to + ' dari ' + records.total + ' data'
                        : '0 data'}
                </p>
                <div className="flex items-center gap-3">
                    <span>
                        Halaman {records.current_page} dari {records.last_page}
                    </span>
                    <PageButton href={records.prev_page_url} label="Halaman sebelumnya">
                        <ChevronLeft />
                    </PageButton>
                    <PageButton href={records.next_page_url} label="Halaman berikutnya">
                        <ChevronRight />
                    </PageButton>
                </div>
            </div>
        </div>
    );
}
function PageButton({
    href,
    label,
    children,
}: {
    href: string | null;
    label: string;
    children: ReactNode;
}) {
    return href ? (
        <Button variant="outline" size="icon" asChild>
            <Link href={href} preserveScroll aria-label={label}>
                {children}
            </Link>
        </Button>
    ) : (
        <Button variant="outline" size="icon" disabled aria-label={label}>
            {children}
        </Button>
    );
}
function formatValue(key: string, value: MasterRecord[string]) {
    if (value === null || value === undefined || value === '') return '—';
    if (key === 'capacity_kg')
        return Number(value).toLocaleString('id-ID', { maximumFractionDigits: 3 });
    if (key === 'joined_on')
        return new Intl.DateTimeFormat('id-ID', {
            dateStyle: 'medium',
            timeZone: 'Asia/Jakarta',
        }).format(new Date(String(value)));
    return String(value);
}

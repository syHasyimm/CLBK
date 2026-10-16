import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { PaginatedData } from '@/types/finance';

export default function PayrollPagination({
    records,
    label,
}: {
    records: Omit<PaginatedData<unknown>, 'data'>;
    label: string;
}) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-3 border-t px-5 py-4 text-xs text-muted-foreground sm:px-6">
            <p>
                {records.total
                    ? `${records.from}–${records.to} dari ${records.total} ${label}`
                    : `0 ${label}`}
            </p>
            <nav aria-label={'Halaman ' + label} className="flex items-center gap-3">
                <span className="tabular-nums">
                    {records.current_page} / {records.last_page}
                </span>
                {[
                    { href: records.prev_page_url, label: 'Halaman sebelumnya', Icon: ChevronLeft },
                    {
                        href: records.next_page_url,
                        label: 'Halaman berikutnya',
                        Icon: ChevronRight,
                    },
                ].map(({ href, label: buttonLabel, Icon }) =>
                    href ? (
                        <Button key={buttonLabel} asChild variant="outline" size="icon">
                            <Link href={href} preserveScroll aria-label={buttonLabel}>
                                <Icon className="size-4" aria-hidden="true" />
                            </Link>
                        </Button>
                    ) : (
                        <Button
                            key={buttonLabel}
                            variant="outline"
                            size="icon"
                            disabled
                            aria-label={buttonLabel}
                        >
                            <Icon className="size-4" aria-hidden="true" />
                        </Button>
                    ),
                )}
            </nav>
        </div>
    );
}

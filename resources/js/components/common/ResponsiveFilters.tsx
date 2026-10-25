import { useEffect, useState, type FormEvent, type ReactNode } from 'react';
import { router } from '@inertiajs/react';
import { Search, SlidersHorizontal, X } from 'lucide-react';
import { Dialog } from 'radix-ui';
import useMobileViewport from '@/hooks/use-mobile-viewport';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export default function ResponsiveFilters({
    title = 'Filter daftar',
    summary,
    children,
    search,
}: {
    title?: string;
    summary: string;
    children: ReactNode;
    search?: {
        url: string;
        filters: Record<string, string | number | null | undefined>;
        placeholder: string;
    };
}) {
    const mobile = useMobileViewport();
    const [open, setOpen] = useState(false);
    useEffect(() => {
        if (!open) return;
        return router.on('success', () => setOpen(false));
    }, [open]);

    if (!mobile) return <>{children}</>;

    return (
        <Dialog.Root open={open} onOpenChange={setOpen}>
            {search && <MobileSearch {...search} />}
            <div className="mobile-filter-bar">
                <p>{summary}</p>
                <Dialog.Trigger asChild>
                    <Button type="button" variant="outline">
                        <SlidersHorizontal aria-hidden="true" /> Filter
                    </Button>
                </Dialog.Trigger>
            </div>
            <Dialog.Portal>
                <Dialog.Overlay className="mobile-sheet-overlay" />
                <Dialog.Content className="mobile-sheet mobile-filter-sheet">
                    <div className="mobile-sheet-handle" aria-hidden="true" />
                    <div className="mobile-sheet-heading">
                        <div>
                            <Dialog.Title>{title}</Dialog.Title>
                            <Dialog.Description>
                                Sesuaikan pilihan, lalu terapkan untuk memperbarui daftar.
                            </Dialog.Description>
                        </div>
                        <Dialog.Close asChild>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label="Tutup filter"
                            >
                                <X aria-hidden="true" />
                            </Button>
                        </Dialog.Close>
                    </div>
                    <div className="mobile-sheet-scroll">{children}</div>
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}

function MobileSearch({
    url,
    filters,
    placeholder,
}: NonNullable<Parameters<typeof ResponsiveFilters>[0]['search']>) {
    const [query, setQuery] = useState(String(filters.q ?? ''));
    const [busy, setBusy] = useState(false);
    useEffect(() => setQuery(String(filters.q ?? '')), [filters.q]);
    function submit(event: FormEvent) {
        event.preventDefault();
        router.get(
            url,
            { ...filters, page: undefined, q: query },
            {
                preserveState: true,
                preserveScroll: true,
                onStart: () => setBusy(true),
                onFinish: () => setBusy(false),
            },
        );
    }
    return (
        <form
            className="mobile-inline-search"
            role="search"
            aria-label={placeholder}
            onSubmit={submit}
            aria-busy={busy}
        >
            <Input
                value={query}
                maxLength={100}
                aria-label={placeholder}
                placeholder={placeholder}
                onChange={(event) => setQuery(event.target.value)}
            />
            <Button type="submit" variant="ghost" aria-label="Terapkan pencarian" disabled={busy}>
                <Search aria-hidden="true" />
            </Button>
        </form>
    );
}

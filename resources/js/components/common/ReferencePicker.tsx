import axios from 'axios';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { Dialog } from 'radix-ui';
import { ChevronDown, X } from 'lucide-react';
import useMobileViewport from '@/hooks/use-mobile-viewport';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError';
import type { ReferenceOption } from '@/types/operations';

export default function ReferencePicker({
    id,
    label,
    url,
    value,
    onChange,
    error,
    optional = false,
}: {
    id: string;
    label: string;
    url: string;
    value: ReferenceOption | null;
    onChange: (option: ReferenceOption | null) => void;
    error?: string;
    optional?: boolean;
}) {
    const mobile = useMobileViewport();
    const trigger = useRef<HTMLButtonElement>(null);
    const [open, setOpen] = useState(false),
        [q, setQ] = useState(''),
        [page, setPage] = useState(1);
    const [results, setResults] = useState<ReferenceOption[]>([]),
        [last, setLast] = useState(1),
        [busy, setBusy] = useState(false),
        [failure, setFailure] = useState('');
    useEffect(() => {
        if (!open) return;
        const controller = new AbortController();
        setBusy(true);
        setFailure('');
        const timer = setTimeout(() => {
            axios
                .get(url, { params: { q, page }, signal: controller.signal })
                .then(({ data }) => {
                    setResults(
                        data.data.map(
                            (row: {
                                id: number;
                                label?: string;
                                name?: string;
                                plate_number?: string;
                            }) => ({
                                id: row.id,
                                label: row.label ?? row.name ?? row.plate_number ?? '',
                            }),
                        ),
                    );
                    setLast(data.last_page);
                })
                .catch((error) => {
                    if (!axios.isCancel(error)) {
                        setResults([]);
                        setFailure('Data gagal dimuat. Tutup dan buka pemilih untuk mencoba lagi.');
                    }
                })
                .finally(() => {
                    if (!controller.signal.aborted) setBusy(false);
                });
        }, 250);
        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [open, q, page, url]);
    return (
        <div>
            <Label htmlFor={id}>
                {label}
                {optional && <span className="font-normal text-muted-foreground"> (opsional)</span>}
            </Label>
            <div className="mt-2">
                <Button
                    ref={trigger}
                    id={id}
                    type="button"
                    variant="outline"
                    className="h-auto min-h-10 w-full justify-between whitespace-normal text-left font-normal"
                    aria-expanded={open}
                    aria-controls={id + '-options'}
                    aria-invalid={!!error}
                    onClick={() => setOpen(!open)}
                >
                    {value?.label ?? 'Pilih ' + label.toLowerCase()}
                    <ChevronDown
                        className="ml-3 size-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                </Button>
            </div>
            {open && (
                <PickerPanel
                    mobile={mobile}
                    title={label}
                    onClose={() => setOpen(false)}
                    onRestoreFocus={() => trigger.current?.focus()}
                >
                    <div id={id + '-options'} className="mt-2 rounded-lg border bg-background p-3">
                        <Input
                            id={id + '-search'}
                            value={q}
                            maxLength={100}
                            onChange={(e) => {
                                setQ(e.target.value);
                                setPage(1);
                            }}
                            aria-label={'Cari ' + label.toLowerCase()}
                            placeholder={'Cari ' + label.toLowerCase() + '...'}
                        />
                        <div className="mt-2 max-h-56 overflow-y-auto">
                            {busy ? (
                                <p role="status" className="p-2 text-sm">
                                    Memuat...
                                </p>
                            ) : failure ? (
                                <p role="alert" className="p-2 text-sm text-destructive">
                                    {failure}
                                </p>
                            ) : results.length ? (
                                results.map((option) => (
                                    <button
                                        key={option.id}
                                        aria-pressed={value?.id === option.id}
                                        type="button"
                                        className="block w-full rounded px-2 py-2 text-left text-sm hover:bg-secondary focus-visible:outline-primary"
                                        onClick={() => {
                                            onChange(option);
                                            setOpen(false);
                                        }}
                                    >
                                        {option.label}
                                    </button>
                                ))
                            ) : (
                                <p className="p-2 text-sm text-muted-foreground">
                                    Tidak ada data aktif yang cocok.
                                </p>
                            )}
                        </div>
                        <div className="mt-2 flex items-center justify-between gap-2">
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                disabled={busy || page === 1}
                                onClick={() => setPage(page - 1)}
                            >
                                Sebelumnya
                            </Button>
                            <span className="text-xs">
                                {page} / {last}
                            </span>
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                disabled={busy || page >= last}
                                onClick={() => setPage(page + 1)}
                            >
                                Berikutnya
                            </Button>
                        </div>
                        {optional && (
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                onClick={() => {
                                    onChange(null);
                                    setOpen(false);
                                }}
                            >
                                Kosongkan pilihan
                            </Button>
                        )}
                    </div>
                </PickerPanel>
            )}
            <InputError message={error} className="mt-2" />
        </div>
    );
}

function PickerPanel({
    mobile,
    title,
    onClose,
    onRestoreFocus,
    children,
}: {
    mobile: boolean;
    title: string;
    onClose: () => void;
    onRestoreFocus: () => void;
    children: ReactNode;
}) {
    if (!mobile) return <>{children}</>;
    return (
        <Dialog.Root
            open
            onOpenChange={(next) => {
                if (!next) onClose();
            }}
        >
            <Dialog.Portal>
                <Dialog.Overlay className="mobile-sheet-overlay" />
                <Dialog.Content
                    className="mobile-sheet reference-picker-sheet"
                    onCloseAutoFocus={(event) => {
                        event.preventDefault();
                        onRestoreFocus();
                    }}
                >
                    <div className="mobile-sheet-handle" aria-hidden="true" />
                    <div className="mobile-sheet-heading">
                        <div>
                            <Dialog.Title>Pilih {title.toLowerCase()}</Dialog.Title>
                            <Dialog.Description>
                                Cari dan pilih data yang sesuai.
                            </Dialog.Description>
                        </div>
                        <Dialog.Close asChild>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label="Tutup pemilih"
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

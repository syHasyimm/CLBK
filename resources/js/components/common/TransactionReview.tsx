import { useRef, type ReactNode } from 'react';
import { Check, X } from 'lucide-react';
import { Dialog } from 'radix-ui';
import { Button } from '@/components/ui/button';

export default function TransactionReview({
    open,
    onOpenChange,
    title,
    description,
    rows,
    confirmLabel,
    onConfirm,
    disabled,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: string;
    rows: { label: string; value: ReactNode }[];
    confirmLabel: string;
    onConfirm: () => void;
    disabled: boolean;
}) {
    const previousFocus = useRef<HTMLElement | null>(null);
    return (
        <Dialog.Root open={open} onOpenChange={onOpenChange}>
            <Dialog.Portal>
                <Dialog.Overlay className="mobile-sheet-overlay" />
                <Dialog.Content
                    className="mobile-sheet transaction-review"
                    onOpenAutoFocus={() => {
                        previousFocus.current =
                            document.activeElement instanceof HTMLElement
                                ? document.activeElement
                                : null;
                    }}
                    onCloseAutoFocus={(event) => {
                        if (previousFocus.current?.isConnected) {
                            event.preventDefault();
                            previousFocus.current.focus();
                        }
                    }}
                >
                    <div className="mobile-sheet-handle" aria-hidden="true" />
                    <div className="mobile-sheet-heading">
                        <div>
                            <Dialog.Title>{title}</Dialog.Title>
                            <Dialog.Description>{description}</Dialog.Description>
                        </div>
                        <Dialog.Close asChild>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label="Tutup pemeriksaan"
                            >
                                <X aria-hidden="true" />
                            </Button>
                        </Dialog.Close>
                    </div>
                    <dl className="mobile-sheet-scroll review-details">
                        {rows.map((row) => (
                            <div key={row.label}>
                                <dt>{row.label}</dt>
                                <dd>{row.value}</dd>
                            </div>
                        ))}
                    </dl>
                    <div className="mobile-sheet-actions">
                        <Dialog.Close asChild>
                            <Button type="button" variant="outline">
                                Periksa kembali
                            </Button>
                        </Dialog.Close>
                        <Button
                            type="button"
                            disabled={disabled}
                            onClick={() => {
                                if (disabled) return;
                                onOpenChange(false);
                                onConfirm();
                            }}
                        >
                            <Check aria-hidden="true" /> {confirmLabel}
                        </Button>
                    </div>
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}

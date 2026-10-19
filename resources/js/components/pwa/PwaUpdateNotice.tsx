import { RefreshCw } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { usePwa } from './PwaProvider';

export default function PwaUpdateNotice() {
    const { updateAvailable, updateBlocked, updating, updateError, update, dismissUpdate } =
        usePwa();
    if (!updateAvailable) return null;
    return (
        <section
            role="status"
            aria-label="Pembaruan aplikasi"
            className="border-b border-primary/15 bg-secondary px-4 py-4 text-primary print:hidden sm:px-8"
        >
            <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3">
                <div>
                    <p className="text-sm font-semibold">Pembaruan tersedia</p>
                    <p className="mt-1 text-xs leading-relaxed">
                        {updateBlocked
                            ? 'Selesaikan atau batalkan perubahan formulir sebelum memperbarui.'
                            : 'Perbarui aplikasi untuk menggunakan versi terbaru.'}
                    </p>
                    {updateError && <p className="mt-1 text-xs text-destructive">{updateError}</p>}
                </div>
                <div className="flex gap-2">
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        disabled={updating}
                        onClick={dismissUpdate}
                    >
                        Nanti
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        disabled={updateBlocked || updating}
                        onClick={update}
                    >
                        <RefreshCw
                            className={updating ? 'size-4 animate-spin' : 'size-4'}
                            aria-hidden="true"
                        />
                        {updating ? 'Memperbarui...' : 'Perbarui'}
                    </Button>
                </div>
            </div>
        </section>
    );
}

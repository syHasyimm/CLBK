import { useForm } from '@inertiajs/react';
import FormField from '@/components/common/FormField';
import ConfirmDialog from '@/components/common/ConfirmDialog';
import { Button } from '@/components/ui/button';
import { requestKey } from '@/lib/idempotency';
import InputError from '@/components/InputError';
import { usePwaReloadGuard } from '@/lib/pwa-reload-guard';
export default function ReverseVoucherForm({
    url,
    version,
    number,
}: {
    url: string;
    version?: number;
    number: string;
}) {
    const form = useForm({
        version: version ?? 1,
        reason: '',
        return_reference: '',
        idempotency_key: requestKey(),
    });
    usePwaReloadGuard(form.isDirty || form.processing);
    return (
        <section className="mt-6 rounded-xl border bg-card p-6 print:hidden">
            <h2 className="text-lg font-semibold">Pembalikan {number}</h2>
            <p className="my-4 text-sm text-muted-foreground">
                Catat alasan dan bukti kejadian pengembalian uang. Pembalikan administrasi mengikuti
                pengembalian nyata.
            </p>
            <div className="grid gap-4 sm:grid-cols-2">
                {(['reason', 'return_reference'] as const).map((key) => (
                    <FormField
                        key={key}
                        field={{
                            key,
                            label:
                                key === 'reason'
                                    ? 'Alasan pembalikan'
                                    : 'Kejadian / referensi pengembalian uang',
                            type: 'textarea',
                            required: true,
                            maxLength: 1000,
                        }}
                        value={form.data[key]}
                        error={form.errors[key]}
                        onChange={(v) => form.setData(key, v)}
                    />
                ))}
            </div>
            {Object.entries(form.errors)
                .filter(([key]) => !['reason', 'return_reference'].includes(key))
                .map(([key, message]) => (
                    <InputError key={key} message={message} className="mt-3" />
                ))}
            <ConfirmDialog
                title="Balikkan transaksi uang?"
                description={`${number}: jurnal kas lawan arah dicatat pada hari ini. Dokumen asli dan alasannya tetap tersimpan.`}
                actionLabel="Balikkan transaksi"
                destructive
                onConfirm={() => form.post(url, { preserveScroll: true })}
            >
                <Button
                    className="mt-4"
                    variant="outline"
                    disabled={
                        form.processing ||
                        !form.data.reason.trim() ||
                        !form.data.return_reference.trim()
                    }
                >
                    Balikkan transaksi
                </Button>
            </ConfirmDialog>
        </section>
    );
}

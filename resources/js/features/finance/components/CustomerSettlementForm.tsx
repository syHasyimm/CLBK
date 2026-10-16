import { useForm, usePage } from '@inertiajs/react';
import { Check, LoaderCircle } from 'lucide-react';
import FormField from '@/components/common/FormField';
import ConfirmDialog from '@/components/common/ConfirmDialog';
import InputError from '@/components/InputError';
import { Button } from '@/components/ui/button';
import { requestKey } from '@/lib/idempotency';
import { operationError } from '@/lib/form';
import { rupiah } from '@/lib/currency';
import { weight } from '@/lib/weight';
import type { Entitlement } from '@/types/finance';

export default function CustomerSettlementForm({
    recipient,
    rows,
    localNow,
    settlement,
}: {
    recipient: { id: number; name: string };
    rows: Entitlement[];
    localNow: string;
    settlement: { remaining_idr: number; ticket_count: number };
}) {
    const { cash } = usePage().props;
    const form = useForm({
        recipient_id: recipient.id,
        amount_idr: settlement.remaining_idr,
        expected_remaining_idr: settlement.remaining_idr,
        method: 'cash',
        reference: '',
        occurred_at: localNow,
        backdate_reason: '',
        notes: '',
        idempotency_key: requestKey(),
    });
    const ready =
        cash?.is_active &&
        cash.balance_idr >= settlement.remaining_idr &&
        !form.processing &&
        !!form.data.occurred_at &&
        (form.data.method !== 'bank_transfer' || !!form.data.reference.trim());
    const fields = [
        { key: 'method', label: 'Metode pembayaran', type: 'select', required: true },
        {
            key: 'occurred_at',
            label: 'Tanggal & waktu (WIB)',
            type: 'datetime-local',
            required: true,
        },
        {
            key: 'reference',
            label: 'Referensi transfer',
            type: 'text',
            required: form.data.method === 'bank_transfer',
        },
        {
            key: 'backdate_reason',
            label: 'Alasan tanggal sebelumnya',
            type: 'textarea',
            required: false,
        },
        { key: 'notes', label: 'Catatan', type: 'textarea', required: false },
    ] as const;
    return (
        <section className="rounded-xl border bg-card p-5 sm:p-6">
            <div className="border-b pb-5">
                <h2 className="text-lg font-semibold">Lunasi seluruh uang sawit</h2>
                <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                    Satu pembayaran melunasi seluruh {settlement.ticket_count} tiket konsumen yang
                    belum lunas. Filter dan pagination tidak membatasi pelunasan.
                </p>
                <p className="mt-4 text-xs text-muted-foreground">Nominal pelunasan</p>
                <p className="mt-1 text-2xl font-semibold tabular-nums text-primary">
                    {rupiah(settlement.remaining_idr)}
                </p>
                <p className="mt-2 text-xs text-muted-foreground">
                    {cash
                        ? `${cash.name} · Saldo ${rupiah(cash.balance_idr)}`
                        : 'Kas Usaha belum tersedia.'}
                </p>
                {cash && cash.balance_idr < settlement.remaining_idr && (
                    <p role="alert" className="mt-2 text-xs text-destructive">
                        Dana tersedia belum cukup untuk pelunasan penuh.
                    </p>
                )}
            </div>
            <div className="my-5 grid gap-5 sm:grid-cols-2">
                {fields.map((field) => (
                    <FormField
                        key={field.key}
                        field={{ ...field, maxLength: 1000 }}
                        value={form.data[field.key]}
                        error={form.errors[field.key]}
                        onChange={(value) => form.setData(field.key, value)}
                        options={
                            field.key === 'method'
                                ? [
                                      { value: 'cash', label: 'Tunai' },
                                      { value: 'bank_transfer', label: 'Transfer bank' },
                                  ]
                                : []
                        }
                    />
                ))}
            </div>
            <InputError
                message={
                    form.errors.amount_idr ||
                    form.errors.expected_remaining_idr ||
                    form.errors.recipient_id ||
                    form.errors.idempotency_key ||
                    operationError(form.errors)
                }
                className="mb-4"
            />
            <ConfirmDialog
                title="Lunasi seluruh tagihan konsumen?"
                description={`${recipient.name} · ${settlement.ticket_count} tiket · ${rupiah(settlement.remaining_idr)}. Pastikan uang telah diserahkan atau transfer telah berhasil. Seluruh tagihan yang belum lunas akan dilunasi.`}
                actionLabel="Ya, catat pelunasan"
                onConfirm={() => {
                    if (ready)
                        form.post(route('customer-payments.store'), { preserveScroll: true });
                }}
            >
                <Button type="button" className="h-12 w-full" disabled={!ready}>
                    {form.processing ? (
                        <LoaderCircle className="size-4 animate-spin" aria-hidden="true" />
                    ) : (
                        <Check className="size-4" aria-hidden="true" />
                    )}
                    {form.processing ? 'Mencatat pelunasan...' : 'Konfirmasi pelunasan penuh'}
                </Button>
            </ConfirmDialog>
            <details className="mt-6 border-t pt-4">
                <summary className="cursor-pointer text-sm font-medium">
                    Rincian tiket sesuai filter ({rows.length} di halaman ini)
                </summary>
                <div className="mt-3 divide-y">
                    {rows.map((row) => (
                        <div key={row.id} className="py-3 text-xs">
                            <p className="font-medium">
                                {row.number} · {row.business_date}
                            </p>
                            {row.items.map((item) => (
                                <p key={item.fruit_type} className="mt-1 text-muted-foreground">
                                    {item.fruit_type === 'TBS' ? 'TBS' : 'Sortir'}:{' '}
                                    {weight(item.weight_grams)} ×{' '}
                                    {rupiah(item.buy_price_snapshot_idr)}/kg ={' '}
                                    {rupiah(item.purchase_amount_idr)}
                                </p>
                            ))}
                        </div>
                    ))}
                </div>
            </details>
        </section>
    );
}

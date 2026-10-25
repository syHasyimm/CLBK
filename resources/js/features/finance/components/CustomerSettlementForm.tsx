import { useForm, usePage } from '@inertiajs/react';
import { Banknote, Building2, Check, LoaderCircle } from 'lucide-react';
import FormField from '@/components/common/FormField';
import ConfirmDialog from '@/components/common/ConfirmDialog';
import InputError from '@/components/InputError';
import { Button } from '@/components/ui/button';
import { requestKey } from '@/lib/idempotency';
import { operationError } from '@/lib/form';
import { rupiah } from '@/lib/currency';
import { weight } from '@/lib/weight';
import type { Entitlement } from '@/types/finance';
import { usePwaReloadGuard } from '@/lib/pwa-reload-guard';

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
    usePwaReloadGuard(form.isDirty || form.processing);
    const backdated = form.data.occurred_at.slice(0, 10) < localNow.slice(0, 10);
    const ready =
        !!cash?.is_active &&
        cash.balance_idr >= settlement.remaining_idr &&
        !form.processing &&
        !!form.data.occurred_at &&
        (form.data.method !== 'bank_transfer' || !!form.data.reference.trim()) &&
        (!backdated || !!form.data.backdate_reason.trim());
    const methodLabel = form.data.method === 'cash' ? 'Tunai' : 'Transfer bank';
    const initials = recipient.name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toLocaleUpperCase('id-ID');
    function field(
        key: 'occurred_at' | 'reference' | 'backdate_reason' | 'notes',
        label: string,
        type = 'text',
        required = false,
    ) {
        return (
            <FormField
                field={{ key, label, type, required, maxLength: 1000 }}
                value={form.data[key]}
                error={form.errors[key]}
                onChange={(value) => form.setData(key, value)}
            />
        );
    }
    return (
        <section
            className="customer-settlement rounded-2xl border bg-card p-5 sm:p-6"
            aria-label="Pelunasan seluruh tagihan konsumen"
        >
            <div className="settlement-recipient">
                <span aria-hidden="true">{initials}</span>
                <div>
                    <h2>{recipient.name}</h2>
                    <p>{settlement.ticket_count} tiket belum lunas</p>
                </div>
            </div>
            <p className="mt-6 text-sm text-muted-foreground">Total pelunasan</p>
            <p className="settlement-amount mt-2 font-bold tracking-tight tabular-nums text-primary">
                {rupiah(settlement.remaining_idr)}
            </p>
            <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                Melunasi seluruh tiket yang belum lunas. Filter dan pagination tidak membatasi
                pelunasan.
            </p>
            <details className="my-5 rounded-xl border p-4">
                <summary className="cursor-pointer text-sm font-semibold">
                    Rincian tiket sesuai filter ({rows.length} di halaman ini)
                </summary>
                <p className="mt-2 text-xs leading-relaxed text-muted-foreground">
                    Cakupan pelunasan tetap seluruh {settlement.ticket_count} tiket belum lunas.
                </p>
                <div className="mt-3 divide-y">
                    {rows.map((row) => (
                        <div key={row.id} className="py-3 text-sm">
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
            <fieldset className="border-t pt-5">
                <legend className="sr-only">Metode pembayaran</legend>
                <p className="mb-3 text-base font-semibold">Metode pembayaran</p>
                <div
                    className="settlement-methods"
                    role="group"
                    aria-label="Pilih metode pembayaran"
                >
                    {[
                        { value: 'cash', label: 'Tunai', icon: Banknote },
                        { value: 'bank_transfer', label: 'Transfer bank', icon: Building2 },
                    ].map((method) => (
                        <button
                            key={method.value}
                            type="button"
                            aria-pressed={form.data.method === method.value}
                            disabled={form.processing}
                            onClick={() => form.setData('method', method.value)}
                        >
                            <method.icon aria-hidden="true" />
                            <span>{method.label}</span>
                            {form.data.method === method.value && (
                                <Check aria-hidden="true" className="settlement-method-check" />
                            )}
                        </button>
                    ))}
                </div>
                <InputError message={form.errors.method} className="mt-2" />
            </fieldset>
            <div className="mt-5 grid gap-5">
                {(form.data.method === 'bank_transfer' || form.errors.reference) &&
                    field(
                        'reference',
                        'Referensi transfer',
                        'text',
                        form.data.method === 'bank_transfer',
                    )}
                {field('occurred_at', 'Tanggal & waktu (WIB)', 'datetime-local', true)}
                {backdated &&
                    field('backdate_reason', 'Alasan tanggal sebelumnya', 'textarea', true)}
                <details
                    className="border-t pt-4"
                    open={!!form.errors.notes || (!backdated && !!form.errors.backdate_reason)}
                >
                    <summary className="cursor-pointer text-sm font-medium">
                        Catatan tambahan
                    </summary>
                    <div className="mt-4 grid gap-4">
                        {!backdated &&
                            !!form.errors.backdate_reason &&
                            field('backdate_reason', 'Alasan tanggal sebelumnya', 'textarea')}
                        {field('notes', 'Catatan', 'textarea')}
                    </div>
                </details>
            </div>
            <h3 className="mb-3 mt-6 text-base font-semibold">Ringkasan transaksi</h3>
            <dl className="settlement-cash-summary">
                <div>
                    <dt>Saldo Kas Usaha</dt>
                    <dd>{cash ? rupiah(cash.balance_idr) : 'Belum tersedia'}</dd>
                </div>
                <div>
                    <dt>Pelunasan</dt>
                    <dd>− {rupiah(settlement.remaining_idr)}</dd>
                </div>
                {cash && (
                    <div>
                        <dt>Sisa kas</dt>
                        <dd>{rupiah(cash.balance_idr - settlement.remaining_idr)}</dd>
                    </div>
                )}
            </dl>
            {(!cash || !cash.is_active) && (
                <p role="alert" className="mt-3 text-sm text-destructive">
                    Kas usaha {cash ? 'nonaktif' : 'belum tersedia'}. Pelunasan belum dapat dicatat.
                </p>
            )}
            {cash && cash.balance_idr < settlement.remaining_idr && (
                <p role="alert" className="mt-3 text-sm text-destructive">
                    Dana tersedia belum cukup untuk pelunasan penuh. Dibutuhkan{' '}
                    {rupiah(settlement.remaining_idr)}.
                </p>
            )}
            <InputError
                message={
                    form.errors.amount_idr ||
                    form.errors.expected_remaining_idr ||
                    form.errors.recipient_id ||
                    form.errors.idempotency_key ||
                    operationError(form.errors)
                }
                className="my-4"
            />
            <div className="mobile-action-dock mt-6">
                <div>
                    <p className="mobile-dock-hint mb-3 text-sm text-muted-foreground">
                        Pastikan uang telah diserahkan atau transfer berhasil.
                    </p>
                    <ConfirmDialog
                        title="Lunasi seluruh tagihan konsumen?"
                        description={`${recipient.name} · ${settlement.ticket_count} tiket · ${rupiah(settlement.remaining_idr)} · ${methodLabel}. Waktu ${form.data.occurred_at.replace('T', ' ')} WIB. Pastikan uang telah diserahkan atau transfer telah berhasil. Seluruh tagihan belum lunas akan dilunasi.`}
                        actionLabel="Ya, catat pelunasan"
                        onConfirm={() => {
                            if (ready)
                                form.post(route('customer-payments.store'), {
                                    preserveScroll: true,
                                });
                        }}
                    >
                        <Button type="button" className="h-12 w-full rounded-xl" disabled={!ready}>
                            {form.processing ? (
                                <LoaderCircle
                                    className="size-4 animate-spin motion-reduce:animate-none"
                                    aria-hidden="true"
                                />
                            ) : (
                                <Check className="size-4" aria-hidden="true" />
                            )}
                            {form.processing ? 'Mencatat pelunasan...' : 'Periksa pelunasan'}
                        </Button>
                    </ConfirmDialog>
                </div>
            </div>
        </section>
    );
}

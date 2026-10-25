import { router, useForm, usePage } from '@inertiajs/react';
import { Check, HandCoins, LoaderCircle, RefreshCw } from 'lucide-react';
import FormField from '@/components/common/FormField';
import ConfirmDialog from '@/components/common/ConfirmDialog';
import UnsavedChangesGuard from '@/components/common/UnsavedChangesGuard';
import InputError from '@/components/InputError';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { rupiah } from '@/lib/currency';
import { requestKey } from '@/lib/idempotency';
import { operationError } from '@/lib/form';
import type { PayeeTotals } from '@/types/finance';

export default function PayrollPaymentForm({
    recipient,
    totals,
    localNow,
    period,
}: {
    recipient: { id: number; name: string };
    totals: PayeeTotals;
    localNow: string;
    period: { from: string; to: string };
}) {
    const { cash } = usePage().props;
    const form = useForm({
        recipient_id: recipient.id,
        all_in_period: true,
        expected_remaining_idr: totals.remaining_idr,
        period_start: period.from,
        period_end: period.to,
        cash_account_id: String(cash?.id ?? ''),
        amount_idr: totals.remaining_idr > 0 ? String(totals.remaining_idr) : '',
        method: 'cash',
        reference: '',
        occurred_at: localNow,
        backdate_reason: '',
        notes: '',
        idempotency_key: requestKey(),
    });
    const amount = Number(form.data.amount_idr);
    const backdated = form.data.occurred_at.slice(0, 10) < localNow.slice(0, 10);
    const ready =
        cash?.is_active === true &&
        cash.balance_idr >= amount &&
        Number.isSafeInteger(amount) &&
        amount > 0 &&
        amount <= totals.remaining_idr &&
        !!form.data.occurred_at &&
        !!period.from &&
        !!period.to &&
        (form.data.method !== 'bank_transfer' || !!form.data.reference.trim()) &&
        (!backdated || !!form.data.backdate_reason.trim()) &&
        !form.processing;
    const stale = operationError(form.errors);
    function submit() {
        if (!ready) return;
        form.post(route('payroll-payments.store'), { preserveScroll: true });
    }
    function field(
        key: 'method' | 'reference' | 'occurred_at' | 'backdate_reason' | 'notes',
        label: string,
        type = 'text',
        required = false,
    ) {
        return (
            <FormField
                field={{ key, label, type, required, maxLength: type === 'textarea' ? 1000 : 255 }}
                value={form.data[key]}
                error={form.errors[key]}
                onChange={(value) => form.setData(key, value)}
                options={
                    key === 'method'
                        ? [
                              { value: 'cash', label: 'Tunai' },
                              { value: 'bank_transfer', label: 'Transfer bank' },
                          ]
                        : []
                }
            />
        );
    }
    if (totals.remaining_idr <= 0)
        return (
            <section
                className="rounded-2xl border border-primary/15 bg-secondary/60 p-5 sm:p-6"
                aria-labelledby="payroll-paid-title"
            >
                <span className="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                    <Check className="size-5" aria-hidden="true" />
                </span>
                <h2 id="payroll-paid-title" className="mt-4 text-base font-semibold text-primary">
                    {totals.ticket_count
                        ? 'Upah periode ini sudah lunas'
                        : 'Belum ada upah untuk dibayar'}
                </h2>
                <p className="mt-2 text-xs leading-relaxed text-muted-foreground">
                    {totals.ticket_count
                        ? 'Seluruh hak dari timbangan pada periode ini telah dibayar. Pekerjaan lunas dan voucher tersedia di Riwayat Penggajian Karyawan.'
                        : 'Pilih periode yang memiliki timbangan sah untuk karyawan ini.'}
                </p>
            </section>
        );
    return (
        <section
            className="payroll-panel min-w-0"
            aria-labelledby="payroll-payment-title"
            aria-busy={form.processing}
        >
            <UnsavedChangesGuard dirty={form.isDirty && !form.processing} />
            <div className="border-b bg-secondary/40 p-5 sm:p-6">
                <p className="mb-3 break-words text-lg font-semibold">{recipient.name}</p>
                <h2
                    id="payroll-payment-title"
                    className="flex items-center gap-2 text-sm font-semibold text-primary"
                >
                    <HandCoins className="size-4" aria-hidden="true" />
                    Catat pembayaran upah
                </h2>
                <p className="mt-2 text-xs leading-relaxed text-muted-foreground">
                    {totals.outstanding_count} timbangan belum lunas otomatis tercakup. Nominal
                    dapat dikurangi untuk pembayaran sebagian.
                </p>
            </div>
            <div className="space-y-5 p-5 sm:p-6">
                <div>
                    <Label htmlFor="payroll-amount">Nominal pembayaran</Label>
                    <div className="relative mt-2">
                        <span className="pointer-events-none absolute left-4 top-5 text-sm font-medium text-muted-foreground">
                            Rp
                        </span>
                        <Input
                            id="payroll-amount"
                            name="amount_idr"
                            type="number"
                            inputMode="numeric"
                            min={1}
                            max={totals.remaining_idr}
                            step={1}
                            required
                            autoComplete="off"
                            className="h-16 bg-secondary/30 pl-12 text-xl font-semibold tabular-nums md:text-xl"
                            value={form.data.amount_idr}
                            onChange={(event) => form.setData('amount_idr', event.target.value)}
                            aria-invalid={!!form.errors.amount_idr || amount > totals.remaining_idr}
                            aria-describedby="payroll-amount-help"
                        />
                    </div>
                    <div id="payroll-amount-help" className="mt-2">
                        <InputError
                            message={
                                form.errors.amount_idr ||
                                (amount > totals.remaining_idr
                                    ? 'Nominal melebihi sisa upah periode ini.'
                                    : undefined)
                            }
                        />
                        <div className="mt-2 flex flex-wrap items-center justify-between gap-2 text-[11px] text-muted-foreground">
                            <span>Maksimal {rupiah(totals.remaining_idr)}</span>
                            {amount !== totals.remaining_idr && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="h-7 px-2 text-[11px]"
                                    disabled={form.processing}
                                    onClick={() =>
                                        form.setData('amount_idr', String(totals.remaining_idr))
                                    }
                                >
                                    Isi sisa upah
                                </Button>
                            )}
                        </div>
                    </div>
                </div>
                {field('method', 'Metode pembayaran', 'select', true)}
                <div>
                    <p className="text-sm font-medium">Kas Usaha</p>
                    {cash ? (
                        <div className="mt-2 rounded-lg bg-secondary/30 p-3">
                            <p className="text-sm font-medium">{cash.name}</p>
                            <p className="mt-1 text-xs tabular-nums text-muted-foreground">
                                Saldo {rupiah(cash.balance_idr)}
                            </p>
                            {!cash.is_active && (
                                <p className="mt-2 text-xs text-destructive">
                                    Kas usaha nonaktif. Pembayaran belum dapat dicatat.
                                </p>
                            )}
                        </div>
                    ) : (
                        <p className="mt-2 text-xs text-destructive">
                            Kas usaha belum tersedia. Pembayaran belum dapat dicatat.
                        </p>
                    )}
                    <InputError message={form.errors.cash_account_id} className="mt-2" />
                </div>
                {(form.data.method === 'bank_transfer' || form.errors.reference) &&
                    field(
                        'reference',
                        'Referensi transfer',
                        'text',
                        form.data.method === 'bank_transfer',
                    )}
                <details
                    className="border-t pt-4"
                    open={
                        !!(
                            form.errors.occurred_at ||
                            form.errors.backdate_reason ||
                            form.errors.notes
                        )
                    }
                >
                    <summary className="w-fit cursor-pointer rounded-sm text-xs font-medium focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring">
                        Rincian pembayaran
                    </summary>
                    <div className="mt-4 space-y-4">
                        {field('occurred_at', 'Tanggal & waktu (WIB)', 'datetime-local', true)}
                        {field(
                            'backdate_reason',
                            'Alasan tanggal sebelumnya',
                            'textarea',
                            backdated,
                        )}
                        {field('notes', 'Catatan', 'textarea')}
                    </div>
                </details>
                <dl className="flex items-start justify-between gap-4 border-t pt-4 text-xs">
                    <dt className="text-muted-foreground">Sisa setelah pembayaran</dt>
                    <dd className="text-right font-semibold tabular-nums text-primary">
                        {Number.isSafeInteger(amount) &&
                        amount >= 0 &&
                        amount <= totals.remaining_idr
                            ? rupiah(totals.remaining_idr - amount)
                            : '—'}
                    </dd>
                </dl>
                {stale && (
                    <div role="alert" className="space-y-2">
                        <InputError message={stale} />
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={form.processing}
                            onClick={() => router.reload()}
                        >
                            <RefreshCw className="size-3.5" aria-hidden="true" />
                            Muat ulang riwayat
                        </Button>
                    </div>
                )}
                <InputError
                    message={
                        form.errors.recipient_id ||
                        form.errors.expected_remaining_idr ||
                        form.errors.period_start ||
                        form.errors.period_end ||
                        form.errors.all_in_period ||
                        form.errors.idempotency_key
                    }
                />
                {cash && Number.isSafeInteger(amount) && amount > cash.balance_idr && (
                    <p role="alert" className="text-sm text-destructive">
                        Saldo kas tidak cukup untuk pembayaran ini.
                    </p>
                )}
                <div className="mobile-action-dock">
                    <div>
                        <ConfirmDialog
                            title="Konfirmasi pembayaran upah"
                            description={`${recipient.name} · ${rupiah(amount)} · ${cash?.name ?? 'Kas Usaha'}. Waktu ${form.data.occurred_at.replace('T', ' ')} WIB. Dasar pembayaran adalah seluruh sisa upah periode ${period.from} sampai ${period.to}. Nominal dialokasikan dari timbangan tertua. Pastikan uang diserahkan sesuai catatan ini.`}
                            actionLabel="Ya, catat pembayaran"
                            onConfirm={submit}
                        >
                            <Button
                                type="button"
                                className="h-12 w-full rounded-xl"
                                disabled={!ready}
                            >
                                {form.processing ? (
                                    <LoaderCircle
                                        className="size-4 animate-spin motion-reduce:animate-none"
                                        aria-hidden="true"
                                    />
                                ) : (
                                    <Check className="size-4" aria-hidden="true" />
                                )}
                                {form.processing
                                    ? 'Mencatat pembayaran...'
                                    : 'Periksa pembayaran upah'}
                            </Button>
                        </ConfirmDialog>
                    </div>
                </div>
                <p className="text-center text-[11px] leading-relaxed text-muted-foreground">
                    Pembayaran mencatat voucher dan uang keluar dari kas usaha.
                </p>
            </div>
        </section>
    );
}

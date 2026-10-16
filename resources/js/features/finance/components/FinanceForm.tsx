import { useState } from 'react';
import { useForm, Link, usePage } from '@inertiajs/react';
import FormField from '@/components/common/FormField';
import ReferencePicker from '@/components/common/ReferencePicker';
import ConfirmDialog from '@/components/common/ConfirmDialog';
import UnsavedChangesGuard from '@/components/common/UnsavedChangesGuard';
import InputError from '@/components/InputError';
import { Button } from '@/components/ui/button';
import { rupiah } from '@/lib/currency';
import { weight } from '@/lib/weight';
import { requestKey } from '@/lib/idempotency';
import { operationError } from '@/lib/form';
import type { ReferenceOption } from '@/types/operations';
import type { FinanceKind, Entitlement, ReceiptSource } from '@/types/finance';
import { financeLabels, postRoute } from '@/types/finance';

export default function FinanceForm({
    kind,
    localNow,
    recipient,
    rows = [],
    period,
    sale,
}: {
    kind: FinanceKind;
    localNow: string;
    recipient?: { id: number; name: string };
    rows?: Entitlement[];
    period?: { from: string; to: string };
    sale?: ReceiptSource | null;
}) {
    const { cash } = usePage().props;
    const [category, setCategory] = useState<ReferenceOption | null>(null);
    const form = useForm({
        cash_account_id: String(cash?.id ?? ''),
        amount_idr: sale ? String(sale.remaining_idr) : '',
        method: 'cash',
        reference: '',
        occurred_at: localNow,
        backdate_reason: '',
        notes: '',
        recipient_id: recipient?.id ?? 0,
        selected_ids: [] as number[],
        expected_remaining_idr: 0,
        period_start: period?.from ?? '',
        period_end: period?.to ?? '',
        sale_kind: sale?.kind ?? 'factory',
        sale_id: sale?.id ?? 0,
        expense_category_id: '',
        description: '',
        idempotency_key: requestKey(),
    });
    const payableRows = kind === 'customer' ? rows.filter((row) => row.remaining_idr > 0) : rows;
    const selected = payableRows.filter((row) => form.data.selected_ids.includes(row.id));
    const remaining =
        sale?.remaining_idr ?? selected.reduce((sum, row) => sum + row.remaining_idr, 0);
    const needsAllocation = kind === 'customer' || kind === 'payroll';
    const ready =
        cash?.is_active === true &&
        Number(form.data.amount_idr) > 0 &&
        (form.data.method !== 'bank_transfer' || !!form.data.reference.trim()) &&
        (!needsAllocation || selected.length > 0) &&
        (kind !== 'receipt' || (!!sale && Number(form.data.amount_idr) <= remaining)) &&
        (kind !== 'expense' || (!!category && !!form.data.description.trim()));
    function submit() {
        if (
            !ready ||
            form.processing ||
            (needsAllocation && Number(form.data.amount_idr) > remaining)
        )
            return;
        form.transform((data) => ({
            ...data,
            expected_remaining_idr: remaining,
        }));
        form.post(route(postRoute(kind)), { preserveScroll: true });
    }
    function field(
        key:
            | 'amount_idr'
            | 'method'
            | 'reference'
            | 'occurred_at'
            | 'backdate_reason'
            | 'notes'
            | 'description',
        label: string,
        type = 'text',
        required = false,
    ) {
        return (
            <FormField
                field={{ key, label, type, required, maxLength: type === 'textarea' ? 1000 : 255 }}
                value={form.data[key]}
                error={form.errors[key]}
                onChange={(v) => form.setData(key, v)}
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
    return (
        <section className="rounded-xl border bg-card p-5 sm:p-6">
            <UnsavedChangesGuard dirty={form.isDirty} />
            <h2 className="text-lg font-semibold">{financeLabels[kind]}</h2>
            <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                {needsAllocation
                    ? 'Pilih hak yang dibayar. Nominal dialokasikan ke pekerjaan tertua yang dipilih.'
                    : kind === 'receipt'
                      ? 'Catat uang yang benar-benar diterima dari penjualan pabrik atau sortir. Pembayaran dapat sebagian atau lunas.'
                      : 'Biaya ini sekaligus mencatat uang keluar dari kas usaha.'}
            </p>
            {needsAllocation && (
                <div className="mt-6">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            form.setData(
                                'selected_ids',
                                payableRows.filter((r) => r.remaining_idr > 0).map((r) => r.id),
                            )
                        }
                    >
                        Pilih semua yang belum lunas di halaman ini
                    </Button>
                    <div className="mt-3 space-y-3">
                        {payableRows.map((row) => (
                            <label
                                key={row.id}
                                className="flex cursor-pointer items-start gap-3 rounded-lg border p-4 has-[:checked]:border-primary has-[:checked]:bg-secondary/30"
                            >
                                <input
                                    type="checkbox"
                                    className="mt-1 size-4 accent-primary"
                                    disabled={row.remaining_idr === 0 || form.processing}
                                    checked={form.data.selected_ids.includes(row.id)}
                                    onChange={(e) =>
                                        form.setData(
                                            'selected_ids',
                                            e.target.checked
                                                ? [...form.data.selected_ids, row.id]
                                                : form.data.selected_ids.filter(
                                                      (id) => id !== row.id,
                                                  ),
                                        )
                                    }
                                />
                                <span className="min-w-0 flex-1">
                                    <span className="flex flex-wrap justify-between gap-2 text-sm font-medium">
                                        <span>{row.number}</span>
                                        <span>Sisa {rupiah(row.remaining_idr)}</span>
                                    </span>
                                    <span className="mt-2 block text-xs leading-relaxed text-muted-foreground">
                                        {row.business_date} · Hak {rupiah(row.total_idr)} · Dibayar{' '}
                                        {rupiah(row.paid_idr)}
                                        {kind === 'payroll' && (
                                            <>
                                                {' '}
                                                · Dasar {weight(row.basis_grams)} ×{' '}
                                                {rupiah(row.rate_idr)}/kg · {row.member_count}{' '}
                                                anggota
                                            </>
                                        )}
                                    </span>
                                    {row.items.map((item) => (
                                        <span
                                            key={item.fruit_type}
                                            className="mt-1 block text-xs text-muted-foreground"
                                        >
                                            {item.fruit_type === 'TBS' ? 'TBS' : 'Sortir awal'}:{' '}
                                            {weight(item.weight_grams)} ×{' '}
                                            {rupiah(item.buy_price_snapshot_idr)}/kg ={' '}
                                            {rupiah(item.purchase_amount_idr)}
                                        </span>
                                    ))}
                                    <Link
                                        href={route('weighings.show', row.weighing_id)}
                                        className="mt-2 inline-block text-xs text-primary underline"
                                        onClick={(e) => e.stopPropagation()}
                                    >
                                        Lihat tiket
                                    </Link>
                                </span>
                            </label>
                        ))}
                    </div>
                    <InputError message={form.errors.selected_ids} />
                    <p className="mt-4 text-sm font-medium">Sisa pilihan: {rupiah(remaining)}</p>
                </div>
            )}
            {sale && (
                <div className="mt-6 rounded-lg bg-muted p-4 text-sm">
                    <p className="font-semibold">
                        {sale.number} · {sale.party}
                    </p>
                    <p className="mt-2">
                        Nilai nota {rupiah(sale.total_idr)} · Diterima {rupiah(sale.paid_idr)} ·
                        Sisa {rupiah(remaining)}
                    </p>
                    <Link
                        href={route(sale.kind + '-sales.show', sale.id)}
                        className="mt-2 inline-block text-primary underline"
                    >
                        Lihat nota {sale.kind === 'factory' ? 'pabrik' : 'sortir'}
                    </Link>
                </div>
            )}
            <div className="mt-6 grid gap-5 sm:grid-cols-2">
                <div>
                    <p className="text-sm font-medium">Kas Usaha</p>
                    {cash ? (
                        <div className="mt-2 rounded-lg bg-muted p-3">
                            <p className="text-sm font-medium">{cash.name}</p>
                            <p className="mt-1 text-xs tabular-nums text-muted-foreground">
                                Saldo {rupiah(cash.balance_idr)}
                            </p>
                            {!cash.is_active && (
                                <p className="mt-2 text-xs text-destructive">
                                    Kas usaha nonaktif. Transaksi belum dapat dicatat.
                                </p>
                            )}
                        </div>
                    ) : (
                        <p className="mt-2 text-sm text-destructive">
                            Kas usaha belum tersedia. Transaksi belum dapat dicatat.
                        </p>
                    )}
                    <InputError message={form.errors.cash_account_id} className="mt-2" />
                </div>
                {field('method', 'Metode', 'select', true)}
                <div>
                    {field('amount_idr', 'Nominal (Rp)', 'number', true)}
                    {(needsAllocation || !!sale) && (
                        <Button
                            type="button"
                            className="mt-2"
                            size="sm"
                            variant="ghost"
                            disabled={!remaining}
                            onClick={() => form.setData('amount_idr', String(remaining))}
                        >
                            Lunaskan pilihan: {rupiah(remaining)}
                        </Button>
                    )}
                </div>
                {field('occurred_at', 'Tanggal & waktu (WIB)', 'datetime-local', true)}
                {field(
                    'reference',
                    'Referensi transfer',
                    'text',
                    form.data.method === 'bank_transfer',
                )}
                {field('backdate_reason', 'Alasan tanggal sebelumnya', 'textarea')}
                {kind === 'expense' && (
                    <>
                        <ReferencePicker
                            id="expense_category_id"
                            label="Kategori pengeluaran"
                            url={route('references.index', 'expense-categories')}
                            value={category}
                            onChange={(v) => {
                                setCategory(v);
                                form.setData('expense_category_id', String(v?.id ?? ''));
                            }}
                            error={form.errors.expense_category_id}
                        />

                        {field('description', 'Deskripsi pengeluaran', 'textarea', true)}
                    </>
                )}
                {field('notes', 'Catatan', 'textarea')}
            </div>
            {operationError(form.errors) && (
                <p role="alert" className="mt-4 text-sm text-destructive">
                    {operationError(form.errors)}
                </p>
            )}
            <div className="mt-6 flex flex-wrap items-center justify-between gap-3 border-t pt-5">
                <p className="text-sm">
                    {needsAllocation || !!sale
                        ? `Sisa sesudah transaksi: ${rupiah(remaining - Number(form.data.amount_idr || 0))}`
                        : 'Biaya dibukukan satu kali.'}
                </p>
                <ConfirmDialog
                    title="Konfirmasi transaksi uang"
                    description={`${recipient?.name ?? sale?.party ?? category?.label ?? financeLabels[kind]} · ${rupiah(Number(form.data.amount_idr))} · ${cash?.name ?? 'Kas Usaha'}. Pastikan ${kind === 'receipt' ? 'penerimaan' : 'penyerahan'} uang sesuai catatan ini.`}
                    actionLabel="Ya, catat transaksi"
                    onConfirm={submit}
                >
                    <Button
                        disabled={
                            !ready ||
                            form.processing ||
                            (needsAllocation && Number(form.data.amount_idr) > remaining)
                        }
                    >
                        Konfirmasi{' '}
                        {kind === 'receipt'
                            ? 'penerimaan'
                            : kind === 'expense'
                              ? 'pengeluaran'
                              : 'pembayaran'}
                    </Button>
                </ConfirmDialog>
            </div>
        </section>
    );
}

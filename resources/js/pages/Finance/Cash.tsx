import { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowDownLeft,
    ArrowUpRight,
    ChevronRight,
    Eye,
    EyeOff,
    LoaderCircle,
    Plus,
} from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import PageHeading from '@/components/common/PageHeading';
import FormField from '@/components/common/FormField';
import DataTable from '@/components/common/DataTable';
import ConfirmDialog from '@/components/common/ConfirmDialog';
import ReverseVoucherForm from '@/features/finance/components/ReverseVoucherForm';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { rupiah } from '@/lib/currency';
import { dateTime } from '@/lib/dates';
import { requestKey } from '@/lib/idempotency';
import type { FinanceKind, PaginatedData } from '@/types/finance';
import { usePwaReloadGuard } from '@/lib/pwa-reload-guard';
import ResponsiveFilters from '@/components/common/ResponsiveFilters';
import useAmountVisibility from '@/hooks/use-amount-visibility';
type CashEntry = {
    id: number;
    number: string | null;
    kind: string;
    direction: string;
    amount_idr: number;
    occurred_at: string;
    reference: string | null;
    reason: string | null;
    is_reversed: boolean;
    voucher: { id: number; number: string; kind: FinanceKind } | null;
};
const cashLabels: Record<string, string> = {
    customer: 'Bayar konsumen',
    payroll: 'Bayar gaji',
    receipt: 'Penjualan masuk',
    expense: 'Biaya harian',
    opening: 'Saldo awal',
    capital: 'Setoran modal',
    withdrawal: 'Penarikan pemilik',
    reversal: 'Pembalikan',
};
export default function Cash({
    account,
    entries,
    filters,
    canManage,
    canCreateAccount,
    localNow,
}: {
    account: {
        id: number;
        name: string;
        code: string;
        is_active: boolean;
        balance_idr: number;
    } | null;
    entries: PaginatedData<CashEntry>;
    filters: Record<string, string>;
    canManage: boolean;
    canCreateAccount: boolean;
    localNow: string;
}) {
    const { hidden, toggle } = useAmountVisibility();
    const [from, setFrom] = useState(filters.from ?? ''),
        [to, setTo] = useState(filters.to ?? ''),
        [reversing, setReversing] = useState<CashEntry | null>(null);
    const accountCreation = useForm({});
    const form = useForm({
        kind: 'capital',
        amount_idr: '',
        occurred_at: localNow,
        reason: '',
        reference: '',
        backdate_reason: '',
        idempotency_key: requestKey(),
    });
    usePwaReloadGuard(form.isDirty || form.processing || accountCreation.processing);
    function field(
        key: 'kind' | 'amount_idr' | 'occurred_at' | 'reason' | 'reference' | 'backdate_reason',
        label: string,
        type = 'text',
        required = false,
    ) {
        return (
            <FormField
                field={{ key: 'cash-' + key, label, type, required, maxLength: 1000 }}
                value={form.data[key]}
                error={form.errors[key]}
                onChange={(v) => form.setData(key, v)}
                options={
                    key === 'kind'
                        ? ['opening', 'capital', 'withdrawal'].map((value) => ({
                              value,
                              label: cashLabels[value],
                          }))
                        : []
                }
            />
        );
    }
    return (
        <AppLayout
            header={
                <PageHeading
                    title="Kas Usaha"
                    description="Satu kas untuk seluruh penerimaan dan pembayaran usaha. Saldo mengikuti mutasi sah dan pembalikan."
                    action={
                        canManage &&
                        canCreateAccount && (
                            <div className="w-full sm:w-auto">
                                <Button
                                    type="button"
                                    className="w-full sm:w-auto"
                                    disabled={!canCreateAccount || accountCreation.processing}
                                    aria-describedby="cash-create-hint"
                                    onClick={() =>
                                        accountCreation.post(route('cash.account.store'), {
                                            preserveScroll: true,
                                        })
                                    }
                                >
                                    {accountCreation.processing ? (
                                        <LoaderCircle
                                            className="size-4 animate-spin motion-reduce:animate-none"
                                            aria-hidden="true"
                                        />
                                    ) : (
                                        <Plus className="size-4" aria-hidden="true" />
                                    )}
                                    {accountCreation.processing ? 'Membuat...' : 'Buat Kas Usaha'}
                                </Button>
                                <p
                                    id="cash-create-hint"
                                    className="mt-2 max-w-64 text-xs leading-relaxed text-muted-foreground"
                                >
                                    {canCreateAccount
                                        ? 'Kas baru dimulai dengan saldo Rp0.'
                                        : 'Akun kas sudah tercatat. Maksimal satu Kas Usaha.'}
                                </p>
                            </div>
                        )
                    }
                />
            }
        >
            <Head title="Kas Usaha" />
            <div className="cash-balance mb-6" data-account-exists={!!account}>
                <div className="cash-balance-inner rounded-xl border bg-card p-5">
                    <div className="cash-balance-label">
                        <p className="text-sm text-muted-foreground">
                            Saldo Kas Usaha{account && !account.is_active ? ' · Nonaktif' : ''}
                        </p>
                        <button
                            type="button"
                            onClick={toggle}
                            aria-label={hidden ? 'Tampilkan saldo kas' : 'Sembunyikan saldo kas'}
                            aria-pressed={hidden}
                        >
                            {hidden ? <EyeOff aria-hidden="true" /> : <Eye aria-hidden="true" />}
                        </button>
                    </div>
                    <p className="cash-balance-amount mt-2 text-2xl font-semibold tabular-nums">
                        {hidden
                            ? '••••••••'
                            : account
                              ? rupiah(account.balance_idr)
                              : 'Belum tersedia'}
                    </p>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {account
                            ? 'Saldo mencakup seluruh tanggal; filter di bawah berlaku pada daftar mutasi.'
                            : 'Kas Usaha belum tersedia.'}
                    </p>
                </div>
            </div>
            <ResponsiveFilters
                title="Filter mutasi kas"
                summary={
                    filters.from || filters.to
                        ? `${filters.from || 'Awal'} – ${filters.to || 'Sekarang'} · Filter hanya daftar mutasi`
                        : 'Seluruh tanggal · Filter hanya daftar mutasi'
                }
            >
                <form
                    className="mb-5 grid items-end gap-3 sm:flex sm:flex-wrap"
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get(route('cash.index'), { from, to });
                    }}
                >
                    <div>
                        <Label htmlFor="cash-from">Tanggal mulai</Label>
                        <Input
                            id="cash-from"
                            className="mt-2 w-full sm:w-40"
                            type="date"
                            value={from}
                            onChange={(e) => setFrom(e.target.value)}
                        />
                    </div>
                    <div>
                        <Label htmlFor="cash-to">Tanggal akhir</Label>
                        <Input
                            id="cash-to"
                            className="mt-2 w-full sm:w-40"
                            type="date"
                            value={to}
                            onChange={(e) => setTo(e.target.value)}
                        />
                    </div>
                    <Button variant="outline">Terapkan</Button>
                    <Button
                        type="button"
                        variant="ghost"
                        onClick={() => {
                            setFrom('');
                            setTo('');
                            router.get(route('cash.index'), {}, { preserveScroll: true });
                        }}
                    >
                        Atur ulang
                    </Button>
                </form>
            </ResponsiveFilters>
            <section className="mobile-cash-list lg:hidden" aria-labelledby="mobile-cash-title">
                <h2 id="mobile-cash-title" className="mb-3 text-base font-semibold">
                    Mutasi kas
                </h2>
                <div className="mobile-task-list">
                    {entries.data.map((entry) => (
                        <article key={entry.id} className="mobile-cash-entry">
                            <span
                                className={
                                    entry.direction === 'in'
                                        ? 'mobile-cash-direction cash-in'
                                        : 'mobile-cash-direction'
                                }
                            >
                                {entry.direction === 'in' ? (
                                    <ArrowDownLeft aria-hidden="true" />
                                ) : (
                                    <ArrowUpRight aria-hidden="true" />
                                )}
                            </span>
                            <div className="mobile-cash-entry-copy">
                                {entry.voucher ? (
                                    <Link
                                        className="font-semibold"
                                        href={route('finance.show', {
                                            kind: entry.voucher.kind,
                                            document: entry.voucher.id,
                                        })}
                                    >
                                        {cashLabels[entry.kind] ?? entry.kind}
                                        <ChevronRight
                                            className="size-4 shrink-0"
                                            aria-hidden="true"
                                        />
                                    </Link>
                                ) : (
                                    <h3 className="font-semibold">
                                        {cashLabels[entry.kind] ?? entry.kind}
                                    </h3>
                                )}
                                <p>
                                    {entry.voucher?.number ?? entry.number ?? `#${entry.id}`} ·{' '}
                                    {dateTime(entry.occurred_at)}
                                </p>
                                <strong className={entry.direction === 'in' ? 'text-primary' : ''}>
                                    {hidden
                                        ? '••••••'
                                        : `${entry.direction === 'in' ? '+' : '−'} ${rupiah(entry.amount_idr)}`}
                                </strong>
                                {(entry.reason || entry.reference) && (
                                    <p>
                                        {[entry.reason, entry.reference]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </p>
                                )}
                                {entry.is_reversed && (
                                    <span className="text-xs text-muted-foreground">
                                        Sudah dibalik
                                    </span>
                                )}
                                {canManage &&
                                    ['opening', 'capital', 'withdrawal'].includes(entry.kind) &&
                                    !entry.is_reversed && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="mt-2"
                                            onClick={() => setReversing(entry)}
                                        >
                                            Koreksi mutasi
                                        </Button>
                                    )}
                            </div>
                        </article>
                    ))}
                    {!entries.data.length && (
                        <p className="py-8 text-center text-sm text-muted-foreground">
                            Belum ada mutasi pada periode ini. Ubah filter untuk melihat periode
                            lain.
                        </p>
                    )}
                </div>
                <div className="mobile-cash-pagination">
                    <p>
                        {entries.total} mutasi · Halaman {entries.current_page} /{' '}
                        {entries.last_page}
                    </p>
                    <div>
                        {entries.prev_page_url && (
                            <Button asChild variant="outline">
                                <Link href={entries.prev_page_url}>Sebelumnya</Link>
                            </Button>
                        )}
                        {entries.next_page_url && (
                            <Button asChild variant="outline">
                                <Link href={entries.next_page_url}>Berikutnya</Link>
                            </Button>
                        )}
                    </div>
                </div>
                <Link className="mobile-info-link" href={route('daily-expenses.create')}>
                    <span>
                        <strong>Catat uang keluar</strong>
                        <span>Pengeluaran harian usaha</span>
                    </span>
                    <ChevronRight aria-hidden="true" />
                </Link>
            </section>
            <div className="hidden lg:block print:block">
                <DataTable
                    records={entries}
                    columns={[
                        { label: 'Tanggal', render: (r) => dateTime(r.occurred_at) },
                        {
                            label: 'Mutasi / dokumen',
                            render: (r) => (
                                <div>
                                    <p>{cashLabels[r.kind]}</p>
                                    {r.voucher ? (
                                        <Link
                                            className="mt-1 block text-xs text-primary underline"
                                            href={route('finance.show', {
                                                kind: r.voucher.kind,
                                                document: r.voucher.id,
                                            })}
                                        >
                                            {r.voucher.number}
                                        </Link>
                                    ) : (
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {r.number ?? `#${r.id}`}
                                        </p>
                                    )}
                                    {r.is_reversed && <p className="mt-1 text-xs">Sudah dibalik</p>}
                                </div>
                            ),
                        },
                        {
                            label: 'Masuk',
                            render: (r) => (r.direction === 'in' ? rupiah(r.amount_idr) : '—'),
                        },
                        {
                            label: 'Keluar',
                            render: (r) => (r.direction === 'out' ? rupiah(r.amount_idr) : '—'),
                        },
                        {
                            label: 'Catatan',
                            render: (r) => (
                                <span className="block min-w-32 max-w-64 text-xs">
                                    {[r.reason, r.reference].filter(Boolean).join(' · ') || '—'}
                                </span>
                            ),
                        },
                        {
                            label: 'Tindakan',
                            render: (r) =>
                                canManage &&
                                ['opening', 'capital', 'withdrawal'].includes(r.kind) &&
                                !r.is_reversed ? (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => setReversing(r)}
                                    >
                                        Koreksi
                                    </Button>
                                ) : (
                                    '—'
                                ),
                        },
                    ]}
                />
            </div>
            {reversing && (
                <ReverseVoucherForm
                    key={reversing.id}
                    url={route('cash.reverse', reversing.id)}
                    number={reversing.number ?? `Mutasi #${reversing.id}`}
                />
            )}
            {canManage && account?.is_active && (
                <section className="mt-8 rounded-xl border bg-card p-6">
                    <h2 className="text-lg font-semibold">
                        Saldo awal, modal, atau penarikan pemilik
                    </h2>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Pembayaran konsumen, gaji, dan biaya menggunakan modulnya masing-masing.
                    </p>
                    <div className="mt-6 grid gap-5 sm:grid-cols-2">
                        {field('kind', 'Jenis mutasi', 'select', true)}
                        {field('amount_idr', 'Nominal (Rp)', 'number', true)}
                        {field('occurred_at', 'Tanggal & waktu (WIB)', 'datetime-local', true)}
                        {field('reference', 'Referensi')}
                        {field('reason', 'Keterangan mutasi', 'textarea', true)}
                        {field('backdate_reason', 'Alasan tanggal sebelumnya', 'textarea')}
                    </div>
                    <ConfirmDialog
                        title="Catat mutasi kas manual?"
                        description={`${cashLabels[form.data.kind]} sebesar ${rupiah(Number(form.data.amount_idr))} pada ${account.name}. Pastikan uang masuk/keluar telah terjadi.`}
                        actionLabel="Ya, catat mutasi"
                        onConfirm={() =>
                            form.post(route('cash.store'), {
                                onSuccess: () => {
                                    form.reset('amount_idr', 'reason', 'reference');
                                    form.setData('idempotency_key', requestKey());
                                },
                            })
                        }
                    >
                        <Button
                            className="mt-5"
                            disabled={
                                form.processing ||
                                !form.data.reason.trim() ||
                                Number(form.data.amount_idr) <= 0
                            }
                        >
                            Konfirmasi mutasi
                        </Button>
                    </ConfirmDialog>
                </section>
            )}
        </AppLayout>
    );
}

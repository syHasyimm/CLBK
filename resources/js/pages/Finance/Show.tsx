import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import PageHeading from '@/components/common/PageHeading';
import ReverseVoucherForm from '@/features/finance/components/ReverseVoucherForm';
import InputError from '@/components/InputError';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import VoucherDocument from '@/features/finance/components/VoucherDocument';

import type { FinanceKind, FinanceDocument } from '@/types/finance';
import { financeLabels } from '@/types/finance';
export default function Show({
    kind,
    record,
    party,
    source,
    allocations,
    canReverse,
    attachments,
}: {
    kind: FinanceKind;
    record: FinanceDocument;
    party: string;
    source: { kind: string; id: number; number: string } | null;
    allocations: {
        weighing_id: number;
        number: string;
        business_date: string;
        amount_idr: number;
    }[];
    canReverse: boolean;
    attachments: { id: number; original_name: string; size_bytes: number }[];
}) {
    const proof = useForm<{ proof: File | null }>({ proof: null });

    const sortReceipt = kind === 'receipt' && source?.kind === 'sort';
    const label = sortReceipt ? 'Penerimaan Penjualan Sortir' : financeLabels[kind];
    return (
        <AppLayout
            header={
                <PageHeading
                    title={record.number}
                    description={label}
                    action={
                        <div className="flex flex-wrap gap-2 print:hidden">
                            <Button variant="outline" onClick={() => window.print()}>
                                Cetak / simpan PDF
                            </Button>
                            <Button variant="outline" asChild>
                                <Link
                                    href={
                                        kind === 'receipt'
                                            ? route('sale-receipts.index')
                                            : kind === 'payroll'
                                              ? route('payroll.history', { view: 'payments' })
                                              : route('finance.index', { kind })
                                    }
                                >
                                    Riwayat
                                </Link>
                            </Button>
                        </div>
                    }
                />
            }
        >
            <Head title={record.number} />
            <VoucherDocument
                kind={kind}
                record={record}
                label={label}
                party={party}
                source={source}
                allocations={allocations}
            />
            <section className="mt-6 rounded-xl border bg-card p-6 print:hidden">
                <h2 className="mb-4 text-lg font-semibold">Bukti transaksi</h2>
                {attachments.map((file) => (
                    <a
                        key={file.id}
                        href={route('finance-proofs.download', file.id)}
                        className="mb-3 block text-sm text-primary underline"
                    >
                        {file.original_name} · {Math.ceil(file.size_bytes / 1024)} KB
                    </a>
                ))}
                {!attachments.length && (
                    <p className="mb-4 text-sm text-muted-foreground">
                        Belum ada bukti tambahan tersimpan.
                    </p>
                )}
                {record.status === 'posted' && (
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            proof.post(route('finance.proofs', { kind, document: record.id }), {
                                forceFormData: true,
                                onSuccess: () => proof.reset(),
                            });
                        }}
                    >
                        <Label htmlFor="proof">Unggah PDF, JPG, atau PNG (maksimal 10 MB)</Label>
                        <Input
                            id="proof"
                            className="mt-2"
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png"
                            onChange={(e) => proof.setData('proof', e.target.files?.[0] ?? null)}
                        />
                        <InputError message={proof.errors.proof} />
                        <Button className="mt-3" disabled={!proof.data.proof || proof.processing}>
                            Simpan bukti
                        </Button>
                    </form>
                )}
            </section>
            {canReverse && record.status === 'posted' && (
                <ReverseVoucherForm
                    url={route('finance.reverse', { kind, document: record.id })}
                    version={record.version}
                    number={record.number}
                />
            )}
        </AppLayout>
    );
}

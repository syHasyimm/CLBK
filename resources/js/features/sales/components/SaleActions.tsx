import ConfirmDialog from '@/components/common/ConfirmDialog';
import FormField from '@/components/common/FormField';
import InputError from '@/components/InputError';
import { Button } from '@/components/ui/button';
import { dateTime } from '@/lib/dates';
import { operationError } from '@/lib/form';
import { Link } from '@inertiajs/react';
import { FormEvent } from 'react';

import type { SaleActionsModel } from '@/features/sales/hooks/use-sale-actions';

export default function SaleActions({ model }: { model: SaleActionsModel }) {
    const {
        kind,
        record,
        previewError,
        canEdit,
        canReverse,
        manager,
        posting,
        reversal,
        approval,
        deletion,
        approved,
        needsApproval,
        ready,
    } = model;
    function reasonField(form: typeof reversal, label: string) {
        return (
            <FormField
                field={{
                    key: 'reversal_reason',
                    label,
                    type: 'textarea',
                    required: true,
                    maxLength: 1000,
                }}
                value={form.data.reason}
                error={form.errors.reason}
                onChange={(v) => form.setData('reason', v)}
            />
        );
    }

    return (
        <>
            {previewError && (
                <p
                    role="alert"
                    className="mt-6 rounded-lg bg-destructive/10 p-4 text-sm text-destructive"
                >
                    {previewError}
                </p>
            )}
            {needsApproval && (
                <div className="mt-6 rounded-xl border border-amber-300 bg-amber-50 p-6">
                    <h2 className="font-semibold">Persetujuan selisih berat</h2>
                    <p className="mt-2 text-sm">
                        Selisih berat membutuhkan persetujuan manajer atas draft ini.
                    </p>
                    {approved ? (
                        <p className="mt-3 text-sm">Disetujui: {record.variance_reason}</p>
                    ) : manager ? (
                        <form
                            onSubmit={(e: FormEvent) => {
                                e.preventDefault();
                                approval.post(route('factory-sales.approve', { sale: record.id }));
                            }}
                            className="mt-4 max-w-xl space-y-4"
                        >
                            <InputError message={operationError(approval.errors)} />
                            <Button disabled={approval.processing}>Setujui selisih</Button>
                        </form>
                    ) : (
                        <p className="mt-3 text-sm font-medium">Menunggu persetujuan manajer.</p>
                    )}
                </div>
            )}
            {!needsApproval &&
                kind === 'factory' &&
                record.status !== 'draft' &&
                record.variance_reason && (
                    <div className="mt-6 rounded-xl border bg-card p-6">
                        <h2 className="font-semibold">Persetujuan selisih berat</h2>
                        <p className="mt-2 whitespace-pre-wrap text-sm">{record.variance_reason}</p>
                    </div>
                )}
            {record.status === 'draft' && canEdit && (
                <div className="mt-6 flex flex-wrap gap-3">
                    <ConfirmDialog
                        title="Sahkan penjualan?"
                        description="Nota mencatat piutang. Catat uang yang diterima melalui Penerimaan Penjualan setelah nota disahkan."
                        actionLabel="Ya, sahkan"
                        onConfirm={() =>
                            posting.post(route(kind + '-sales.post', { sale: record.id }))
                        }
                    >
                        <Button disabled={!ready || posting.processing}>Sahkan penjualan</Button>
                    </ConfirmDialog>
                    <Button asChild variant="outline">
                        <Link href={route(kind + '-sales.edit', { sale: record.id })}>
                            Ubah draft
                        </Link>
                    </Button>
                    <ConfirmDialog
                        title="Hapus draft?"
                        description="Draft akan dihapus. Nomor dokumen tetap tidak digunakan ulang."
                        actionLabel="Hapus draft"
                        destructive
                        onConfirm={() =>
                            deletion.delete(route(kind + '-sales.destroy', { sale: record.id }))
                        }
                    >
                        <Button variant="outline" disabled={deletion.processing}>
                            Hapus draft
                        </Button>
                    </ConfirmDialog>
                </div>
            )}
            <InputError
                message={operationError(posting.errors) ?? operationError(deletion.errors)}
                className="mt-4"
            />
            {record.status === 'posted' && canReverse && (
                <div className="mt-8 max-w-xl rounded-xl border bg-card p-6">
                    <h2 className="mb-4 font-semibold">Pembalikan nota</h2>
                    {reasonField(reversal, 'Alasan pembalikan')}
                    <InputError message={operationError(reversal.errors)} className="mt-3" />
                    <ConfirmDialog
                        title="Balikkan nota?"
                        description="Uang masuk penjualan ini akan dikurangkan kembali dari saldo kas usaha. Saldo harus mencukupi. Riwayat nota dan mutasi tetap tersimpan."
                        actionLabel="Balikkan nota"
                        destructive
                        onConfirm={() =>
                            reversal.post(route(kind + '-sales.reverse', { sale: record.id }))
                        }
                    >
                        <Button
                            variant="outline"
                            className="mt-4"
                            disabled={!reversal.data.reason.trim() || reversal.processing}
                        >
                            Balikkan nota
                        </Button>
                    </ConfirmDialog>
                </div>
            )}
            {record.status === 'reversed' && (
                <div className="mt-6 rounded-xl border bg-card p-6">
                    <p className="text-sm">
                        Dibalik {dateTime(record.reversed_at!)} · {record.reversal_reason}
                    </p>
                    {canReverse && (
                        <Button asChild className="mt-4">
                            <Link
                                href={route(kind + '-sales.create', {
                                    replaces_sale_id: record.id,
                                })}
                            >
                                Buat nota pengganti
                            </Link>
                        </Button>
                    )}
                </div>
            )}
        </>
    );
}

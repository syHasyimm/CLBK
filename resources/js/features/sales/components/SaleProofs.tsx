import InputError from '@/components/InputError';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

import type { SaleActionsModel } from '@/features/sales/hooks/use-sale-actions';

export default function SaleProofs({ model }: { model: SaleActionsModel }) {
    const { kind, record, canEdit, attachments, direct, proof } = model;

    return (
        <>
            {((!direct && kind === 'sort') || attachments.length > 0) && (
                <div className="mt-8 rounded-xl border bg-card p-6">
                    <h2 className="font-semibold">Bukti penjualan</h2>
                    <div className="mt-3 space-y-2">
                        {attachments.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Belum ada bukti tersimpan.
                            </p>
                        ) : (
                            attachments.map((file) => (
                                <a
                                    className="block text-sm text-primary hover:underline"
                                    key={file.id}
                                    href={route('sale-proofs.download', file.id)}
                                >
                                    {file.original_name}
                                </a>
                            ))
                        )}
                    </div>
                    {!direct && kind === 'sort' && canEdit && record.status !== 'reversed' && (
                        <form
                            className="mt-5 max-w-xl space-y-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                proof.post(route(kind + '-sales.proofs', { sale: record.id }), {
                                    onSuccess: () => proof.reset(),
                                });
                            }}
                        >
                            <Label htmlFor="proof">
                                Unggah PDF, JPG, atau PNG (maksimal 10 MB)
                            </Label>
                            <Input
                                id="proof"
                                type="file"
                                accept="application/pdf,image/jpeg,image/png"
                                onChange={(e) =>
                                    proof.setData('proof', e.target.files?.[0] ?? null)
                                }
                            />
                            <InputError message={proof.errors.proof} />
                            <Button
                                variant="outline"
                                disabled={!proof.data.proof || proof.processing}
                            >
                                Simpan bukti
                            </Button>
                        </form>
                    )}
                </div>
            )}
        </>
    );
}

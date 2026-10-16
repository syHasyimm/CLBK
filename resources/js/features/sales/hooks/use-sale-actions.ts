import type { SaleShowProps } from '@/features/sales/types/sale-show';
import { requestKey } from '@/lib/idempotency';
import { useForm, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
export default function useSaleActions({
    kind,
    record,
    preview,
    previewError,
    canEdit,
    canReverse,
    attachments,
    cashReceipts,
    canReceive,
}: SaleShowProps) {
    const direct = record.workflow === 'direct';
    const manager = usePage().props.auth.user.role === 'manager';
    const posting = useForm({
        version: record.version,
        preview_hash: preview?.preview_hash ?? null,
        idempotency_key: requestKey(),
    });
    const reversal = useForm({
        version: record.version,
        reason: '',
        idempotency_key: requestKey(),
    });
    const approval = useForm({
        version: record.version,
        idempotency_key: requestKey(),
    });
    const deletion = useForm({ version: record.version });
    const proof = useForm<{ proof: File | null }>({ proof: null });
    useEffect(() => {
        posting.setData({
            version: record.version,
            preview_hash: preview?.preview_hash ?? null,
            idempotency_key: requestKey(),
        });
        reversal.setData({ version: record.version, reason: '', idempotency_key: requestKey() });
        approval.setData({ version: record.version, idempotency_key: requestKey() });
        deletion.setData('version', record.version);
    }, [record.version, preview?.preview_hash]);
    const current = preview ?? record;
    const approved =
        record.variance_approved_version === record.version && !!record.variance_reason;
    const needsApproval = !!preview?.requires_approval;
    const ready = record.status === 'draft' && canEdit && !!preview && (!needsApproval || approved);
    return {
        kind,
        record,
        preview,
        previewError,
        canEdit,
        canReverse,
        attachments,
        cashReceipts,
        canReceive,
        direct,
        manager,
        posting,
        reversal,
        approval,
        deletion,
        proof,
        current,
        approved,
        needsApproval,
        ready,
    };
}
export type SaleActionsModel = ReturnType<typeof useSaleActions>;

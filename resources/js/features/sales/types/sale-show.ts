import type { SaleKind, SalePreview, SaleRecord } from '@/types/sales';
export interface SaleShowProps {
    kind: SaleKind;
    record: SaleRecord;
    preview: SalePreview | null;
    previewError: string | null;
    canEdit: boolean;
    canReverse: boolean;
    attachments: { id: number; original_name: string; size_bytes: number }[];
    canReceive: boolean;
    cashReceipts: {
        id: number;
        number: string;
        amount_idr: number;
        status: 'posted' | 'reversed';
        account_name: string;
        method: string;
        reference: string | null;
        occurred_at: string;
    }[];
}

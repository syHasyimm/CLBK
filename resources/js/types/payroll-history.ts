import type { Entitlement, FinanceDocument, PaginatedData } from './finance';

export type PayrollHistoryView = 'settled' | 'payments';

export interface PayrollHistoryWorker {
    id: number;
    code: string;
    name: string;
    is_active: boolean;
}

interface PayrollHistoryFilters {
    q?: string;
    from?: string;
    to?: string;
    worker_id?: number | string;
    status?: string;
    view?: PayrollHistoryView;
}

export type SettledPayrollRow = Entitlement & { worker: PayrollHistoryWorker };
export type PayrollHistoryDocument = FinanceDocument & { worker: PayrollHistoryWorker };

export interface PayrollHistoryProps {
    view: PayrollHistoryView;
    filters: PayrollHistoryFilters;
    worker: PayrollHistoryWorker | null;
    rows: PaginatedData<SettledPayrollRow> | null;
    documents: PaginatedData<PayrollHistoryDocument> | null;
    summary: {
        record_count: number;
        worker_count: number;
        total_idr: number;
        reversed_idr: number;
    };
}

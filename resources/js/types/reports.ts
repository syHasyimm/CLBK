import type { PaginatedData } from './master-data';

export interface CashFlow {
    accounts: {
        id: number;
        name: string;
        type: string;
        opening_idr: number;
        incoming_idr: number;
        outgoing_idr: number;
        closing_idr: number;
    }[];
    totals: {
        opening_idr: number;
        incoming_idr: number;
        outgoing_idr: number;
        closing_idr: number;
    };
}
export interface ReportColumn {
    key: string;
    label: string;
    type: 'text' | 'money' | 'weight' | 'number';
    sum: boolean;
}
interface ReportRow {
    id: string;
    source: {
        route:
            | 'weighings.show'
            | 'factory-sales.show'
            | 'sort-sales.show'
            | 'finance.show'
            | 'cash.index';
        params: number | Record<string, string | number>;
    } | null;
    [key: string]: unknown;
}
export interface ReportData {
    title: string;
    note: string;
    columns: ReportColumn[];
    totals: Record<string, number>;
    rows: PaginatedData<ReportRow>;
    cashflow?: CashFlow;
}

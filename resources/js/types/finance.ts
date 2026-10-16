import type { PaginatedData } from './master-data';
export type { PaginatedData };
export type FinanceKind = 'customer' | 'payroll' | 'receipt' | 'expense';
export const financeLabels: Record<FinanceKind, string> = {
    customer: 'Pembayaran Konsumen',
    payroll: 'Gaji Karyawan Muat',
    receipt: 'Penerimaan Penjualan',
    expense: 'Uang Keluar Harian',
};
export const paymentLabels: Record<string, string> = {
    unpaid: 'Belum lunas',
    partial: 'Sebagian',
    paid: 'Lunas',
};
export interface FinanceDocument {
    id: number;
    number: string;
    amount_idr: number;
    factory_sale_id?: number | null;
    sort_sale_id?: number | null;
    occurred_at: string;
    business_date: string;
    status: 'posted' | 'reversed';
    version: number;
    method: string;
    reference: string | null;
    notes: string | null;
    period_start?: string;
    period_end?: string;
    description?: string;
    backdate_reason: string | null;
    reversal_reason: string | null;
    return_reference: string | null;
    reversed_at: string | null;
    cash_account: { id: number; name: string };
    creator: { id: number; name: string };
}
export interface PayeeCard {
    id: number;
    code: string;
    name: string;
    is_active: boolean;
    ticket_count: number;
    first_work_date?: string;
    last_work_date?: string;
    total_idr: number;
    paid_idr: number;
    remaining_idr: number;
    last_payment: { id: number; number: string; amount_idr: number; occurred_at: string } | null;
    tickets: {
        weighing_id: number;
        number: string;
        business_date: string;
        basis_grams: number;
        rate_idr: number;
        member_count: number | null;
        items: { fruit_type: string; weight_grams: number; buy_price_snapshot_idr: number }[];
    }[];
}
export interface Entitlement {
    id: number;
    weighing_id: number;
    number: string;
    weighed_at: string;
    business_date: string;
    total_idr: number;
    paid_idr: number;
    remaining_idr: number;
    basis_grams: number;
    rate_idr: number;
    member_count: number | null;
    items: {
        fruit_type: string;
        weight_grams: number;
        buy_price_snapshot_idr: number;
        purchase_amount_idr: number;
    }[];
}
export interface PayeeTotals {
    total_idr: number;
    paid_idr: number;
    remaining_idr: number;
    ticket_count: number;
    outstanding_count: number;
}
export interface PayeePageProps {
    customerSettlement?: {
        remaining_idr: number;
        ticket_count: number;
    } | null;
    kind: 'customer' | 'payroll';
    recipient: { id: number; code: string; name: string; is_active: boolean };
    rows: PaginatedData<Entitlement>;
    totals: PayeeTotals;
    history: PaginatedData<FinanceDocument>;
    filters: Record<string, string>;
    localNow: string;
}
export interface ReceiptSource {
    id: number;
    kind: 'factory' | 'sort';
    number: string;
    party: string;
    total_idr: number;
    paid_idr: number;
    remaining_idr: number;
}
export const worklistRoute = (kind: FinanceKind) =>
    kind === 'customer' ? 'customer-payments.index' : 'payroll.index';
export const payeeRoute = (kind: FinanceKind) =>
    kind === 'customer' ? 'customer-payments.payee' : 'payroll.payee';
export const postRoute = (kind: FinanceKind) =>
    ({
        customer: 'customer-payments.store',
        payroll: 'payroll-payments.store',
        receipt: 'sale-receipts.store',
        expense: 'daily-expenses.store',
    })[kind];

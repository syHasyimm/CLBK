import type { CashFlow } from '@/types/reports';

type Totals = {
    weighing_count: number;
    tbs_bought_grams: number;
    sort_bought_grams: number;
    factory_sold_grams: number;
    sort_sold_grams: number;
    purchase_idr: number;
    revenue_idr: number;
    loading_idr: number;
    expense_idr?: number;
    period_difference_idr?: number;
};
export interface PeriodProps {
    filters: { from: string; to: string; vehicle_id: number | null };
    vehicles: { id: number; plate_number: string }[];
    summary: {
        totals: Totals;
        days: (Totals & { date: string })[];
        vehicles: (Totals & { id: number; plate_number: string })[];
        balances: {
            customer_debt_idr: number;
            payroll_debt_idr: number;
            sale_receivable_idr: number;
            cash_idr: number;
        } | null;
        cashflow: CashFlow | null;
        as_of_date: string;
    };
}

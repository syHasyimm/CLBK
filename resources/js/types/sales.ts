import type { Trip } from './operations';
export type SaleKind = 'factory' | 'sort';
export interface SaleRecord {
    id: number;
    number: string;
    workflow: 'legacy' | 'direct';
    vehicle_id: number;
    vehicle: { id: number; plate_number: string };
    trip_id: number | null;
    trip: Trip | null;
    status: 'draft' | 'posted' | 'reversed';
    version: number;
    sold_at: string;
    business_date: string;
    sell_price_snapshot_idr: number;
    deduction_idr: number;
    deduction_reason: string | null;
    revenue_idr: number;
    receivable_idr: number;
    received_idr: number;
    payment_status: string | null;
    cost_status: string;
    purchase_cost_released_idr?: number | null;
    loading_cost_released_idr?: number | null;
    notes: string | null;
    replaces_sale_id: number | null;
    correction_reason: string | null;
    reversal_reason: string | null;
    reversed_at: string | null;
    factory_id?: number;
    factory?: { id: number; name: string };
    invoice_number?: string;
    initial_load_grams?: number;
    sorted_out_grams?: number;
    accepted_grams?: number;
    weight_difference_grams?: number;
    variance_approved_by?: number | null;
    variance_approved_version?: number | null;
    variance_reason?: string | null;
    factory_return?: { id: number; weight_grams: number } | null;
    buyer_name?: string;
    buyer_contact?: string | null;
    manual_sold_grams?: number;
    items?: SaleAllocation[];
}
interface SaleAllocation {
    id?: number;
    lot_id: number;
    weight_grams: number;
    gross_revenue_idr: number;
    purchase_cost_released_idr?: number | null;
    loading_cost_released_idr?: number | null;
}
export interface SalePreview {
    revenue_idr: number;
    cost_status: string;
    purchase_cost_released_idr?: number | null;
    loading_cost_released_idr?: number | null;
    purchase_cost_carried_idr?: number | null;
    loading_cost_carried_idr?: number | null;
    requires_approval?: boolean;
    weight_difference_grams?: number;
    preview_hash?: string;
    items?: SaleAllocation[];
}
export const saleTitle = (kind: SaleKind) =>
    kind === 'factory' ? 'Penjualan Pabrik' : 'Penjualan Sortir';

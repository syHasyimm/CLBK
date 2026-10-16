import type { PaginatedData } from './master-data';
export type { PaginatedData };
export interface ReferenceOption {
    id: number;
    label: string;
}
export interface Trip {
    id: number;
    number: string;
    status: string;
    business_date: string;
    driver: { id: number; name: string };
    vehicle: { id: number; plate_number: string };
    planned_factory: { id: number; name: string } | null;
    manual_load_grams: number | null;
    version: number;
    cost_status: string;
    departed_at: string | null;
    closed_at: string | null;
    tbs_purchase_cost_snapshot_idr?: number | null;
    tbs_loading_cost_snapshot_idr?: number | null;
}
interface WeighingItem {
    id: number;
    fruit_type: 'TBS' | 'SORT';
    weight_grams: number;
    buy_price_snapshot_idr: number | null;
    purchase_amount_idr: number | null;
    loading_cost_allocated_idr: number | null;
    lot?: {
        id: number;
        status: string;
        initial_grams: number;
        movements: { quantity_delta_grams: number }[];
    } | null;
}
export interface Loader {
    id?: number;
    worker_id: number;
    member_count_snapshot: number | null;
    share_amount_idr: number | null;
    allocation_order: number | null;
    worker?: { id: number; name: string };
}
export interface Weighing {
    paid_idr?: number;
    remaining_idr?: number;
    payment_status?: string | null;
    id: number;
    number: string;
    status: 'draft' | 'posted' | 'voided';
    version: number;
    created_by: number;
    weighed_at: string;
    business_date: string;
    customer_id: number;
    customer: { id: number; name: string };
    workflow: 'legacy' | 'direct';
    vehicle_id: number;
    vehicle: { id: number; plate_number: string };
    trip_id: number | null;
    trip: Trip | null;
    items: WeighingItem[];
    loaders: Loader[];
    price_schedule_id: number | null;
    loading_rate_snapshot_idr: number | null;
    loading_basis_grams: number | null;
    total_purchase_idr: number | null;
    total_loading_wage_idr: number | null;
    backdate_reason: string | null;
    void_reason: string | null;
    replaces_weighing_id: number | null;
}
export interface Price {
    id: number;
    effective_from: string;
    business_date: string;
    tbs_buy_price_idr: number;
    sort_buy_price_idr: number;
    loading_rate_idr: number;
    notes: string | null;
}
export interface FactoryPrice {
    id: number;
    factory: { id: number; name: string };
    effective_from: string;
    tbs_sell_price_idr: number;
}
export interface Preview {
    price_schedule_id: number;
    loading_rate_snapshot_idr: number;
    loading_basis_grams: number;
    total_purchase_idr: number;
    total_loading_wage_idr: number;
    items: WeighingItem[];
    loaders: Loader[];
    effective_from: string;
    weighed_at: string;
}
export const statusLabels: Record<string, string> = {
    unpaid: 'Belum lunas',
    partial: 'Sebagian',
    paid: 'Lunas',
    draft: 'Draft',
    posted: 'Sah',
    voided: 'Batal',
    loading: 'Menerima muatan',
    dispatched: 'Berangkat',
    factory_recorded: 'Hasil pabrik dicatat',
    closed: 'Selesai',
    cancelled: 'Dibatalkan',
    correction_pending: 'Menunggu koreksi',
    reversed: 'Dibalik',
    active: 'Aktif',
};
export interface DriverVehicleAssignment {
    vehicle: ReferenceOption | null;
    version: number;
    error: string | null;
}

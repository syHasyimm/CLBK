type FieldValue = string | number | boolean | null;
export interface MasterRecord {
    id: number;
    is_active: boolean;
    [key: string]: FieldValue;
}
export interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
}
export interface ListFilters {
    q: string;
    status: string;
}
export const roleLabels: Record<string, string> = {
    manager: 'Manajer Toke',
    driver: 'Supir',
    cashier: 'Kasir',
};

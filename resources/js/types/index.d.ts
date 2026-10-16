export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    role: 'manager' | 'driver' | 'cashier';
    is_active: boolean;
    assigned_vehicle_id?: number | null;
}

export interface BusinessCash {
    id: number;
    name: string;
    is_active: boolean;
    balance_idr: number;
}

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    auth: {
        user: User;
    };
    business: { name: string };
    cash: BusinessCash | null;
    flash: { success?: string };
};

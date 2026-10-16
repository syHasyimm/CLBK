import type { User } from '@/types';
import {
    ChartNoAxesCombined,
    Coins,
    ContactRound,
    Factory,
    History,
    LayoutDashboard,
    ReceiptText,
    Scale,
    Settings,
    ShieldCheck,
    Tags,
    Truck,
    UserRound,
    Users,
    Wallet,
} from 'lucide-react';

export type NavigationItem = {
    name: string;
    pattern: string;
    label: string;
    icon: typeof LayoutDashboard;
    kind?: string | null;
    params?: { resource: string };
};

const masterNavigation = [
    { resource: 'customers', label: 'Konsumen', icon: ContactRound },
    { resource: 'loading-workers', label: 'Anggota Muat', icon: Users },
    { resource: 'vehicles', label: 'Mobil', icon: Truck },
    { resource: 'factories', label: 'Pabrik', icon: Factory },
    { resource: 'expense-categories', label: 'Kategori Pengeluaran', icon: Tags },
];
const accountNavigation = [
    { name: 'users.index', pattern: 'users.*', label: 'Akun Pengguna', icon: ShieldCheck },
    {
        name: 'settings.business.edit',
        pattern: 'settings.business.*',
        label: 'Pengaturan Usaha',
        icon: Settings,
    },
];
const mainNavigation = [
    { name: 'dashboard', pattern: 'dashboard', label: 'Dashboard', icon: LayoutDashboard },
    { name: 'prices.index', pattern: 'prices.*', label: 'Harga & Upah Muat', icon: Coins },
    { name: 'weighings.index', pattern: 'weighings.*', label: 'Timbang Sawit', icon: Scale },
    {
        name: 'factory-sales.index',
        pattern: 'factory-sales.*',
        label: 'Penjualan Pabrik',
        icon: Factory,
    },
    {
        name: 'sort-sales.index',
        pattern: 'sort-sales.*',
        label: 'Penjualan Sortir',
        icon: ReceiptText,
    },
    { name: 'reports.index', pattern: 'reports.*', label: 'Laporan', icon: ChartNoAxesCombined },
    { name: 'profile.edit', pattern: 'profile.*', label: 'Profil Saya', icon: UserRound },
];

const financeNavigation = [
    {
        name: 'customer-payments.index',
        pattern: 'customer-payments.*',
        kind: 'customer',
        label: 'Uang Penimbang',
        icon: Wallet,
    },
    {
        name: 'payroll.index',
        pattern: 'payroll.*',
        kind: 'payroll',
        label: 'Gaji Karyawan Muat',
        icon: Users,
    },
    {
        name: 'payroll.history',
        pattern: 'payroll.history',
        kind: 'payroll',
        label: 'Riwayat Penggajian Karyawan',
        icon: History,
    },
    {
        name: 'sale-receipts.index',
        pattern: 'sale-receipts.*',
        kind: 'receipt',
        label: 'Penerimaan Penjualan',
        icon: ReceiptText,
    },
    {
        name: 'daily-expenses.index',
        pattern: 'daily-expenses.*',
        kind: 'expense',
        label: 'Uang Keluar Harian',
        icon: Coins,
    },
    { name: 'cash.index', pattern: 'cash.*', kind: null, label: 'Kas Usaha', icon: Wallet },
];

export function navigationForRole(role: User['role']): NavigationItem[] {
    if (role === 'cashier') {
        return [
            ...mainNavigation.filter((item) =>
                ['dashboard', 'prices.index', 'reports.index', 'profile.edit'].includes(item.name),
            ),
            ...financeNavigation,
        ];
    }
    if (role === 'driver') {
        return [
            ...mainNavigation.filter((item) =>
                [
                    'dashboard',
                    'weighings.index',
                    'factory-sales.index',
                    'sort-sales.index',
                    'profile.edit',
                ].includes(item.name),
            ),
            {
                name: 'customers.create',
                pattern: 'customers.*',
                label: 'Tambah Konsumen',
                icon: ContactRound,
            },
        ];
    }
    return [
        ...mainNavigation,
        ...financeNavigation,
        ...masterNavigation.map(({ resource, label, icon }) => ({
            name: 'master-data.index',
            pattern: 'master-data.*',
            params: { resource },
            label,
            icon,
        })),
        ...accountNavigation,
    ];
}

export function navigationHref(item: NavigationItem): string {
    return route(item.name, item.params);
}

export function navigationKey(item: NavigationItem): string {
    return item.params?.resource ?? item.name;
}

export function navigationGroupsForRole(role: User['role'], items: NavigationItem[]) {
    const account = { label: 'Akun', items: items.filter((item) => item.name === 'profile.edit') };
    if (role === 'driver') {
        return [
            { label: 'Operasional', items: items.filter((item) => item.name !== 'profile.edit') },
            account,
        ];
    }
    const groups = [
        {
            label: 'Ringkasan usaha',
            items: items.filter((item) =>
                ['dashboard', 'prices.index', 'reports.index'].includes(item.name),
            ),
        },
        ...(role === 'manager'
            ? [
                  {
                      label: 'Operasional',
                      items: items.filter((item) =>
                          ['weighings.index', 'factory-sales.index', 'sort-sales.index'].includes(
                              item.name,
                          ),
                      ),
                  },
              ]
            : []),
        {
            label: 'Keuangan',
            items: items.filter((item) => !!item.kind || item.name === 'cash.index'),
        },
        ...(role === 'manager'
            ? [
                  { label: 'Data master', items: items.filter((item) => !!item.params?.resource) },
                  {
                      label: 'Administrasi',
                      items: items.filter((item) =>
                          accountNavigation.some((entry) => entry.name === item.name),
                      ),
                  },
              ]
            : []),
        account,
    ];
    return groups;
}

export function navigationIsActive(item: NavigationItem): boolean {
    if (item.name === 'payroll.index') {
        return !!route().current('payroll.index') || !!route().current('payroll.payee');
    }
    if (item.name === 'payroll.history') {
        return (
            !!route().current('payroll.history') ||
            !!route().current('finance.*', { kind: 'payroll' })
        );
    }
    return (
        !!route().current(item.pattern, item.params) ||
        (!!item.kind && !!route().current('finance.*', { kind: item.kind }))
    );
}

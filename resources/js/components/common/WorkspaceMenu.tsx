import { Button } from '@/components/ui/button';
import {
    navigationGroupsForRole,
    navigationHref,
    navigationIsActive,
    navigationKey,
    type NavigationItem,
} from '@/lib/navigation';
import { cn } from '@/lib/utils';
import type { User } from '@/types';
import { Link } from '@inertiajs/react';
import { ArrowUpRight, Check, Grid2X2, X } from 'lucide-react';
import { Dialog } from 'radix-ui';
import type { ComponentProps } from 'react';

const descriptions: Record<string, string> = {
    dashboard: 'Ringkasan aktivitas usaha',
    'customer-payments.index': 'Pembayaran hasil timbang',
    'payroll.index': 'Periksa dan bayar sisa upah',
    'payroll.history': 'Pekerjaan lunas dan voucher gaji',
    'sale-receipts.index': 'Penerimaan pabrik dan sortir',
    'cash.index': 'Saldo dan mutasi kas usaha',
    'prices.index': 'Tarif buah dan upah muat',
    'reports.index': 'Rincian, ekspor, dan cetak',
    'daily-expenses.index': 'Catatan biaya operasional',
    'profile.edit': 'Informasi dan keamanan akun',
    'weighings.index': 'Catat timbang dan hak konsumen',
    'customers.create': 'Daftarkan konsumen baru',
    'factory-sales.index': 'Nota dan penjualan ke pabrik',
    'sort-sales.index': 'Penjualan buah sortir',
    customers: 'Data konsumen penimbang',
    'loading-workers': 'Data anggota tim muat',
    vehicles: 'Mobil dan penugasan supir',
    factories: 'Data pabrik tujuan penjualan',
    'expense-categories': 'Kategori biaya operasional',
    'users.index': 'Akun dan hak akses pengguna',
    'settings.business.edit': 'Identitas dan pengaturan usaha',
};

export default function WorkspaceMenu({
    items,
    role,
    onNavigate,
    onCloseAutoFocus,
}: {
    items: NavigationItem[];
    role: User['role'];
    onNavigate: () => void;
    onCloseAutoFocus?: ComponentProps<typeof Dialog.Content>['onCloseAutoFocus'];
}) {
    const roleName = { manager: 'admin', cashier: 'kasir', driver: 'supir' }[role];
    return (
        <Dialog.Portal>
            <Dialog.Overlay className="cashier-menu-overlay fixed inset-0 z-40 bg-foreground/35 backdrop-blur-sm print:hidden" />
            <Dialog.Content
                onCloseAutoFocus={onCloseAutoFocus}
                className="cashier-menu-dialog fixed z-50 flex flex-col overflow-hidden rounded-[1.75rem] border border-primary/10 bg-card outline-none print:hidden"
            >
                <div className="flex shrink-0 items-start justify-between gap-4 px-5 pb-5 pt-6 sm:px-6">
                    <div className="min-w-0">
                        <p className="mb-2 flex items-center gap-2 text-[11px] font-medium text-primary">
                            <Grid2X2 className="size-3.5" aria-hidden="true" /> Ruang kerja Anda
                        </p>
                        <Dialog.Title className="text-2xl font-semibold tracking-tight">
                            Menu {roleName}
                        </Dialog.Title>
                        <Dialog.Description className="mt-2 text-sm leading-relaxed text-muted-foreground">
                            Semua kebutuhan kerja, dalam satu tempat.
                        </Dialog.Description>
                    </div>
                    <Dialog.Close asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="Tutup menu"
                            className="size-11 shrink-0 rounded-xl bg-muted/60 text-muted-foreground hover:bg-secondary hover:text-primary"
                        >
                            <X className="size-5" aria-hidden="true" />
                        </Button>
                    </Dialog.Close>
                </div>
                <nav
                    aria-label={`Semua menu ${roleName}`}
                    className="staff-navigation-scroll min-h-0 overflow-y-auto overscroll-contain px-5 pb-5 sm:px-6"
                >
                    <WorkspaceMenuLinks items={items} role={role} onNavigate={onNavigate} />
                </nav>
                <div className="cashier-menu-footer shrink-0 border-t border-primary/10 bg-secondary/30 px-5 pt-3 text-xs text-muted-foreground sm:px-6">
                    <span className="inline-flex items-center gap-2">
                        <span className="size-1.5 rounded-full bg-primary" aria-hidden="true" />{' '}
                        Pilih halaman untuk melanjutkan pekerjaan.
                    </span>
                </div>
            </Dialog.Content>
        </Dialog.Portal>
    );
}

function WorkspaceMenuLinks({
    items,
    role = 'cashier',
    onNavigate,
}: {
    items: NavigationItem[];
    role?: User['role'];
    onNavigate: () => void;
}) {
    const groups =
        role === 'manager' ? navigationGroupsForRole(role, items) : [{ label: '', items }];
    return (
        <div className="space-y-5">
            {groups.map((group) => (
                <section key={group.label || 'menu'}>
                    {group.label && (
                        <h3 className="mb-3 px-1 text-xs font-medium text-muted-foreground">
                            {group.label}
                        </h3>
                    )}
                    <div className="grid grid-cols-2 gap-2.5 sm:gap-3">
                        {group.items.map((item) => {
                            const active = navigationIsActive(item);
                            return (
                                <Link
                                    key={navigationKey(item)}
                                    href={navigationHref(item)}
                                    onClick={onNavigate}
                                    aria-current={active ? 'page' : undefined}
                                    className={cn(
                                        'cashier-menu-link group relative flex min-w-0 flex-col rounded-2xl border p-3.5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring sm:p-4',
                                        active
                                            ? 'border-primary/20 bg-secondary/80'
                                            : 'border-transparent bg-muted/55 hover:border-primary/15 hover:bg-secondary/65',
                                    )}
                                >
                                    <div className="mb-3 flex items-center justify-between gap-2">
                                        <span
                                            className={cn(
                                                'flex size-9 shrink-0 items-center justify-center rounded-xl',
                                                active
                                                    ? 'bg-primary text-primary-foreground'
                                                    : 'bg-card text-primary',
                                            )}
                                        >
                                            <item.icon
                                                className="size-[1.125rem]"
                                                aria-hidden="true"
                                            />
                                        </span>
                                        {active ? (
                                            <>
                                                <Check
                                                    className="size-4 text-primary"
                                                    aria-hidden="true"
                                                />
                                                <span className="sr-only">Halaman saat ini</span>
                                            </>
                                        ) : (
                                            <ArrowUpRight
                                                className="size-4 text-primary/35 transition-transform group-hover:-translate-y-0.5 group-hover:translate-x-0.5 motion-reduce:transform-none"
                                                aria-hidden="true"
                                            />
                                        )}
                                    </div>
                                    <span className="text-[13px] font-semibold leading-snug text-foreground sm:text-sm">
                                        {item.label}
                                    </span>
                                    <span className="mt-1.5 text-[11px] leading-relaxed text-muted-foreground sm:text-xs">
                                        {descriptions[navigationKey(item)]}
                                    </span>
                                </Link>
                            );
                        })}
                    </div>
                </section>
            ))}
        </div>
    );
}

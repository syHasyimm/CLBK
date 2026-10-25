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
import { Link, usePage } from '@inertiajs/react';
import { ChevronRight, Check, LogOut, X } from 'lucide-react';
import { Dialog } from 'radix-ui';
import type { ComponentProps } from 'react';
import PwaInstallButton from '@/components/pwa/PwaInstallButton';

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
    'whatsapp.index': 'Status notifikasi transaksi',
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
    const { auth } = usePage().props;
    return (
        <Dialog.Portal>
            <Dialog.Overlay className="cashier-menu-overlay fixed inset-0 z-40 bg-foreground/35 backdrop-blur-sm print:hidden" />
            <Dialog.Content
                onCloseAutoFocus={onCloseAutoFocus}
                className="cashier-menu-dialog fixed z-50 flex flex-col overflow-hidden rounded-[1.75rem] border border-primary/10 bg-card outline-none print:hidden"
            >
                <div className="mobile-sheet-handle" aria-hidden="true" />
                <div className="workspace-menu-heading flex shrink-0 items-start justify-between gap-4 px-5 pb-5 pt-6 sm:px-6">
                    <div className="min-w-0">
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
                    <div className="mt-5 empty:hidden">
                        <PwaInstallButton />
                    </div>
                </nav>
                <div className="cashier-menu-footer shrink-0 border-t border-primary/10 px-5 pt-3 sm:px-6">
                    <p className="min-w-0 text-sm font-semibold">
                        {auth.user.name}
                        <span className="block text-xs font-normal text-muted-foreground">
                            {roleName === 'admin'
                                ? 'Manajer'
                                : roleName === 'kasir'
                                  ? 'Kasir'
                                  : 'Supir'}
                        </span>
                    </p>
                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="mobile-logout"
                    >
                        <LogOut aria-hidden="true" className="size-4" /> Keluar akun
                    </Link>
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
    const groups = navigationGroupsForRole(role, items).filter((group) => group.items.length > 0);
    return (
        <div className="space-y-5">
            {groups.map((group) => (
                <section key={group.label || 'menu'}>
                    {group.label && (
                        <h3 className="mb-3 px-1 text-sm font-semibold text-muted-foreground">
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
                                        'cashier-menu-link group relative flex min-w-0 rounded-2xl border p-3.5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring sm:p-4',
                                        active
                                            ? 'border-primary/20 bg-secondary/80'
                                            : 'border-transparent bg-muted/55 hover:border-primary/15 hover:bg-secondary/65',
                                    )}
                                >
                                    <div className="workspace-menu-icon flex shrink-0 items-center gap-2">
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
                                        <span className="sr-only">
                                            {active ? 'Halaman saat ini' : ''}
                                        </span>
                                    </div>
                                    <span className="workspace-menu-copy min-w-0">
                                        <span className="block text-sm font-semibold leading-snug text-foreground">
                                            {item.name === 'dashboard' ? 'Beranda' : item.label}
                                        </span>
                                        <span className="workspace-menu-description mt-1.5 block text-xs leading-relaxed text-muted-foreground">
                                            {descriptions[navigationKey(item)]}
                                        </span>
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
                                        <ChevronRight
                                            className="workspace-menu-chevron size-4 shrink-0 text-primary/35"
                                            aria-hidden="true"
                                        />
                                    )}
                                </Link>
                            );
                        })}
                    </div>
                </section>
            ))}
        </div>
    );
}

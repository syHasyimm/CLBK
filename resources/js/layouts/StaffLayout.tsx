import { PropsWithChildren, ReactNode, useEffect, useRef, useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { Grid2X2, LogOut } from 'lucide-react';
import { Dialog } from 'radix-ui';
import ApplicationLogo from '@/components/ApplicationLogo';
import ConnectionStatus from '@/components/common/ConnectionStatus';
import WorkspaceMenu from '@/components/common/WorkspaceMenu';
import StaffSidebar from '@/components/common/StaffSidebar';
import { Button } from '@/components/ui/button';
import { navigationForRole, navigationIsActive, type NavigationItem } from '@/lib/navigation';
import { cn } from '@/lib/utils';
import { roleLabels } from '@/types/master-data';
import PwaUpdateNotice from '@/components/pwa/PwaUpdateNotice';

const shortLabels: Record<string, string> = {
    dashboard: 'Dashboard',
    'customer-payments.index': 'Penimbang',
    'payroll.index': 'Gaji Muat',
    'cash.index': 'Kas',
    'weighings.index': 'Timbang',
    'factory-sales.index': 'Pabrik',
    'sort-sales.index': 'Sortir',
    'profile.edit': 'Profil',
};
const desktopMedia = '(min-width: 64rem)';

export default function StaffLayout({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const { auth, business, flash, errors } = usePage().props;
    const user = auth.user;
    const items = navigationForRole(user.role);
    const primaryNames =
        user.role === 'driver'
            ? ['dashboard', 'weighings.index', 'factory-sales.index', 'sort-sales.index']
            : ['dashboard', 'customer-payments.index', 'payroll.index', 'cash.index'];
    const hasMenu = items.some((item) => !primaryNames.includes(item.name));
    const primary = primaryNames.map((name) => items.find((item) => item.name === name)!);
    const moreActive = items.some(
        (item) => !primaryNames.includes(item.name) && navigationIsActive(item),
    );
    const [menuOpen, setMenuOpen] = useState(false);
    const desktopHeading = useRef<HTMLParagraphElement>(null);
    const activeItem = items.find(navigationIsActive);
    useEffect(() => {
        const viewport = window.matchMedia(desktopMedia);
        const closeMenuOnDesktop = () => {
            if (viewport.matches) setMenuOpen(false);
        };
        closeMenuOnDesktop();
        viewport.addEventListener('change', closeMenuOnDesktop);
        return () => viewport.removeEventListener('change', closeMenuOnDesktop);
    }, []);

    return (
        <div className="staff-layout min-h-svh">
            <a
                href="#main-content"
                className="staff-skip-link sr-only z-50 rounded-lg bg-card p-3 text-primary focus:not-sr-only focus:fixed focus:left-4 focus:top-4"
            >
                Lewati ke isi halaman
            </a>
            <StaffSidebar items={items} user={user} businessName={business.name} />
            <div className="min-w-0 lg:pl-64 print:pl-0">
                <ConnectionStatus />
                <PwaUpdateNotice />
                <header className="staff-header border-b border-primary/10 bg-card">
                    <div className="mx-auto flex min-h-20 max-w-7xl items-center gap-2.5 px-4 py-4 sm:gap-4 sm:px-8 lg:py-3">
                        <Link
                            href={route('dashboard')}
                            aria-label={business.name + ' · Dashboard'}
                            className="flex min-w-0 flex-1 items-center gap-3 rounded-xl focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring lg:hidden"
                        >
                            <span className="flex size-11 shrink-0 items-center justify-center rounded-2xl border border-primary/10 bg-secondary">
                                <ApplicationLogo className="size-7 text-primary" />
                            </span>
                            <span className="min-w-0">
                                <span
                                    className="block truncate text-[15px] font-semibold tracking-tight sm:text-base"
                                    title={business.name}
                                >
                                    {business.name}
                                </span>
                                <span className="mt-1 block truncate text-[10px] text-muted-foreground sm:text-[11px]">
                                    Pengelolaan usaha sawit
                                </span>
                            </span>
                        </Link>
                        <div className="hidden min-w-0 flex-1 lg:block">
                            <p className="text-[11px] text-muted-foreground">
                                Ruang kerja {roleLabels[user.role].toLowerCase()}
                            </p>
                            <p
                                ref={desktopHeading}
                                tabIndex={-1}
                                className="mt-1 truncate text-sm font-semibold outline-none"
                            >
                                {activeItem?.label ?? 'Rincian transaksi'}
                            </p>
                        </div>
                        <span className="shrink-0 rounded-full border border-primary/10 bg-secondary/60 px-2.5 py-1.5 text-[10px] font-semibold text-primary sm:px-3 sm:text-[11px]">
                            {roleLabels[user.role]}
                        </span>
                        <Button
                            variant="ghost"
                            size="icon"
                            asChild
                            className="size-11 shrink-0 rounded-xl border border-primary/10 bg-muted/40 text-primary hover:bg-secondary lg:w-auto lg:gap-2 lg:px-3"
                        >
                            <Link
                                href={route('logout')}
                                method="post"
                                as="button"
                                aria-label="Keluar dari akun"
                                title="Keluar dari akun"
                            >
                                <LogOut className="size-4" aria-hidden="true" />
                                <span className="hidden text-xs lg:inline">Keluar</span>
                            </Link>
                        </Button>
                    </div>
                </header>
                <main
                    id="main-content"
                    tabIndex={-1}
                    className="staff-main mx-auto min-w-0 max-w-7xl px-4 pt-6 sm:px-8 sm:pt-9"
                >
                    {header && (
                        <div className="staff-page-heading mb-6 min-w-0 sm:mb-8">{header}</div>
                    )}
                    {flash.success && (
                        <div
                            role="status"
                            className="mb-6 rounded-xl border border-primary/20 bg-secondary px-4 py-3 text-sm text-primary"
                        >
                            {flash.success}
                        </div>
                    )}
                    {errors.operation && (
                        <div
                            role="alert"
                            className="mb-6 rounded-xl border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive"
                        >
                            {errors.operation}
                        </div>
                    )}
                    {children}
                </main>
            </div>
            <Dialog.Root open={menuOpen} onOpenChange={setMenuOpen}>
                <nav
                    aria-label="Navigasi bawah"
                    className="staff-bottom-nav fixed inset-x-0 bottom-0 z-30 mx-auto border-t border-primary/10 bg-card/95 px-2 pt-2 backdrop-blur-lg md:bottom-4 md:max-w-xl md:rounded-2xl md:border md:px-3 lg:hidden print:hidden"
                >
                    <div className="grid grid-cols-5 gap-1">
                        {primary.slice(0, 2).map((item) => (
                            <BottomLink key={item.name} item={item} />
                        ))}
                        {hasMenu && (
                            <Dialog.Trigger asChild>
                                <button
                                    type="button"
                                    className={cn(
                                        'cashier-menu-trigger',
                                        (moreActive || menuOpen) && 'cashier-menu-trigger-active',
                                    )}
                                >
                                    <span className="cashier-menu-trigger-icon">
                                        <Grid2X2 className="size-[1.375rem]" aria-hidden="true" />
                                    </span>
                                    <span>Menu</span>
                                    {moreActive && (
                                        <span className="sr-only">
                                            Halaman saat ini ada di menu lainnya
                                        </span>
                                    )}
                                </button>
                            </Dialog.Trigger>
                        )}
                        {primary.slice(2).map((item) => (
                            <BottomLink key={item.name} item={item} />
                        ))}
                    </div>
                </nav>
                {hasMenu && (
                    <WorkspaceMenu
                        items={items}
                        role={user.role}
                        onNavigate={() => setMenuOpen(false)}
                        onCloseAutoFocus={(event) => {
                            if (window.matchMedia(desktopMedia).matches) {
                                event.preventDefault();
                                desktopHeading.current?.focus();
                            }
                        }}
                    />
                )}
            </Dialog.Root>
        </div>
    );
}

function BottomLink({ item }: { item: NavigationItem }) {
    const active = navigationIsActive(item);
    return (
        <Link
            href={route(item.name)}
            aria-label={item.label}
            aria-current={active ? 'page' : undefined}
            className={cn('staff-nav-item', active && 'staff-nav-item-active')}
        >
            <item.icon className="size-5" aria-hidden="true" />
            <span>{shortLabels[item.name] ?? item.label}</span>
        </Link>
    );
}

import ApplicationLogo from '@/components/ApplicationLogo';
import ConnectionStatus from '@/components/common/ConnectionStatus';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { Link, usePage } from '@inertiajs/react';
import { LogOut } from 'lucide-react';
import { PropsWithChildren, ReactNode } from 'react';

export default function AppLayout({ header, children }: PropsWithChildren<{ header?: ReactNode }>) {
    const user = usePage().props.auth.user;
    return (
        <div className="min-h-svh">
            <ConnectionStatus />
            <header className="border-b bg-card">
                <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-6 py-5 sm:px-8">
                    <Link href={route('dashboard')} className="flex items-center gap-3">
                        <ApplicationLogo className="size-10 text-primary" />
                        <span className="text-lg font-semibold tracking-tight">CLBK TOKE</span>
                    </Link>
                    <nav
                        aria-label="Navigasi utama"
                        className="order-3 flex w-full gap-1 sm:order-none sm:w-auto"
                    >
                        {[
                            { name: 'dashboard', label: 'Dashboard' },
                            { name: 'profile.edit', label: 'Profil' },
                        ].map((item) => (
                            <Link
                                key={item.name}
                                href={route(item.name)}
                                aria-current={route().current(item.name) ? 'page' : undefined}
                                className={cn(
                                    'rounded-md px-4 py-2 text-sm font-medium transition-colors hover:bg-muted',
                                    route().current(item.name) && 'bg-secondary text-primary',
                                )}
                            >
                                {item.label}
                            </Link>
                        ))}
                    </nav>
                    <div className="flex items-center gap-3">
                        <span className="hidden max-w-40 truncate text-sm text-muted-foreground md:block">
                            {user.name}
                        </span>
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={route('logout')} method="post" as="button">
                                <LogOut />
                                Keluar
                            </Link>
                        </Button>
                    </div>
                </div>
            </header>
            <main className="mx-auto max-w-6xl px-6 py-10 sm:px-8 sm:py-12">
                {header && <div className="mb-8">{header}</div>}
                {children}
            </main>
        </div>
    );
}

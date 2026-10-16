import { Link } from '@inertiajs/react';
import { useLayoutEffect, useRef } from 'react';
import ApplicationLogo from '@/components/ApplicationLogo';
import {
    navigationGroupsForRole,
    navigationHref,
    navigationIsActive,
    navigationKey,
    type NavigationItem,
} from '@/lib/navigation';
import { cn } from '@/lib/utils';
import type { User } from '@/types';
import { roleLabels } from '@/types/master-data';

const sidebarScrollPositions = new Map<number, number>();

export default function StaffSidebar({
    items,
    user,
    businessName,
}: {
    items: NavigationItem[];
    user: User;
    businessName: string;
}) {
    const navigation = useRef<HTMLElement>(null);
    useLayoutEffect(() => {
        const element = navigation.current;
        if (!element) return;

        // Page changes recreate the layout, so restore the sidebar before painting.
        element.scrollTop = sidebarScrollPositions.get(user.id) ?? 0;

        return () => {
            sidebarScrollPositions.set(user.id, element.scrollTop);
        };
    }, [user.id]);

    const groups = navigationGroupsForRole(user.role, items);
    const initials = user.name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toLocaleUpperCase('id-ID');

    return (
        <aside className="staff-sidebar fixed inset-y-0 left-0 z-20 hidden w-64 flex-col border-r border-primary/10 bg-card lg:flex print:hidden">
            <div className="px-5 pb-7 pt-8">
                <Link
                    href={route('dashboard')}
                    aria-label={businessName + ' · Dashboard'}
                    className="flex min-w-0 items-center gap-3 rounded-xl focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring"
                >
                    <span className="flex size-11 shrink-0 items-center justify-center rounded-2xl border border-primary/10 bg-secondary">
                        <ApplicationLogo className="size-7 text-primary" />
                    </span>
                    <span className="min-w-0">
                        <span
                            className="block truncate text-base font-semibold tracking-tight"
                            title={businessName}
                        >
                            {businessName}
                        </span>
                        <span className="mt-1 block text-[11px] text-muted-foreground">
                            Ruang kerja {roleLabels[user.role].toLowerCase()}
                        </span>
                    </span>
                </Link>
            </div>
            <nav
                ref={navigation}
                aria-label="Navigasi sidebar"
                className="staff-navigation-scroll min-h-0 flex-1 space-y-6 overflow-y-auto px-4 pb-6"
            >
                {groups.map((group) => (
                    <div key={group.label}>
                        <p className="mb-2 px-3 text-[11px] font-medium text-muted-foreground">
                            {group.label}
                        </p>
                        <div className="space-y-1">
                            {group.items.map((item) => {
                                const active = navigationIsActive(item);
                                return (
                                    <Link
                                        key={navigationKey(item)}
                                        href={navigationHref(item)}
                                        aria-current={active ? 'page' : undefined}
                                        className={cn(
                                            'group flex min-h-11 items-center gap-3 rounded-xl px-3 py-3 text-[13px] font-medium transition-colors focus-visible:outline-2 focus-visible:outline-ring',
                                            active
                                                ? 'bg-primary text-primary-foreground'
                                                : 'text-muted-foreground hover:bg-secondary/70 hover:text-primary',
                                        )}
                                    >
                                        <item.icon
                                            className="size-[1.125rem] shrink-0"
                                            aria-hidden="true"
                                        />
                                        <span className="min-w-0 flex-1">{item.label}</span>
                                        {active && (
                                            <span
                                                className="size-1.5 shrink-0 rounded-full bg-primary-foreground/75"
                                                aria-hidden="true"
                                            />
                                        )}
                                    </Link>
                                );
                            })}
                        </div>
                    </div>
                ))}
            </nav>
            <div className="mx-4 mb-5 flex min-w-0 items-center gap-3 rounded-2xl border border-primary/10 bg-secondary/40 p-3.5">
                <span
                    className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-card text-xs font-semibold text-primary"
                    aria-hidden="true"
                >
                    {initials || 'A'}
                </span>
                <div className="min-w-0">
                    <p className="truncate text-xs font-semibold" title={user.name}>
                        {user.name}
                    </p>
                    <p className="mt-1 text-[11px] text-muted-foreground">
                        {roleLabels[user.role]}
                    </p>
                </div>
            </div>
        </aside>
    );
}

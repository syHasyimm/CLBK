import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowUpRight, CalendarDays, Plus } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import { Button } from '@/components/ui/button';
import type { PeriodProps } from '@/features/reports/components/PeriodOverview';
import DashboardOverview from '@/features/dashboard/components/DashboardOverview';
import { dashboardDate } from '@/features/dashboard/components/DashboardFilters';
export default function Dashboard(props: PeriodProps) {
    const { auth } = usePage().props;
    const driverOrManager = auth.user.role !== 'cashier';
    return (
        <AppLayout
            header={
                <div className="dashboard flex flex-wrap items-end justify-between gap-5">
                    <div>
                        <p className="mb-2 flex items-center gap-2 text-xs font-medium text-muted-foreground">
                            <span className="size-1.5 rounded-full bg-primary" aria-hidden="true" />
                            Dashboard
                        </p>
                        <h1 className="dashboard-heading text-3xl font-semibold tracking-tight sm:text-4xl">
                            Ringkasan usaha
                        </h1>
                        <p className="mt-2.5 text-sm text-muted-foreground">
                            Selamat datang, {auth.user.name.split(' ')[0]}. Pantau perkembangan
                            usaha Anda.
                        </p>
                    </div>
                    <div className="flex w-full flex-col gap-3 sm:w-auto sm:items-end">
                        <p className="flex items-center gap-2 text-xs text-muted-foreground">
                            <CalendarDays className="size-3.5" aria-hidden="true" />
                            {dashboardDate(props.summary.as_of_date, true)}
                            <span className="text-[10px]">WIB</span>
                        </p>
                        <div className="flex flex-wrap gap-2">
                            {auth.user.role !== 'driver' && (
                                <Button
                                    asChild
                                    variant="outline"
                                    className="h-10 rounded-lg bg-card"
                                >
                                    <Link href={route('reports.index', props.filters)}>
                                        Buka laporan
                                        <ArrowUpRight />
                                    </Link>
                                </Button>
                            )}
                            {driverOrManager && (
                                <Button asChild className="h-10 rounded-lg">
                                    <Link href={route('weighings.create')}>
                                        <Plus />
                                        Catat timbangan
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </div>
                </div>
            }
        >
            <Head title="Dashboard" />
            <DashboardOverview {...props} />
        </AppLayout>
    );
}

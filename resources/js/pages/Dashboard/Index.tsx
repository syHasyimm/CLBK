import AppLayout from '@/layouts/AppLayout';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Sprout, ArrowUpRight } from 'lucide-react';

export default function Dashboard({ auth }: PageProps) {
    return (
        <AppLayout
            header={
                <>
                    <p className="mb-2 text-sm text-muted-foreground">Ruang kerja</p>
                    <h1 className="text-3xl font-semibold tracking-tight">
                        Selamat datang, {auth.user.name}.
                    </h1>
                </>
            }
        >
            <Head title="Dashboard" />
            <Card className="py-0 shadow-none">
                <CardContent className="flex min-h-72 flex-col items-center justify-center px-6 py-12 text-center">
                    <div className="mb-6 rounded-full bg-secondary p-4">
                        <Sprout className="size-8 text-primary" />
                    </div>
                    <h2 className="text-xl font-semibold">Usaha sawit, tercatat dengan rapi.</h2>
                    <p className="mt-3 max-w-md text-sm leading-relaxed text-muted-foreground">
                        Aktivitas harian Anda akan tampil di sini. Pastikan informasi akun Anda
                        sudah sesuai.
                    </p>
                    <Button className="mt-7" variant="outline" asChild>
                        <Link href={route('profile.edit')}>
                            Lihat profil
                            <ArrowUpRight />
                        </Link>
                    </Button>
                </CardContent>
            </Card>
        </AppLayout>
    );
}

import AppLayout from '@/layouts/AppLayout';
import { Card, CardContent } from '@/components/ui/card';
import { PageProps } from '@/types';
import { Head } from '@inertiajs/react';
import UpdatePasswordForm from '@/features/profile/components/UpdatePasswordForm';
import UpdateProfileInformationForm from '@/features/profile/components/UpdateProfileInformationForm';

export default function Edit({
    mustVerifyEmail,
    status,
}: PageProps<{ mustVerifyEmail: boolean; status?: string }>) {
    return (
        <AppLayout
            header={
                <>
                    <p className="mb-2 text-sm text-muted-foreground">Pengaturan akun</p>
                    <h1 className="text-3xl font-semibold tracking-tight">Profil Anda</h1>
                </>
            }
        >
            <Head title="Profil" />
            <div className="grid items-start gap-6 lg:grid-cols-2">
                <Card className="py-0 shadow-none">
                    <CardContent className="p-6 sm:p-8">
                        <UpdateProfileInformationForm
                            mustVerifyEmail={mustVerifyEmail}
                            status={status}
                        />
                    </CardContent>
                </Card>
                <Card className="py-0 shadow-none">
                    <CardContent className="p-6 sm:p-8">
                        <UpdatePasswordForm />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

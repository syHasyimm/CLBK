import { Head, Link } from '@inertiajs/react';
import { MessageCircle, ArrowRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout';
import PageHeading from '@/components/common/PageHeading';
import BackupPanel, { BackupStatus } from '@/features/settings/components/BackupPanel';
import BusinessSettingsForm, {
    BusinessSettings,
} from '@/features/settings/components/BusinessSettingsForm';

export default function Business({
    settings,
    backups,
}: {
    settings: BusinessSettings;
    backups: BackupStatus;
}) {
    return (
        <AppLayout
            header={
                <PageHeading
                    title="Pengaturan Usaha"
                    description="Atur identitas usaha dan penomoran bukti transaksi."
                />
            }
        >
            <Head title="Pengaturan Usaha" />
            <section className="mb-6 flex max-w-3xl flex-wrap items-center justify-between gap-5 rounded-xl border bg-card p-6">
                <div className="flex items-start gap-3">
                    <MessageCircle className="mt-0.5 size-5 text-primary" aria-hidden="true" />
                    <div>
                        <h2 className="font-semibold">Notifikasi WhatsApp</h2>
                        <p className="mt-2 max-w-lg text-sm leading-relaxed text-muted-foreground">
                            Atur token Fonnte, uji koneksi perangkat, dan pantau pengiriman kepada
                            konsumen.
                        </p>
                    </div>
                </div>
                <Button variant="outline" asChild>
                    <Link href={route('whatsapp.index')}>
                        Pengaturan WhatsApp
                        <ArrowRight className="size-4" />
                    </Link>
                </Button>
            </section>
            <BusinessSettingsForm settings={settings} retention={backups.retention} />
            <BackupPanel status={backups} />
        </AppLayout>
    );
}

import { Head } from '@inertiajs/react';
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
            <BusinessSettingsForm settings={settings} retention={backups.retention} />
            <BackupPanel status={backups} />
        </AppLayout>
    );
}

import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import FormField from '@/components/common/FormField';
import UnsavedChangesGuard from '@/components/common/UnsavedChangesGuard';

export interface BusinessSettings {
    business_name: string;
    receipt_prefix: string;
    business_timezone: string;
    weight_tolerance_basis_points: number;
}
export default function BusinessSettingsForm({
    settings,
    retention,
}: {
    settings: BusinessSettings;
    retention: { daily: number; weekly: number };
}) {
    const form = useForm({
        business_name: settings.business_name,
        receipt_prefix: settings.receipt_prefix,
        backup_keep_daily: retention.daily,
        backup_keep_weekly: retention.weekly,
    });
    function submit(event: FormEvent) {
        event.preventDefault();
        form.patch(route('settings.business.update'), {
            preserveScroll: true,
            onSuccess: () => form.setDefaults(),
        });
    }
    return (
        <form onSubmit={submit} className="max-w-3xl rounded-xl border bg-card p-6 sm:p-8">
            <UnsavedChangesGuard dirty={form.isDirty && !form.processing} />
            <div className="grid gap-6 sm:grid-cols-2">
                <FormField
                    field={{
                        key: 'business_name',
                        label: 'Nama usaha',
                        type: 'text',
                        required: true,
                        maxLength: 150,
                    }}
                    value={form.data.business_name}
                    error={form.errors.business_name}
                    onChange={(v) => form.setData('business_name', v)}
                />
                <FormField
                    field={{
                        key: 'receipt_prefix',
                        label: 'Awalan nomor bukti',
                        type: 'text',
                        required: true,
                        maxLength: 12,
                    }}
                    value={form.data.receipt_prefix}
                    error={form.errors.receipt_prefix}
                    onChange={(v) => form.setData('receipt_prefix', v.toUpperCase())}
                />
            </div>
            <p className="mt-3 text-xs text-muted-foreground">
                Awalan hanya memakai huruf A–Z dan angka; perubahan berlaku untuk bukti baru.
            </p>
            <dl className="mt-8 grid gap-5 border-t pt-6 sm:grid-cols-2">
                <div>
                    <dt className="text-xs text-muted-foreground">Zona waktu usaha</dt>
                    <dd className="mt-2 font-medium">{settings.business_timezone} (WIB)</dd>
                </div>
                <div>
                    <dt className="text-xs text-muted-foreground">Toleransi selisih berat</dt>
                    <dd className="mt-2 font-medium">
                        {settings.weight_tolerance_basis_points / 100}% dari muatan awal
                    </dd>
                </div>
                <div>
                    <dt className="text-xs text-muted-foreground">Ketelitian timbangan</dt>
                    <dd className="mt-2 font-medium">3 desimal kg / 1 gram</dd>
                </div>
                <div>
                    <dt className="text-xs text-muted-foreground">Format bukti</dt>
                    <dd className="mt-2 font-medium">A4</dd>
                </div>
            </dl>
            <fieldset className="mt-8 grid gap-5 border-t pt-6 sm:grid-cols-2">
                <legend className="pt-6 text-sm font-semibold">Retensi cadangan terjadwal</legend>
                <FormField
                    field={{
                        key: 'backup_keep_daily',
                        label: 'Jumlah cadangan harian',
                        type: 'number',
                        required: true,
                    }}
                    value={String(form.data.backup_keep_daily)}
                    error={form.errors.backup_keep_daily}
                    onChange={(value) => form.setData('backup_keep_daily', Number(value))}
                />
                <FormField
                    field={{
                        key: 'backup_keep_weekly',
                        label: 'Jumlah cadangan mingguan',
                        type: 'number',
                        required: true,
                    }}
                    value={String(form.data.backup_keep_weekly)}
                    error={form.errors.backup_keep_weekly}
                    onChange={(value) => form.setData('backup_keep_weekly', Number(value))}
                />
            </fieldset>
            <div className="mt-8 flex justify-end border-t pt-6">
                <Button disabled={form.processing}>
                    {form.processing ? 'Menyimpan...' : 'Simpan pengaturan'}
                </Button>
            </div>
        </form>
    );
}

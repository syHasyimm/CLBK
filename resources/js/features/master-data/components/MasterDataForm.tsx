import ActiveCheckbox from '@/components/common/ActiveCheckbox';
import FormField, { FieldDefinition, SelectOption } from '@/components/common/FormField';
import UnsavedChangesGuard from '@/components/common/UnsavedChangesGuard';
import InputError from '@/components/InputError';
import { Button } from '@/components/ui/button';
import type { MasterRecord } from '@/types/master-data';
import { Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import WhatsAppConsent from '@/features/settings/components/WhatsAppConsent';

export interface MasterFormProps {
    resource: string;
    title: string;
    fields: FieldDefinition[];
    options: Record<string, SelectOption[]>;
    record: MasterRecord | null;
}
export default function MasterDataForm({ resource, fields, options, record }: MasterFormProps) {
    const initial: Record<string, string | boolean> = { is_active: record?.is_active ?? true };
    if (resource === 'customers') initial.whatsapp_enabled = record?.whatsapp_enabled === true;
    fields.forEach((field) => {
        initial[field.key] =
            record?.[field.key] === null || record?.[field.key] === undefined
                ? ''
                : String(record[field.key]);
    });
    const form = useForm(initial);
    function submit(event: FormEvent) {
        event.preventDefault();
        if (record) form.patch(route('master-data.update', [resource, record.id]));
        else form.post(route('master-data.store', resource));
    }
    return (
        <form onSubmit={submit} className="max-w-3xl rounded-xl border bg-card p-6 sm:p-8">
            <UnsavedChangesGuard dirty={form.isDirty && !form.processing} />
            <div className="grid gap-6 sm:grid-cols-2">
                {fields.map((field) => (
                    <FormField
                        key={field.key}
                        field={field}
                        value={String(form.data[field.key] ?? '')}
                        error={form.errors[field.key]}
                        options={options[field.key]}
                        onChange={(value) => form.setData(field.key, value)}
                    />
                ))}
            </div>
            {resource === 'factories' && (
                <p className="mt-6 text-sm leading-relaxed text-muted-foreground">
                    Harga TBS pabrik dikelola terpisah melalui riwayat harga.
                </p>
            )}
            {resource === 'customers' && (
                <div className="mt-6">
                    <WhatsAppConsent
                        checked={Boolean(form.data.whatsapp_enabled)}
                        onChange={(value) => form.setData('whatsapp_enabled', value)}
                        error={form.errors.whatsapp_enabled}
                    />
                </div>
            )}
            <div className="mt-7">
                <ActiveCheckbox
                    value={Boolean(form.data.is_active)}
                    onChange={(value) => form.setData('is_active', value)}
                />
                <InputError message={form.errors.is_active} className="mt-2" />
            </div>
            <div className="mobile-action-dock mt-8 flex flex-wrap justify-end gap-3 border-t pt-6">
                <Button variant="outline" asChild className="mobile-secondary-action">
                    <Link href={route('master-data.index', resource)}>Batal</Link>
                </Button>
                <Button className="w-full lg:w-auto" disabled={form.processing}>
                    {form.processing ? 'Menyimpan...' : 'Simpan data'}
                </Button>
            </div>
        </form>
    );
}

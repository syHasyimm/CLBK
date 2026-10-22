import { type FormEvent } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Check, LoaderCircle, UserRoundPlus } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import FormField, { type FieldDefinition, type SelectOption } from '@/components/common/FormField';
import UnsavedChangesGuard from '@/components/common/UnsavedChangesGuard';
import InputError from '@/components/InputError';
import { Button } from '@/components/ui/button';
import WhatsAppConsent from '@/features/settings/components/WhatsAppConsent';

export default function Create({
    fields,
    options = {},
}: {
    fields: FieldDefinition[];
    options?: Record<string, SelectOption[]>;
}) {
    const initial: Record<string, string | boolean> = Object.fromEntries(
        fields.map((field) => [field.key, '']),
    );
    initial.whatsapp_enabled = false;
    const form = useForm(initial);

    function submit(event: FormEvent) {
        event.preventDefault();
        if (form.processing) return;
        form.post(route('customers.store'), { preserveScroll: true });
    }

    return (
        <AppLayout
            header={
                <div>
                    <Link
                        href={route('weighings.create')}
                        className="mb-4 inline-flex items-center gap-2 text-xs font-medium text-primary hover:underline focus-visible:rounded-sm focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring"
                    >
                        <ArrowLeft className="size-3.5" aria-hidden="true" />
                        Catat timbangan
                    </Link>
                    <div className="flex items-start gap-4">
                        <span className="hidden size-12 shrink-0 items-center justify-center rounded-2xl border border-primary/10 bg-secondary text-primary sm:flex">
                            <UserRoundPlus className="size-6" aria-hidden="true" />
                        </span>
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                                Tambah konsumen
                            </h1>
                            <p className="mt-2 max-w-xl text-sm leading-relaxed text-muted-foreground">
                                Catat identitas konsumen baru. Setelah disimpan, konsumen langsung
                                dipilih pada formulir timbangan.
                            </p>
                        </div>
                    </div>
                </div>
            }
        >
            <Head title="Tambah Konsumen" />
            <form
                onSubmit={submit}
                aria-label="Tambah konsumen"
                aria-busy={form.processing}
                className="max-w-3xl rounded-2xl border bg-card"
            >
                <UnsavedChangesGuard dirty={form.isDirty && !form.processing} />
                <div className="border-b px-5 py-5 sm:px-6">
                    <h2 className="text-sm font-semibold">Identitas konsumen</h2>
                    <p className="mt-1.5 text-xs leading-relaxed text-muted-foreground">
                        Nama wajib diisi. Nomor telepon dan informasi kebun dapat dilengkapi jika
                        tersedia.
                    </p>
                </div>
                <div className="grid gap-5 px-5 py-6 sm:grid-cols-2 sm:px-6">
                    {fields.map((field) => (
                        <div
                            key={field.key}
                            className={field.type === 'textarea' ? 'sm:col-span-2' : undefined}
                        >
                            <FormField
                                field={field}
                                value={String(form.data[field.key] ?? '')}
                                error={form.errors[field.key]}
                                options={options[field.key]}
                                onChange={(value) => form.setData(field.key, value)}
                            />
                        </div>
                    ))}
                    <div className="sm:col-span-2">
                        <WhatsAppConsent
                            checked={Boolean(form.data.whatsapp_enabled)}
                            onChange={(value) => form.setData('whatsapp_enabled', value)}
                            error={form.errors.whatsapp_enabled}
                        />
                    </div>
                    <InputError message={form.errors.operation} className="sm:col-span-2" />
                </div>
                <div className="flex flex-wrap items-center justify-end gap-3 border-t px-5 py-5 sm:px-6">
                    <Button variant="outline" asChild>
                        <Link href={route('weighings.create')}>Batal</Link>
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? (
                            <LoaderCircle
                                className="size-4 animate-spin motion-reduce:animate-none"
                                aria-hidden="true"
                            />
                        ) : (
                            <Check className="size-4" aria-hidden="true" />
                        )}
                        {form.processing ? 'Menyimpan...' : 'Simpan konsumen'}
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}

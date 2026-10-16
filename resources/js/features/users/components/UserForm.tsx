import { Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import FormField, { SelectOption } from '@/components/common/FormField';
import ActiveCheckbox from '@/components/common/ActiveCheckbox';
import InputError from '@/components/InputError';
import UnsavedChangesGuard from '@/components/common/UnsavedChangesGuard';
import type { User } from '@/types';

export interface UserFormProps {
    record: User | null;
    roles: SelectOption[];
    vehicles: SelectOption[];
}

export default function UserForm({ record, roles, vehicles }: UserFormProps) {
    const form = useForm({
        name: record?.name ?? '',
        email: record?.email ?? '',
        role: record?.role ?? 'driver',
        assigned_vehicle_id: String(record?.assigned_vehicle_id ?? ''),
        is_active: record?.is_active ?? true,
        password: '',
        password_confirmation: '',
    });
    function submit(event: FormEvent) {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            assigned_vehicle_id: data.role === 'driver' ? data.assigned_vehicle_id || null : null,
        }));
        const options = { onSuccess: () => form.reset('password', 'password_confirmation') };
        if (record) form.patch(route('users.update', record.id), options);
        else form.post(route('users.store'), options);
    }
    return (
        <form onSubmit={submit} className="max-w-3xl rounded-xl border bg-card p-6 sm:p-8">
            <UnsavedChangesGuard dirty={form.isDirty && !form.processing} />
            <div className="grid gap-6 sm:grid-cols-2">
                <FormField
                    field={{
                        key: 'name',
                        label: 'Nama pengguna',
                        type: 'text',
                        required: true,
                        maxLength: 150,
                    }}
                    value={form.data.name}
                    error={form.errors.name}
                    onChange={(v) => form.setData('name', v)}
                />
                <FormField
                    field={{
                        key: 'email',
                        label: 'Email',
                        type: 'email',
                        required: true,
                        maxLength: 255,
                    }}
                    value={form.data.email}
                    error={form.errors.email}
                    onChange={(v) => form.setData('email', v)}
                />
                <FormField
                    field={{ key: 'role', label: 'Role', type: 'select', required: true }}
                    value={form.data.role}
                    options={roles}
                    error={form.errors.role}
                    onChange={(v) =>
                        form.setData((data) => ({
                            ...data,
                            role: v as User['role'],
                            assigned_vehicle_id: v === 'driver' ? data.assigned_vehicle_id : '',
                        }))
                    }
                />
                {form.data.role === 'driver' && (
                    <div>
                        <FormField
                            field={{
                                key: 'assigned_vehicle_id',
                                label: 'Mobil yang ditugaskan',
                                type: 'select',
                                required: false,
                            }}
                            value={form.data.assigned_vehicle_id}
                            options={vehicles}
                            error={form.errors.assigned_vehicle_id}
                            onChange={(value) => form.setData('assigned_vehicle_id', value)}
                        />
                        <p className="mt-2 text-xs leading-relaxed text-muted-foreground">
                            Supir memakai satu mobil ini untuk timbang dan penjualan. Tanpa
                            penugasan mobil aktif, pencatatan transaksi diblokir.
                        </p>
                    </div>
                )}
            </div>
            <div className="my-7">
                <ActiveCheckbox
                    label="Akun aktif"
                    value={form.data.is_active}
                    onChange={(v) => form.setData('is_active', v)}
                />
                <InputError message={form.errors.is_active} className="mt-2" />
            </div>
            <div className="border-t pt-6">
                <h2 className="font-semibold">{record ? 'Ganti kata sandi' : 'Kata sandi akun'}</h2>
                <p className="mt-2 text-sm text-muted-foreground">
                    {record ? 'Kosongkan jika kata sandi tidak diubah. ' : ''}Minimal 12 karakter
                    dengan huruf dan angka.
                </p>
                <div className="mt-5 grid gap-6 sm:grid-cols-2">
                    <FormField
                        field={{
                            key: 'password',
                            label: 'Kata sandi',
                            type: 'password',
                            required: !record,
                        }}
                        value={form.data.password}
                        error={form.errors.password}
                        onChange={(v) => form.setData('password', v)}
                    />
                    <FormField
                        field={{
                            key: 'password_confirmation',
                            label: 'Ulangi kata sandi',
                            type: 'password',
                            required: !record || !!form.data.password,
                        }}
                        value={form.data.password_confirmation}
                        onChange={(v) => form.setData('password_confirmation', v)}
                    />
                </div>
            </div>
            <div className="mt-8 flex justify-end gap-3 border-t pt-6">
                <Button variant="outline" asChild>
                    <Link href={route('users.index')}>Batal</Link>
                </Button>
                <Button disabled={form.processing}>
                    {form.processing ? 'Menyimpan...' : 'Simpan akun'}
                </Button>
            </div>
        </form>
    );
}

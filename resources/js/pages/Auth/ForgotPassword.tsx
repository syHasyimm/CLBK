import InputError from '@/components/InputError';
import PrimaryButton from '@/components/PrimaryButton';
import TextInput from '@/components/TextInput';
import InputLabel from '@/components/InputLabel';
import AuthLayout from '@/layouts/AuthLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { usePwaReloadGuard } from '@/lib/pwa-reload-guard';

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors, isDirty } = useForm({
        email: '',
    });
    usePwaReloadGuard(isDirty || processing);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('password.email'));
    };

    return (
        <AuthLayout>
            <Head title="Lupa kata sandi" />
            <h2 className="mb-4 text-2xl font-semibold">Lupa kata sandi?</h2>

            <div className="mb-4 text-sm text-gray-600">
                Masukkan email akun Anda untuk menerima tautan pengaturan ulang kata sandi.
            </div>

            {status && <div className="mb-4 text-sm font-medium text-green-600">{status}</div>}

            <form onSubmit={submit}>
                <InputLabel htmlFor="email" value="Email" />
                <TextInput
                    id="email"
                    type="email"
                    name="email"
                    autoComplete="username"
                    required
                    value={data.email}
                    className="mt-1 block w-full"
                    isFocused={true}
                    onChange={(e) => setData('email', e.target.value)}
                />

                <InputError message={errors.email} className="mt-2" />

                <div className="mt-4 flex items-center justify-end">
                    <PrimaryButton className="ms-4" disabled={processing}>
                        Kirim tautan pemulihan
                    </PrimaryButton>
                </div>
            </form>
        </AuthLayout>
    );
}

import InputError from '@/components/InputError';
import InputLabel from '@/components/InputLabel';
import PrimaryButton from '@/components/PrimaryButton';
import TextInput from '@/components/TextInput';
import { Transition } from '@headlessui/react';
import { Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { usePwaReloadGuard } from '@/lib/pwa-reload-guard';

export default function UpdateProfileInformation({
    mustVerifyEmail,
    status,
    className = '',
}: {
    mustVerifyEmail: boolean;
    status?: string;
    className?: string;
}) {
    const user = usePage().props.auth.user;

    const { data, setData, patch, reset, errors, processing, recentlySuccessful, isDirty } =
        useForm({
            name: user.name,
            email: user.email,
            current_password: '',
        });
    usePwaReloadGuard(isDirty || processing);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        patch(route('profile.update'), {
            onFinish: () => reset('current_password'),
        });
    };

    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-medium text-gray-900">Informasi profil</h2>

                <p className="mt-1 text-sm text-gray-600">
                    Perbarui nama dan alamat email akun Anda.
                </p>
            </header>

            <form onSubmit={submit} className="mt-6 space-y-6">
                <div>
                    <InputLabel htmlFor="name" value="Nama" />

                    <TextInput
                        id="name"
                        className="mt-1 block w-full"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        isFocused
                        autoComplete="name"
                    />

                    <InputError className="mt-2" message={errors.name} />
                </div>

                <div>
                    <InputLabel htmlFor="email" value="Email" />

                    <TextInput
                        id="email"
                        type="email"
                        className="mt-1 block w-full"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        required
                        autoComplete="username"
                    />

                    <InputError className="mt-2" message={errors.email} />
                </div>

                {data.email !== user.email && (
                    <div>
                        <InputLabel
                            htmlFor="profile_current_password"
                            value="Kata sandi saat ini"
                        />

                        <TextInput
                            id="profile_current_password"
                            type="password"
                            className="mt-1 block w-full"
                            value={data.current_password}
                            onChange={(e) => setData('current_password', e.target.value)}
                            autoComplete="current-password"
                            required
                        />

                        <p className="mt-1 text-sm text-gray-600">
                            Konfirmasi kata sandi untuk mengganti alamat email pemulihan akun.
                        </p>
                        <InputError className="mt-2" message={errors.current_password} />
                    </div>
                )}

                {mustVerifyEmail && user.email_verified_at === null && (
                    <div>
                        <p className="mt-2 text-sm text-gray-800">
                            Email Anda belum diverifikasi.
                            <Link
                                href={route('verification.send')}
                                method="post"
                                as="button"
                                className="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                            >
                                Kirim ulang tautan verifikasi.
                            </Link>
                        </p>

                        {status === 'verification-link-sent' && (
                            <div className="mt-2 text-sm font-medium text-green-600">
                                Tautan verifikasi baru telah dikirim ke email Anda.
                            </div>
                        )}
                    </div>
                )}

                <div className="flex flex-wrap items-center gap-4">
                    <PrimaryButton disabled={processing}>Simpan profil</PrimaryButton>

                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                    >
                        <p className="text-sm text-gray-600">Tersimpan.</p>
                    </Transition>
                </div>
            </form>
        </section>
    );
}

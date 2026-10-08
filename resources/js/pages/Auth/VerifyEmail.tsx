import PrimaryButton from '@/components/PrimaryButton';
import AuthLayout from '@/layouts/AuthLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function VerifyEmail({ status }: { status?: string }) {
    const { post, processing } = useForm({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('verification.send'));
    };

    return (
        <AuthLayout>
            <Head title="Verifikasi email" />

            <div className="mb-4 text-sm text-gray-600">
                Verifikasi alamat email Anda melalui tautan yang dikirim. Anda dapat meminta tautan
                baru bila belum menerimanya.
            </div>

            {status === 'verification-link-sent' && (
                <div className="mb-4 text-sm font-medium text-green-600">
                    Tautan verifikasi baru telah dikirim ke email Anda.
                </div>
            )}

            <form onSubmit={submit}>
                <div className="mt-4 flex items-center justify-between">
                    <PrimaryButton disabled={processing}>Kirim ulang tautan</PrimaryButton>

                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        Keluar
                    </Link>
                </div>
            </form>
        </AuthLayout>
    );
}

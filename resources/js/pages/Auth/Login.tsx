import InputError from '@/components/InputError';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/AuthLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { ArrowRight, LoaderCircle } from 'lucide-react';

export default function Login({
    status,
    canResetPassword,
}: {
    status?: string;
    canResetPassword: boolean;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });
    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('login'), { onFinish: () => reset('password') });
    };
    return (
        <AuthLayout>
            <Head title="Masuk" />
            <p className="mb-3 text-sm font-medium text-primary">Selamat datang kembali</p>
            <h2 className="text-3xl font-semibold tracking-tight">Masuk ke akun Anda</h2>
            <p className="mt-3 mb-9 text-sm leading-relaxed text-muted-foreground">
                Gunakan akun yang telah disiapkan untuk mengakses ruang kerja CLBK TOKE.
            </p>
            {status && (
                <p role="status" className="mb-6 rounded-md bg-secondary p-3 text-sm text-primary">
                    {status}
                </p>
            )}
            <form onSubmit={submit} className="space-y-6">
                <div className="space-y-2">
                    <Label htmlFor="email">Email</Label>
                    <Input
                        id="email"
                        name="email"
                        type="email"
                        value={data.email}
                        autoComplete="username"
                        autoFocus
                        required
                        className="h-11 bg-card"
                        placeholder="nama@contoh.com"
                        aria-invalid={!!errors.email}
                        aria-describedby={errors.email ? 'email-error' : undefined}
                        onChange={(event) => setData('email', event.target.value)}
                    />
                    <InputError id="email-error" message={errors.email} />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="password">Kata sandi</Label>
                    <Input
                        id="password"
                        name="password"
                        type="password"
                        value={data.password}
                        autoComplete="current-password"
                        required
                        className="h-11 bg-card"
                        aria-invalid={!!errors.password}
                        aria-describedby={errors.password ? 'password-error' : undefined}
                        onChange={(event) => setData('password', event.target.value)}
                    />
                    <InputError id="password-error" message={errors.password} />
                </div>
                <div className="flex flex-wrap items-center justify-between gap-3 text-sm">
                    <label className="flex cursor-pointer items-center gap-2">
                        <input
                            type="checkbox"
                            name="remember"
                            className="size-4"
                            checked={data.remember}
                            onChange={(event) => setData('remember', event.target.checked)}
                        />
                        Ingat saya
                    </label>
                    {canResetPassword && (
                        <Link
                            href={route('password.request')}
                            className="rounded text-primary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-ring"
                        >
                            Lupa kata sandi?
                        </Link>
                    )}
                </div>
                <Button type="submit" disabled={processing} className="h-11 w-full">
                    {processing ? (
                        <>
                            <LoaderCircle className="animate-spin" />
                            Sedang masuk...
                        </>
                    ) : (
                        <>
                            Masuk
                            <ArrowRight />
                        </>
                    )}
                </Button>
            </form>
        </AuthLayout>
    );
}

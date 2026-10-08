import ApplicationLogo from '@/components/ApplicationLogo';
import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

export default function AuthLayout({ children }: PropsWithChildren) {
    return (
        <main className="grid min-h-svh lg:grid-cols-[1fr_1.1fr]">
            <section className="relative hidden flex-col justify-between bg-primary p-12 text-primary-foreground lg:flex xl:p-16">
                <Link href="/" className="flex w-fit items-center gap-3">
                    <ApplicationLogo className="size-11 text-white/10" />
                    <span className="text-xl font-semibold tracking-tight">CLBK TOKE</span>
                </Link>
                <div className="max-w-lg py-16">
                    <p className="mb-6 text-sm font-medium tracking-[0.18em] text-white/65">
                        CATAT. JUAL. BAYAR.
                    </p>
                    <h1 className="text-5xl leading-[1.12] font-semibold tracking-tight xl:text-6xl">
                        Dari timbangan,
                        <br />
                        sampai pembayaran.
                    </h1>
                    <p className="mt-7 max-w-sm text-lg leading-relaxed text-white/75">
                        Satu tempat untuk mengelola kegiatan sawit Anda setiap hari.
                    </p>
                </div>
                <p className="text-sm text-white/60">Ruang kerja usaha sawit Anda.</p>
            </section>
            <section className="flex items-center justify-center px-6 py-12 sm:px-12">
                <div className="w-full max-w-sm">
                    <Link href="/" className="mb-10 flex w-fit items-center gap-3 lg:hidden">
                        <ApplicationLogo className="size-10 text-primary" />
                        <span className="text-xl font-semibold">CLBK TOKE</span>
                    </Link>
                    {children}
                    <p className="mt-10 text-center text-xs text-muted-foreground">
                        CLBK TOKE · Pengelolaan usaha sawit
                    </p>
                </div>
            </section>
        </main>
    );
}

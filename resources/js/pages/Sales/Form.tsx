import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Factory } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import PageHeading from '@/components/common/PageHeading';
import SaleForm, { type SaleFormProps } from '@/features/sales/components/SaleForm';
import { saleTitle } from '@/types/sales';
export default function Form(props: SaleFormProps) {
    const factorySale = props.kind === 'factory';
    const title = factorySale ? 'Catat penjualan pabrik' : 'Catat · ' + saleTitle(props.kind);
    return (
        <AppLayout
            header={
                factorySale ? (
                    <div className="factory-sales">
                        <Link
                            href={route('factory-sales.index')}
                            className="factory-text-link mb-4"
                        >
                            <ArrowLeft className="size-3.5" aria-hidden="true" />
                            Penjualan pabrik
                        </Link>
                        <div className="flex items-start gap-4">
                            <span className="hidden size-12 shrink-0 items-center justify-center rounded-2xl border border-primary/10 bg-secondary text-primary sm:flex">
                                <Factory className="size-6" aria-hidden="true" />
                            </span>
                            <div>
                                <h1 className="factory-heading text-2xl font-semibold sm:text-3xl">
                                    {title}
                                </h1>
                                <p className="mt-2 max-w-xl text-sm leading-relaxed text-muted-foreground">
                                    Catat hasil timbang pabrik, lalu periksa nilai nota sebelum
                                    menyimpan.
                                </p>
                            </div>
                        </div>
                    </div>
                ) : (
                    <PageHeading
                        title={title}
                        description="Berat dan harga diisi sesuai transaksi aktual."
                    />
                )
            }
        >
            <Head title={title} />
            <SaleForm {...props} />
        </AppLayout>
    );
}

import { rupiah } from '@/lib/currency';
import { weight } from '@/lib/weight';

export default function SaleSummary({
    preview,
    price,
}: {
    preview: {
        grams: number;
        gross_idr: number;
        deduction_idr: number;
        revenue_idr: number;
    } | null;
    price: number | null;
}) {
    return (
        <section
            className="rounded-xl border bg-card p-6"
            aria-label="Ringkasan penjualan"
            aria-live="polite"
        >
            <h2 className="font-semibold">Ringkasan penjualan</h2>
            <dl className="mt-5 space-y-4 text-sm">
                {[
                    ['Berat terjual', preview ? weight(preview.grams) : '—'],
                    ['Harga per kg', price != null ? rupiah(price) : '—'],
                    ['Nilai buah', preview ? rupiah(preview.gross_idr) : '—'],
                    ['Potongan', preview ? rupiah(preview.deduction_idr) : '—'],
                ].map(([label, value]) => (
                    <div key={label} className="flex justify-between gap-4">
                        <dt className="text-muted-foreground">{label}</dt>
                        <dd className="text-right font-medium tabular-nums">{value}</dd>
                    </div>
                ))}
            </dl>
            <div className="mt-5 border-t pt-5">
                <p className="text-sm text-muted-foreground">Nilai bersih penjualan</p>
                <p className="mt-2 text-2xl font-semibold tabular-nums">
                    {preview ? rupiah(preview.revenue_idr) : '—'}
                </p>
            </div>
            <p className="mt-4 text-sm leading-relaxed text-muted-foreground">
                Uang diterima dicatat melalui Penerimaan Penjualan, sebagian atau lunas.
            </p>
        </section>
    );
}

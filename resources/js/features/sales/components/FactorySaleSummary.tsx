import { ArrowRight, ReceiptText } from 'lucide-react';
import { rupiah } from '@/lib/currency';
import { weight } from '@/lib/weight';

export default function FactorySaleSummary({
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
            aria-labelledby="factory-summary-title"
            className="overflow-hidden rounded-2xl border border-primary/15 bg-secondary/60"
            aria-live="polite"
        >
            <div className="p-5 sm:p-6">
                <div className="flex items-center justify-between gap-3">
                    <h2 id="factory-summary-title" className="text-sm font-semibold text-primary">
                        Ringkasan penjualan
                    </h2>
                    <ReceiptText className="size-4 text-primary/60" aria-hidden="true" />
                </div>
                <p className="mt-5 text-[11px] text-primary/75">Nilai bersih penjualan</p>
                <p className="factory-heading mt-2 text-[clamp(1.75rem,3vw,2.5rem)] leading-tight font-semibold tabular-nums text-primary">
                    {preview ? rupiah(preview.revenue_idr) : '—'}
                </p>
                <p className="mt-2 text-xs leading-relaxed text-primary/75">
                    {preview
                        ? 'Nilai nota setelah potongan pabrik.'
                        : 'Pilih pabrik dan isi berat bersih untuk melihat perhitungan.'}
                </p>
                <dl className="mt-6 space-y-3.5 border-t border-primary/10 pt-5 text-xs">
                    {[
                        { label: 'Berat bersih', value: preview ? weight(preview.grams) : '—' },
                        { label: 'Harga per kg', value: price === null ? '—' : rupiah(price) },
                        { label: 'Nilai buah', value: preview ? rupiah(preview.gross_idr) : '—' },
                        { label: 'Potongan', value: preview ? rupiah(preview.deduction_idr) : '—' },
                    ].map(({ label, value }) => (
                        <div key={label} className="flex justify-between gap-4">
                            <dt className="text-muted-foreground">{label}</dt>
                            <dd className="text-right font-semibold tabular-nums">{value}</dd>
                        </div>
                    ))}
                </dl>
            </div>
            <div className="border-t border-primary/10 bg-card/60 p-5 sm:px-6">
                <p className="flex items-center gap-2 text-[11px] font-medium text-primary">
                    Nota penjualan <ArrowRight className="size-3" aria-hidden="true" /> Penerimaan
                    uang
                </p>
                <p className="mt-2 text-xs leading-relaxed text-muted-foreground">
                    Setelah nota tersimpan, uang yang diterima dicatat melalui Penerimaan Penjualan,
                    sebagian atau lunas.
                </p>
            </div>
        </section>
    );
}

import { rupiah } from '@/lib/currency';
export default function CostSummary({
    revenue,
    purchase,
    loading,
    status,
}: {
    revenue: number;
    purchase?: number | null;
    loading?: number | null;
    status: string;
}) {
    const complete = status === 'available' && purchase != null && loading != null;
    return (
        <div className="grid gap-4 rounded-xl border bg-card p-6 sm:grid-cols-2 lg:grid-cols-4">
            {[
                ['Biaya beli dilepas', complete ? rupiah(purchase) : 'Belum lengkap'],
                ['Biaya muat dilepas', complete ? rupiah(loading) : 'Belum lengkap'],
                ['Laba kotor', complete ? rupiah(revenue - purchase) : 'Belum lengkap'],
                [
                    'Laba setelah upah muat',
                    complete ? rupiah(revenue - purchase - loading) : 'Belum lengkap',
                ],
            ].map(([label, value]) => (
                <div key={label}>
                    <p className="text-xs text-muted-foreground">{label}</p>
                    <p className="mt-2 font-semibold tabular-nums">{value}</p>
                </div>
            ))}
        </div>
    );
}

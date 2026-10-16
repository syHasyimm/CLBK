import usePreviewRequest from '@/hooks/use-preview-request';
import type { SaleKind } from '@/types/sales';

type Summary = {
    price: { id: number | null; price_idr: number };
    preview: {
        grams: number;
        gross_idr: number;
        deduction_idr: number;
        revenue_idr: number;
    } | null;
};
export default function useSalePreview(kind: SaleKind, input: Record<string, string>) {
    const key = JSON.stringify(input);
    const { current, errors, busy, refresh } = usePreviewRequest<Summary>(
        key,
        kind + '-sales.preview',
        {
            initialBusy: false,
            canRequest: !(
                (kind === 'factory' && !input.factory_id) ||
                (kind === 'sort' &&
                    (!Number(input.sell_price_snapshot_idr) || input.sold_kg === '0'))
            ),
            fallbackMessage: 'Ringkasan belum dapat dimuat. Coba perbarui ringkasan.',
        },
    );
    return { data: current, errors, busy, ready: !busy && !!current?.preview, refresh };
}

import usePreviewRequest from '@/hooks/use-preview-request';
import type { Preview, Price } from '@/types/operations';

type PriceReference = Pick<
    Price,
    'id' | 'effective_from' | 'tbs_buy_price_idr' | 'sort_buy_price_idr' | 'loading_rate_idr'
>;
type Summary = { price: PriceReference; preview: Preview | null };

export default function useWeighingPreview(tbs: string, sort: string, workers: number[]) {
    const inputKey = JSON.stringify({
        tbs_kg: tbs || '0',
        sort_kg: sort || '0',
        worker_ids: workers,
    });
    const { current, errors, busy, refresh } = usePreviewRequest<Summary>(
        inputKey,
        'weighings.preview',
        {
            initialBusy: true,
            fallbackMessage: 'Ringkasan belum dapat dimuat. Perbarui ringkasan untuk mencoba lagi.',
        },
    );
    return {
        price: current?.price ?? null,
        preview: current?.preview ?? null,
        errors,
        busy,
        ready: !busy && !!current,
        refresh,
    };
}

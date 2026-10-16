import { router } from '@inertiajs/react';
import { useState } from 'react';

export default function useFilterVisit(
    url: string | (() => string),
    options: { preserveState?: boolean } = {},
) {
    const [busy, setBusy] = useState(false);
    function visit(values: Record<string, string | number>) {
        router.get(typeof url === 'function' ? url() : url, values, {
            preserveScroll: true,
            ...options,
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
        });
    }
    return { busy, visit };
}

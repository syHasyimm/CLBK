import axios from 'axios';
import { useEffect, useState } from 'react';

export default function usePreviewRequest<T>(
    inputKey: string,
    routeName: string,
    options: { initialBusy: boolean; canRequest?: boolean; fallbackMessage: string },
) {
    const { initialBusy, canRequest = true, fallbackMessage } = options;
    const [result, setResult] = useState<{ key: string; data: T } | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(initialBusy);
    const [revision, setRevision] = useState(0);

    useEffect(() => {
        const controller = new AbortController();
        setBusy(true);
        setErrors({});
        if (!canRequest) {
            setResult(null);
            setBusy(false);
            return () => controller.abort();
        }
        const timer = setTimeout(() => {
            axios
                .post<T>(route(routeName), JSON.parse(inputKey), { signal: controller.signal })
                .then(({ data }) => {
                    if (!controller.signal.aborted) setResult({ key: inputKey, data });
                })
                .catch((error) => {
                    if (controller.signal.aborted || axios.isCancel(error)) return;
                    setResult(null);
                    const messages = error.response?.data?.errors as
                        Record<string, string[]> | undefined;
                    setErrors(
                        messages
                            ? Object.fromEntries(
                                  Object.entries(messages).map(([key, value]) => [key, value[0]]),
                              )
                            : { operation: fallbackMessage },
                    );
                })
                .finally(() => {
                    if (!controller.signal.aborted) setBusy(false);
                });
        }, 250);
        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [inputKey, routeName, canRequest, fallbackMessage, revision]);

    return {
        current: result?.key === inputKey ? result.data : null,
        errors,
        busy,
        refresh: () => setRevision((value) => value + 1),
    };
}

import { useState } from 'react';
import { usePage } from '@inertiajs/react';

export default function useAmountVisibility() {
    const { auth } = usePage().props;
    const key = `clbk:amounts-hidden:${auth.user.id}`;
    const [hidden, setHidden] = useState(() => {
        try {
            return sessionStorage.getItem(key) === 'true';
        } catch {
            return false;
        }
    });
    function toggle() {
        setHidden((previous) => {
            const next = !previous;
            try {
                sessionStorage.setItem(key, String(next));
            } catch {
                // Visibility still works when browser storage is unavailable.
            }
            return next;
        });
    }
    return { hidden, toggle };
}

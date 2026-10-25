import { useEffect, useState } from 'react';

export default function useMobileKeyboard() {
    const [open, setOpen] = useState(false);

    useEffect(() => {
        const viewport = window.visualViewport;
        if (!viewport) return;
        let baseline = viewport.height;
        let frame = 0;
        const measure = () => {
            const element = document.activeElement;
            const editing =
                element instanceof HTMLTextAreaElement ||
                (element instanceof HTMLInputElement &&
                    !['checkbox', 'radio', 'button', 'submit'].includes(element.type));
            if (!editing) baseline = Math.max(viewport.height, window.innerHeight);
            setOpen(
                window.matchMedia('(width < 1024px)').matches &&
                    editing &&
                    Math.max(baseline, window.innerHeight) - viewport.height > 140,
            );
        };
        const update = () => {
            cancelAnimationFrame(frame);
            frame = requestAnimationFrame(measure);
        };
        viewport.addEventListener('resize', update);
        document.addEventListener('focusin', update);
        document.addEventListener('focusout', update);
        return () => {
            cancelAnimationFrame(frame);
            viewport.removeEventListener('resize', update);
            document.removeEventListener('focusin', update);
            document.removeEventListener('focusout', update);
        };
    }, []);

    return open;
}

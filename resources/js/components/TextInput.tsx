import { Input } from '@/components/ui/input';
import { forwardRef, InputHTMLAttributes, useEffect, useImperativeHandle, useRef } from 'react';

export default forwardRef<
    HTMLInputElement,
    InputHTMLAttributes<HTMLInputElement> & { isFocused?: boolean }
>(function TextInput({ isFocused = false, ...props }, ref) {
    const input = useRef<HTMLInputElement>(null);
    useImperativeHandle(ref, () => input.current!);
    useEffect(() => {
        if (isFocused) input.current?.focus();
    }, [isFocused]);
    return <Input ref={input} {...props} />;
});

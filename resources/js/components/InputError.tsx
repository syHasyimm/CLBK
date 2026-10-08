import { HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

export default function InputError({
    message,
    className,
    ...props
}: HTMLAttributes<HTMLParagraphElement> & { message?: string }) {
    return message ? (
        <p role="alert" className={cn('text-sm text-destructive', className)} {...props}>
            {message}
        </p>
    ) : null;
}

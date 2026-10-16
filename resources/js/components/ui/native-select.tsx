import { ComponentProps, forwardRef } from 'react';
import { cn } from '@/lib/utils';

export const NativeSelect = forwardRef<HTMLSelectElement, ComponentProps<'select'>>(
    ({ className, ...props }, ref) => (
        <select
            ref={ref}
            className={cn(
                'h-10 w-full rounded-md border border-input bg-card px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/30 disabled:opacity-50',
                className,
            )}
            {...props}
        />
    ),
);
NativeSelect.displayName = 'NativeSelect';

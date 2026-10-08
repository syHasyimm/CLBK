import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { LabelHTMLAttributes } from 'react';

export default function InputLabel({
    value,
    children,
    className,
    ...props
}: LabelHTMLAttributes<HTMLLabelElement> & { value?: string }) {
    return (
        <Label className={cn('block', className)} {...props}>
            {value ?? children}
        </Label>
    );
}

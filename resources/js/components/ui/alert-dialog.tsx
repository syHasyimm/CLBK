import { AlertDialog as Primitive } from 'radix-ui';
import { ComponentProps } from 'react';
import { cn } from '@/lib/utils';
import { buttonVariants } from '@/components/ui/button';

export const AlertDialog = Primitive.Root;
export const AlertDialogTrigger = Primitive.Trigger;
export function AlertDialogContent({
    className,
    ...props
}: ComponentProps<typeof Primitive.Content>) {
    return (
        <Primitive.Portal>
            <Primitive.Overlay className="fixed inset-0 z-50 bg-black/40" />
            <Primitive.Content
                className={cn(
                    'fixed left-1/2 top-1/2 z-50 grid w-[calc(100%-2rem)] max-w-md -translate-x-1/2 -translate-y-1/2 gap-5 rounded-xl border bg-card p-6 shadow-xl',
                    className,
                )}
                {...props}
            />
        </Primitive.Portal>
    );
}
export function AlertDialogTitle({ className, ...props }: ComponentProps<typeof Primitive.Title>) {
    return <Primitive.Title className={cn('text-lg font-semibold', className)} {...props} />;
}
export function AlertDialogDescription({
    className,
    ...props
}: ComponentProps<typeof Primitive.Description>) {
    return (
        <Primitive.Description
            className={cn('text-sm leading-relaxed text-muted-foreground', className)}
            {...props}
        />
    );
}
export function AlertDialogCancel({
    className,
    ...props
}: ComponentProps<typeof Primitive.Cancel>) {
    return (
        <Primitive.Cancel
            className={cn(buttonVariants({ variant: 'outline' }), className)}
            {...props}
        />
    );
}
export function AlertDialogAction({
    className,
    ...props
}: ComponentProps<typeof Primitive.Action>) {
    return <Primitive.Action className={cn(buttonVariants(), className)} {...props} />;
}

import { ReactElement } from 'react';
import {
    AlertDialog,
    AlertDialogTrigger,
    AlertDialogContent,
    AlertDialogTitle,
    AlertDialogDescription,
    AlertDialogCancel,
    AlertDialogAction,
} from '@/components/ui/alert-dialog';

export default function ConfirmDialog({
    title,
    description,
    actionLabel,
    destructive = false,
    onConfirm,
    children,
}: {
    title: string;
    description: string;
    actionLabel: string;
    destructive?: boolean;
    onConfirm: () => void;
    children: ReactElement;
}) {
    return (
        <AlertDialog>
            <AlertDialogTrigger asChild>{children}</AlertDialogTrigger>
            <AlertDialogContent>
                <AlertDialogTitle>{title}</AlertDialogTitle>
                <AlertDialogDescription>{description}</AlertDialogDescription>
                <div className="confirmation-actions flex flex-col-reverse justify-end gap-3 sm:flex-row">
                    <AlertDialogCancel>Batal</AlertDialogCancel>
                    <AlertDialogAction
                        className={
                            destructive
                                ? 'bg-destructive text-white hover:bg-destructive/90'
                                : undefined
                        }
                        onClick={onConfirm}
                    >
                        {actionLabel}
                    </AlertDialogAction>
                </div>
            </AlertDialogContent>
        </AlertDialog>
    );
}

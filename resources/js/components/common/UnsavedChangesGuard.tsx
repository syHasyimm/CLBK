import {
    AlertDialog,
    AlertDialogContent,
    AlertDialogTitle,
    AlertDialogDescription,
    AlertDialogCancel,
    AlertDialogAction,
} from '@/components/ui/alert-dialog';
import useUnsavedChanges from '@/hooks/use-unsaved-changes';

export default function UnsavedChangesGuard({ dirty }: { dirty: boolean }) {
    const dialog = useUnsavedChanges(dirty);
    return (
        <AlertDialog
            open={dialog.open}
            onOpenChange={(open) => {
                if (!open) dialog.cancel();
            }}
        >
            <AlertDialogContent>
                <AlertDialogTitle>Perubahan belum disimpan</AlertDialogTitle>
                <AlertDialogDescription>
                    Anda sudah mengubah form ini. Tinggalkan halaman dan abaikan perubahan?
                </AlertDialogDescription>
                <div className="flex flex-wrap justify-end gap-3">
                    <AlertDialogCancel onClick={dialog.cancel}>Tetap di sini</AlertDialogCancel>
                    <AlertDialogAction onClick={dialog.leave}>Tinggalkan halaman</AlertDialogAction>
                </div>
            </AlertDialogContent>
        </AlertDialog>
    );
}

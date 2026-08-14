import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { User } from '@/types/user';
import { useForm } from '@inertiajs/react';
import { IconAlertTriangle } from '@tabler/icons-react';

interface DeleteUserDialogProps {
    user: User | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

export function DeleteUserDialog({
    user,
    open,
    onOpenChange,
}: DeleteUserDialogProps) {
    const { delete: destroy, processing } = useForm({});

    const handleDelete = () => {
        if (!user) return;

        destroy(`/manage-users/${user.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-destructive">
                        <IconAlertTriangle className="h-5 w-5" />
                        Hapus Pengguna
                    </DialogTitle>
                    <DialogDescription>
                        Apakah Anda yakin ingin menghapus{' '}
                        <span className="font-semibold">{user?.name}</span>?
                        Tindakan ini tidak dapat dibatalkan.
                    </DialogDescription>
                </DialogHeader>

                <div className="rounded-lg border border-destructive/20 bg-destructive/10 p-4">
                    <div className="flex gap-3">
                        <IconAlertTriangle className="h-5 w-5 text-destructive" />
                        <div className="flex-1 space-y-1">
                            <p className="text-sm font-medium text-destructive">
                                Peringatan
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Ini akan menghapus akun pengguna dan semua data terkait secara permanen.
                            </p>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={processing}
                    >
                        Batal
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        onClick={handleDelete}
                        disabled={processing}
                    >
                        {processing ? 'Menghapus...' : 'Hapus Pengguna'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

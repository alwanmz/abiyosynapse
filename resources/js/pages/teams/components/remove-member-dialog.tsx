import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useForm } from '@inertiajs/react';
import { CircleMinus } from 'lucide-react';
import { FormEventHandler, ReactNode, useState } from 'react';

interface RemoveMemberDialogProps {
    teamId: number;
    userId: number;
    userName: string;
    trigger?: ReactNode;
}

export default function RemoveMemberDialog({
    teamId,
    userId,
    userName,
    trigger,
}: RemoveMemberDialogProps) {
    const [open, setOpen] = useState(false);

    const { delete: destroy, processing } = useForm({});

    const handleRemove: FormEventHandler = (e) => {
        e.preventDefault();
        destroy(`/teams/${teamId}/members/${userId}`, {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                {trigger ? (
                    trigger
                ) : (
                    <Button variant="ghost" size="sm">
                        <CircleMinus className="h-4 w-4 text-destructive" />
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Hapus Anggota Tim</DialogTitle>
                    <DialogDescription>
                        Apakah Anda yakin ingin menghapus anggota ini dari tim?
                    </DialogDescription>
                </DialogHeader>
                <div className="rounded-lg bg-muted p-4">
                    <p className="text-sm">
                        <span className="font-medium">{userName}</span> akan dihapus dari tim ini dan tidak akan lagi memiliki akses ke sumber daya tim.
                    </p>
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => setOpen(false)}
                        disabled={processing}
                    >
                        Batal
                    </Button>
                    <Button
                        variant="destructive"
                        onClick={handleRemove}
                        disabled={processing}
                    >
                        {processing ? 'Menghapus...' : 'Hapus Anggota'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { User } from '@/types/user';
import { useForm } from '@inertiajs/react';
import { IconShield } from '@tabler/icons-react';
import { useEffect } from 'react';

interface ChangeRoleDialogProps {
    user: User | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    availableRoles: Array<{ id: number; name: string; display_name: string }>;
}

export function ChangeRoleDialog({
    user,
    open,
    onOpenChange,
    availableRoles,
}: ChangeRoleDialogProps) {
    const { data, setData, put, processing, errors, reset } = useForm({
        role_id: user?.role?.id || '',
    });

    // Update form when user changes
    useEffect(() => {
        if (user) {
            setData('role_id', user.role?.id || '');
        }
    }, [user]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!user) return;

        put(`/manage-users/${user.id}/role`, {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => {
                onOpenChange(false);
                reset();
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <IconShield className="h-5 w-5" />
                        Ubah Peran Pengguna
                    </DialogTitle>
                    <DialogDescription>
                        Perbarui peran untuk{' '}
                        <span className="font-semibold">{user?.name}</span>.
                        Ini akan mengubah hak akses mereka.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit}>
                    <div className="grid gap-4 py-4">
                        <div className="grid gap-2">
                            <Label htmlFor="role">Peran Saat Ini</Label>
                            <div className="text-sm text-muted-foreground">
                                {user?.role?.display_name || 'Belum Ada Peran'}
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="role">Peran Baru</Label>
                            <Select
                                value={data.role_id.toString()}
                                onValueChange={(value) =>
                                    setData('role_id', parseInt(value))
                                }
                            >
                                <SelectTrigger id="role">
                                    <SelectValue placeholder="Pilih peran" />
                                </SelectTrigger>
                                <SelectContent className="max-h-72">
                                    {availableRoles.length === 0 ? (
                                        <div className="px-2 py-3 text-sm text-muted-foreground">
                                            Belum ada peran tersedia.
                                        </div>
                                    ) : availableRoles.map((role) => (
                                        <SelectItem
                                            key={role.id}
                                            value={role.id.toString()}
                                        >
                                            {role.display_name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.role_id && (
                                <p className="text-sm text-destructive">
                                    {errors.role_id}
                                </p>
                            )}
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
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Memperbarui...' : 'Perbarui Peran'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

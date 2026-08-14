import { AvatarUploadField } from '@/components/avatar-upload-field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { User } from '@/types/user';
import { useForm } from '@inertiajs/react';
import { IconEdit } from '@tabler/icons-react';
import { useEffect } from 'react';

interface EditUserDialogProps {
    user: User | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

export function EditUserDialog({
    user,
    open,
    onOpenChange,
}: EditUserDialogProps) {
    // We POST with a `_method=PUT` field instead of put() because Inertia's
    // put() doesn't support file uploads.
    const { data, setData, post, processing, errors, reset } = useForm<{
        _method: string;
        name: string;
        username: string;
        email: string;
        avatar: File | null;
        remove_avatar: boolean;
    }>({
        _method: 'put',
        name: user?.name || '',
        username: user?.username || '',
        email: user?.email || '',
        avatar: null,
        remove_avatar: false,
    });

    useEffect(() => {
        if (user) {
            setData({
                _method: 'put',
                name: user.name,
                username: user.username || '',
                email: user.email,
                avatar: null,
                remove_avatar: false,
            });
        }
    }, [user]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!user) return;

        post(`/manage-users/${user.id}`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
                reset();
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <IconEdit className="h-5 w-5" />
                        Edit Pengguna
                    </DialogTitle>
                    <DialogDescription>
                        Perbarui informasi pengguna untuk{' '}
                        <span className="font-semibold">{user?.name}</span>
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit}>
                    <div className="grid gap-4 py-4">
                        <AvatarUploadField
                            name={data.name}
                            currentUrl={user?.avatar_url ?? null}
                            onChange={({ file, remove }) => {
                                setData('avatar', file);
                                setData('remove_avatar', remove);
                            }}
                        />
                        {errors.avatar && (
                            <p className="text-sm text-destructive">
                                {errors.avatar}
                            </p>
                        )}

                        <div className="grid gap-2">
                            <Label htmlFor="edit-name">Nama Lengkap</Label>
                            <Input
                                id="edit-name"
                                placeholder="Masukkan nama lengkap"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                autoComplete="name"
                            />
                            {errors.name && (
                                <p className="text-sm text-destructive">
                                    {errors.name}
                                </p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="edit-username">Username</Label>
                            <Input
                                id="edit-username"
                                placeholder="contoh: john_doe"
                                value={data.username}
                                onChange={(e) =>
                                    setData('username', e.target.value)
                                }
                                autoComplete="username"
                            />
                            <p className="text-xs text-muted-foreground">
                                Digunakan untuk login. Hanya huruf, angka, underscore, dan strip.
                            </p>
                            {errors.username && (
                                <p className="text-sm text-destructive">
                                    {errors.username}
                                </p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="edit-email">Alamat Email</Label>
                            <Input
                                id="edit-email"
                                type="email"
                                placeholder="Masukkan alamat email"
                                value={data.email}
                                onChange={(e) =>
                                    setData('email', e.target.value)
                                }
                                autoComplete="email"
                            />
                            {errors.email && (
                                <p className="text-sm text-destructive">
                                    {errors.email}
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
                            {processing ? 'Menyimpan...' : 'Simpan Perubahan'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

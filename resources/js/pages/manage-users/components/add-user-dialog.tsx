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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useForm } from '@inertiajs/react';
import { IconUserPlus } from '@tabler/icons-react';

interface AddUserDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    availableRoles: Array<{ id: number; name: string; display_name: string }>;
}

export function AddUserDialog({
    open,
    onOpenChange,
    availableRoles,
}: AddUserDialogProps) {
    const { data, setData, post, processing, errors, reset } = useForm<{
        name: string;
        username: string;
        email: string;
        password: string;
        password_confirmation: string;
        role_id: string | number;
        avatar: File | null;
    }>({
        name: '',
        username: '',
        email: '',
        password: '',
        password_confirmation: '',
        role_id: '',
        avatar: null,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        post('/manage-users', {
            forceFormData: true,
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
            <DialogContent className="flex max-h-[92vh] max-w-3xl flex-col gap-0 overflow-hidden p-0">
                <DialogHeader className="border-b px-6 py-4 pr-12">
                    <DialogTitle className="flex items-center gap-2">
                        <IconUserPlus className="h-5 w-5" />
                        Tambah Pengguna Baru
                    </DialogTitle>
                    <DialogDescription>
                        Isi profil, kredensial login, lalu pilih peran pengguna.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="flex min-h-0 flex-1 flex-col">
                    <div className="grid min-h-0 gap-5 overflow-y-auto px-6 py-5">
                        <div className="rounded-lg border bg-muted/20 p-4">
                        <AvatarUploadField
                            name={data.name}
                            currentUrl={null}
                            onChange={({ file }) => setData('avatar', file)}
                        />
                        {errors.avatar && (
                            <p className="mt-2 text-sm text-destructive">
                                {errors.avatar}
                            </p>
                        )}
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Nama Lengkap</Label>
                            <Input
                                id="name"
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
                            <Label htmlFor="add-username">Username</Label>
                            <Input
                                id="add-username"
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
                            <Label htmlFor="add-email">Alamat Email</Label>
                            <Input
                                id="add-email"
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

                        <div className="grid gap-2">
                            <Label htmlFor="role">Peran</Label>
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

                        <div className="grid gap-4 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="password">Kata Sandi</Label>
                            <Input
                                id="password"
                                type="password"
                                placeholder="Masukkan kata sandi"
                                value={data.password}
                                onChange={(e) =>
                                    setData('password', e.target.value)
                                }
                                autoComplete="new-password"
                            />
                            {errors.password && (
                                <p className="text-sm text-destructive">
                                    {errors.password}
                                </p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                Konfirmasi Kata Sandi
                            </Label>
                            <Input
                                id="password_confirmation"
                                type="password"
                                placeholder="Konfirmasi kata sandi"
                                value={data.password_confirmation}
                                onChange={(e) =>
                                    setData(
                                        'password_confirmation',
                                        e.target.value,
                                    )
                                }
                                autoComplete="new-password"
                            />
                            {errors.password_confirmation && (
                                <p className="text-sm text-destructive">
                                    {errors.password_confirmation}
                                </p>
                            )}
                        </div>
                        </div>
                    </div>

                    <DialogFooter className="border-t px-6 py-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            disabled={processing}
                        >
                            Batal
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Membuat...' : 'Buat Pengguna'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

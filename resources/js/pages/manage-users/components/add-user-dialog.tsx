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
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useForm } from '@inertiajs/react';
import { IconUserPlus } from '@tabler/icons-react';
import { useState } from 'react';

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
    const [mode, setMode] = useState<'new' | 'existing'>('new');

    const createForm = useForm<{
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

    const inviteForm = useForm<{
        email: string;
        role_id: string | number;
    }>({
        email: '',
        role_id: '',
    });

    const handleClose = (nextOpen: boolean) => {
        if (!nextOpen) {
            createForm.reset();
            createForm.clearErrors();
            inviteForm.reset();
            inviteForm.clearErrors();
            setMode('new');
        }
        onOpenChange(nextOpen);
    };

    const handleCreateSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        createForm.post('/manage-users', {
            forceFormData: true,
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => handleClose(false),
        });
    };

    const handleInviteSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        inviteForm.post('/manage-users/invite', {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => handleClose(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogContent className="flex max-h-[92vh] max-w-3xl flex-col gap-0 overflow-hidden p-0">
                <DialogHeader className="border-b px-6 py-4 pr-12">
                    <DialogTitle className="flex items-center gap-2">
                        <IconUserPlus className="h-5 w-5" />
                        Tambah Pengguna
                    </DialogTitle>
                    <DialogDescription>
                        Buat pengguna baru, atau tambahkan pengguna yang sudah
                        terdaftar di perusahaan lain ke perusahaan ini.
                    </DialogDescription>
                </DialogHeader>

                <div className="px-6 pt-4">
                    <Tabs
                        value={mode}
                        onValueChange={(value) => setMode(value as 'new' | 'existing')}
                    >
                        <TabsList className="grid w-full grid-cols-2">
                            <TabsTrigger value="new">Pengguna Baru</TabsTrigger>
                            <TabsTrigger value="existing">
                                Pengguna Terdaftar
                            </TabsTrigger>
                        </TabsList>
                    </Tabs>
                </div>

                {mode === 'new' ? (
                    <form
                        onSubmit={handleCreateSubmit}
                        className="flex min-h-0 flex-1 flex-col"
                    >
                        <div className="grid min-h-0 gap-5 overflow-y-auto px-6 py-5">
                            <div className="rounded-lg border bg-muted/20 p-4">
                                <AvatarUploadField
                                    name={createForm.data.name}
                                    currentUrl={null}
                                    onChange={({ file }) =>
                                        createForm.setData('avatar', file)
                                    }
                                />
                                {createForm.errors.avatar && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {createForm.errors.avatar}
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Nama Lengkap</Label>
                                    <Input
                                        id="name"
                                        placeholder="Masukkan nama lengkap"
                                        value={createForm.data.name}
                                        onChange={(e) =>
                                            createForm.setData(
                                                'name',
                                                e.target.value,
                                            )
                                        }
                                        autoComplete="name"
                                    />
                                    {createForm.errors.name && (
                                        <p className="text-sm text-destructive">
                                            {createForm.errors.name}
                                        </p>
                                    )}
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="add-username">
                                        Username
                                    </Label>
                                    <Input
                                        id="add-username"
                                        placeholder="contoh: john_doe"
                                        value={createForm.data.username}
                                        onChange={(e) =>
                                            createForm.setData(
                                                'username',
                                                e.target.value,
                                            )
                                        }
                                        autoComplete="username"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Digunakan untuk login. Hanya huruf,
                                        angka, underscore, dan strip.
                                    </p>
                                    {createForm.errors.username && (
                                        <p className="text-sm text-destructive">
                                            {createForm.errors.username}
                                        </p>
                                    )}
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="add-email">
                                        Alamat Email
                                    </Label>
                                    <Input
                                        id="add-email"
                                        type="email"
                                        placeholder="Masukkan alamat email"
                                        value={createForm.data.email}
                                        onChange={(e) =>
                                            createForm.setData(
                                                'email',
                                                e.target.value,
                                            )
                                        }
                                        autoComplete="email"
                                    />
                                    {createForm.errors.email && (
                                        <p className="text-sm text-destructive">
                                            {createForm.errors.email}
                                        </p>
                                    )}
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="role">Peran</Label>
                                    <Select
                                        value={createForm.data.role_id.toString()}
                                        onValueChange={(value) =>
                                            createForm.setData(
                                                'role_id',
                                                parseInt(value),
                                            )
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
                                            ) : (
                                                availableRoles.map((role) => (
                                                    <SelectItem
                                                        key={role.id}
                                                        value={role.id.toString()}
                                                    >
                                                        {role.display_name}
                                                    </SelectItem>
                                                ))
                                            )}
                                        </SelectContent>
                                    </Select>
                                    {createForm.errors.role_id && (
                                        <p className="text-sm text-destructive">
                                            {createForm.errors.role_id}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="password">
                                        Kata Sandi
                                    </Label>
                                    <Input
                                        id="password"
                                        type="password"
                                        placeholder="Masukkan kata sandi"
                                        value={createForm.data.password}
                                        onChange={(e) =>
                                            createForm.setData(
                                                'password',
                                                e.target.value,
                                            )
                                        }
                                        autoComplete="new-password"
                                    />
                                    {createForm.errors.password && (
                                        <p className="text-sm text-destructive">
                                            {createForm.errors.password}
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
                                        value={
                                            createForm.data
                                                .password_confirmation
                                        }
                                        onChange={(e) =>
                                            createForm.setData(
                                                'password_confirmation',
                                                e.target.value,
                                            )
                                        }
                                        autoComplete="new-password"
                                    />
                                    {createForm.errors
                                        .password_confirmation && (
                                        <p className="text-sm text-destructive">
                                            {
                                                createForm.errors
                                                    .password_confirmation
                                            }
                                        </p>
                                    )}
                                </div>
                            </div>
                        </div>

                        <DialogFooter className="border-t px-6 py-4">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => handleClose(false)}
                                disabled={createForm.processing}
                            >
                                Batal
                            </Button>
                            <Button type="submit" disabled={createForm.processing}>
                                {createForm.processing
                                    ? 'Membuat...'
                                    : 'Buat Pengguna'}
                            </Button>
                        </DialogFooter>
                    </form>
                ) : (
                    <form
                        onSubmit={handleInviteSubmit}
                        className="flex min-h-0 flex-1 flex-col"
                    >
                        <div className="grid min-h-0 gap-5 overflow-y-auto px-6 py-5">
                            <p className="text-sm text-muted-foreground">
                                Masukkan email pengguna yang sudah terdaftar
                                di Nexumi ERP (mis. anggota perusahaan lain).
                                Akun baru tidak akan dibuat — pengguna
                                tersebut hanya ditambahkan sebagai anggota
                                perusahaan ini.
                            </p>

                            <div className="grid gap-2">
                                <Label htmlFor="invite-email">
                                    Alamat Email
                                </Label>
                                <Input
                                    id="invite-email"
                                    type="email"
                                    placeholder="Masukkan alamat email terdaftar"
                                    value={inviteForm.data.email}
                                    onChange={(e) =>
                                        inviteForm.setData(
                                            'email',
                                            e.target.value,
                                        )
                                    }
                                    autoComplete="email"
                                />
                                {inviteForm.errors.email && (
                                    <p className="text-sm text-destructive">
                                        {inviteForm.errors.email}
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="invite-role">Peran</Label>
                                <Select
                                    value={inviteForm.data.role_id.toString()}
                                    onValueChange={(value) =>
                                        inviteForm.setData(
                                            'role_id',
                                            parseInt(value),
                                        )
                                    }
                                >
                                    <SelectTrigger id="invite-role">
                                        <SelectValue placeholder="Pilih peran" />
                                    </SelectTrigger>
                                    <SelectContent className="max-h-72">
                                        {availableRoles.length === 0 ? (
                                            <div className="px-2 py-3 text-sm text-muted-foreground">
                                                Belum ada peran tersedia.
                                            </div>
                                        ) : (
                                            availableRoles.map((role) => (
                                                <SelectItem
                                                    key={role.id}
                                                    value={role.id.toString()}
                                                >
                                                    {role.display_name}
                                                </SelectItem>
                                            ))
                                        )}
                                    </SelectContent>
                                </Select>
                                {inviteForm.errors.role_id && (
                                    <p className="text-sm text-destructive">
                                        {inviteForm.errors.role_id}
                                    </p>
                                )}
                            </div>
                        </div>

                        <DialogFooter className="border-t px-6 py-4">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => handleClose(false)}
                                disabled={inviteForm.processing}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                disabled={inviteForm.processing}
                            >
                                {inviteForm.processing
                                    ? 'Menambahkan...'
                                    : 'Tambahkan Pengguna'}
                            </Button>
                        </DialogFooter>
                    </form>
                )}
            </DialogContent>
        </Dialog>
    );
}

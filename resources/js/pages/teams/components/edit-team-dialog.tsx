import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
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
import { Textarea } from '@/components/ui/textarea';
import { Team } from '@/types/team';
import { User } from '@/types/user';
import { useForm, usePage } from '@inertiajs/react';
import { IconEdit } from '@tabler/icons-react';
import { FormEventHandler, ReactNode, useState } from 'react';

interface EditTeamDialogProps {
    team: Team;
    trigger?: ReactNode;
}

export default function EditTeamDialog({ team, trigger }: EditTeamDialogProps) {
    const [open, setOpen] = useState(false);
    const { allUsers } = usePage<{ allUsers: User[] }>().props;

    const projectManagers = allUsers.filter(
        (user) => user.role?.name === 'project_manager',
    );

    const { data, setData, put, processing, errors, reset } = useForm({
        name: team.name,
        description: team.description || '',
        color: team.color,
        project_manager_id: team.project_manager?.id?.toString() || '',
    });

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        put(`/teams/${team.id}`, {
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
                    <Button variant="outline" size="sm">
                        <IconEdit className="mr-2 h-4 w-4" />
                        Edit Tim
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Edit Tim</DialogTitle>
                    <DialogDescription>Perbarui informasi tim.</DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit}>
                    <div className="grid gap-4 py-4">
                        {/* Row 1 — Name (3/4) + Color (1/4) */}
                        <div className="grid gap-4 sm:grid-cols-4">
                            <div className="grid gap-2 sm:col-span-3">
                                <Label htmlFor="edit-team-name">
                                    Nama Tim <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="edit-team-name"
                                    placeholder="Mis. Tim Klinik"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    className={errors.name ? 'border-destructive' : ''}
                                />
                                {errors.name && (
                                    <p className="text-sm text-destructive">{errors.name}</p>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="edit-team-color">Warna</Label>
                                <div className="flex gap-2">
                                    <Input
                                        id="edit-team-color"
                                        type="color"
                                        value={data.color}
                                        onChange={(e) => setData('color', e.target.value)}
                                        className="h-10 w-10 cursor-pointer p-1"
                                        title="Pilih warna tim"
                                    />
                                    <Input
                                        type="text"
                                        value={data.color}
                                        onChange={(e) => setData('color', e.target.value)}
                                        placeholder="#3B82F6"
                                        className="flex-1 font-mono text-xs"
                                    />
                                </div>
                                {errors.color && (
                                    <p className="text-sm text-destructive">{errors.color}</p>
                                )}
                            </div>
                        </div>

                        {/* Row 2 — Description */}
                        <div className="grid gap-2">
                            <Label htmlFor="edit-team-description">Deskripsi</Label>
                            <Textarea
                                id="edit-team-description"
                                placeholder="Tugas atau scope tim ini"
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                className={errors.description ? 'border-destructive' : ''}
                                rows={3}
                            />
                            {errors.description && (
                                <p className="text-sm text-destructive">{errors.description}</p>
                            )}
                        </div>

                        {/* Row 3 — Project Manager */}
                        <div className="grid gap-2">
                            <Label htmlFor="edit-project-manager">
                                Project Manager <span className="text-destructive">*</span>
                            </Label>
                            <Select
                                value={data.project_manager_id}
                                onValueChange={(value) => setData('project_manager_id', value)}
                            >
                                <SelectTrigger
                                    className={
                                        errors.project_manager_id ? 'border-destructive' : ''
                                    }
                                >
                                    <SelectValue placeholder="Pilih project manager" />
                                </SelectTrigger>
                                <SelectContent>
                                    {projectManagers.length > 0 ? (
                                        projectManagers.map((user) => (
                                            <SelectItem key={user.id} value={user.id.toString()}>
                                                {user.name}
                                                {user.role?.display_name
                                                    ? ` (${user.role.display_name})`
                                                    : ''}
                                            </SelectItem>
                                        ))
                                    ) : (
                                        <div className="p-2 text-sm text-muted-foreground">
                                            Belum ada user dengan role Project Manager. Tambahkan dulu di Master &gt; Pengguna.
                                        </div>
                                    )}
                                </SelectContent>
                            </Select>
                            {errors.project_manager_id && (
                                <p className="text-sm text-destructive">{errors.project_manager_id}</p>
                            )}
                        </div>
                    </div>

                    <div className="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                reset();
                                setOpen(false);
                            }}
                            disabled={processing}
                        >
                            Batal
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Menyimpan...' : 'Simpan Perubahan'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

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
import { User } from '@/types/user';
import { useForm, usePage } from '@inertiajs/react';
import { IconPlus } from '@tabler/icons-react';
import { FormEventHandler, useState } from 'react';

export default function CreateTeamDialog() {
    const [open, setOpen] = useState(false);
    const { allUsers } = usePage<{ allUsers: User[] }>().props;

    // Source of truth lives in RoleSeeder.php → only `project_manager` is valid.
    const projectManagers = allUsers.filter(
        (user) => user.role?.name === 'project_manager',
    );

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        description: '',
        color: '#3B82F6',
        project_manager_id: '',
    });

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/teams', {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <IconPlus className="mr-2 h-4 w-4" />
                    Buat Tim
                </Button>
            </DialogTrigger>
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Buat Tim Baru</DialogTitle>
                    <DialogDescription>
                        Susun tim baru beserta Project Manager yang akan memimpinnya.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit}>
                    <div className="grid gap-4 py-4">
                        {/* Row 1 — Name (3/4) + Color (1/4) sejajar biar layout pas */}
                        <div className="grid gap-4 sm:grid-cols-4">
                            <div className="grid gap-2 sm:col-span-3">
                                <Label htmlFor="team-name">
                                    Nama Tim <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="team-name"
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
                                <Label htmlFor="team-color">Warna</Label>
                                <div className="flex gap-2">
                                    <Input
                                        id="team-color"
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

                        {/* Row 2 — Description (full) */}
                        <div className="grid gap-2">
                            <Label htmlFor="team-description">Deskripsi</Label>
                            <Textarea
                                id="team-description"
                                placeholder="Tugas atau scope tim ini, mis. development & maintenance SIMKLINIK"
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                className={errors.description ? 'border-destructive' : ''}
                                rows={3}
                            />
                            {errors.description && (
                                <p className="text-sm text-destructive">{errors.description}</p>
                            )}
                        </div>

                        {/* Row 3 — Project Manager (full) */}
                        <div className="grid gap-2">
                            <Label htmlFor="project-manager">
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
                            onClick={() => setOpen(false)}
                            disabled={processing}
                        >
                            Batal
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Menyimpan...' : 'Buat Tim'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

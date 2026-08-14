import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
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
import { Textarea } from '@/components/ui/textarea';
import { Team } from '@/types/project';
import { User } from '@/types/user';
import { useForm, usePage } from '@inertiajs/react';
import { IconFile } from '@tabler/icons-react';
import { FormEventHandler, useState } from 'react';

interface CreateProjectDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    teams: Team[];
}

export function CreateProjectDialog({
    open,
    onOpenChange,
    teams,
}: CreateProjectDialogProps) {
    const { allUsers, clients } = usePage<{ allUsers?: User[]; clients?: { id: number; kode: string; nama: string }[] }>().props;

    const [selectedFile, setSelectedFile] = useState<File | null>(null);
    const [selectedImage, setSelectedImage] = useState<File | null>(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        description: '',
        team_id: '',
        client_id: '',
        kode_project: '',
        no_kontrak: '',
        tgl_mulai_kontrak: '',
        tgl_selesai_kontrak: '',
        tgl_implementasi: '',
        tgl_selesai_implementasi: '',
        jenis_pekerjaan: '',
        marketing_internal: '',
        project_manager_id: '',
        status: 'planning',
        start_date: '',
        end_date: '',
        file: null as File | null,
        image: null as File | null,
    });

    // Manajer proyek boleh siapa saja (tidak harus role project_manager).
    const projectManagers = allUsers ?? [];

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/projects', {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                reset();
                setSelectedFile(null);
                setSelectedImage(null);
                onOpenChange(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Buat Proyek Baru</DialogTitle>
                    <DialogDescription>
                        Siapkan proyek baru dengan tim dan timeline
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit}>
                    <div className="grid gap-4 py-4">
                        <div className="grid gap-2">
                            <Label htmlFor="project-name">
                                Nama Proyek{' '}
                                <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="project-name"
                                placeholder="contoh: Platform E-commerce"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                className={
                                    errors.name ? 'border-destructive' : ''
                                }
                            />
                            {errors.name && (
                                <p className="text-sm text-destructive">
                                    {errors.name}
                                </p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="project-description">
                                Deskripsi
                            </Label>
                            <Textarea
                                id="project-description"
                                placeholder="Deskripsikan tujuan dan lingkup proyek..."
                                value={data.description}
                                onChange={(e) =>
                                    setData('description', e.target.value)
                                }
                                className={
                                    errors.description
                                        ? 'border-destructive'
                                        : ''
                                }
                                rows={3}
                            />
                            {errors.description && (
                                <p className="text-sm text-destructive">
                                    {errors.description}
                                </p>
                            )}
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="client">Klient</Label>
                                <Select
                                    value={data.client_id}
                                    onValueChange={(value) => setData('client_id', value)}
                                >
                                    <SelectTrigger id="client">
                                        <SelectValue placeholder="Pilih client" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {(clients ?? []).map((c) => (
                                            <SelectItem key={c.id} value={c.id.toString()}>
                                                {c.kode} - {c.nama}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="kode_project">Kode Project</Label>
                                <Input
                                    id="kode_project"
                                    value={data.kode_project}
                                    onChange={(e) => setData('kode_project', e.target.value)}
                                />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="no_kontrak">No Kontrak</Label>
                            <Input
                                id="no_kontrak"
                                value={data.no_kontrak}
                                onChange={(e) => setData('no_kontrak', e.target.value)}
                            />
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="tgl_mulai_kontrak">Tgl Mulai Kontrak</Label>
                                <Input
                                    id="tgl_mulai_kontrak"
                                    type="date"
                                    value={data.tgl_mulai_kontrak}
                                    onChange={(e) => setData('tgl_mulai_kontrak', e.target.value)}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="tgl_selesai_kontrak">Selesai Kontrak</Label>
                                <Input
                                    id="tgl_selesai_kontrak"
                                    type="date"
                                    value={data.tgl_selesai_kontrak}
                                    onChange={(e) => setData('tgl_selesai_kontrak', e.target.value)}
                                />
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="tgl_implementasi">Tgl Implementasi</Label>
                                <Input
                                    id="tgl_implementasi"
                                    type="date"
                                    value={data.tgl_implementasi}
                                    onChange={(e) => setData('tgl_implementasi', e.target.value)}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="tgl_selesai_implementasi">Selesai Implementasi</Label>
                                <Input
                                    id="tgl_selesai_implementasi"
                                    type="date"
                                    value={data.tgl_selesai_implementasi}
                                    onChange={(e) => setData('tgl_selesai_implementasi', e.target.value)}
                                />
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="jenis_pekerjaan">Jenis Pekerjaan</Label>
                                <Select
                                    value={data.jenis_pekerjaan}
                                    onValueChange={(value) => setData('jenis_pekerjaan', value)}
                                >
                                    <SelectTrigger id="jenis_pekerjaan">
                                        <SelectValue placeholder="Pilih jenis" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="implementasi">Implementasi / Create</SelectItem>
                                        <SelectItem value="maintenance">Maintenance</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="marketing_internal">Marketing Internal</Label>
                                <Input
                                    id="marketing_internal"
                                    value={data.marketing_internal}
                                    onChange={(e) => setData('marketing_internal', e.target.value)}
                                />
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="team">
                                    Tim{' '}
                                    <span className="text-destructive">*</span>
                                </Label>
                                <Select
                                    value={data.team_id}
                                    onValueChange={(value) =>
                                        setData('team_id', value)
                                    }
                                >
                                    <SelectTrigger
                                        id="team"
                                        className={
                                            errors.team_id
                                                ? 'border-destructive'
                                                : ''
                                        }
                                    >
                                        <SelectValue placeholder="Pilih tim" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {teams.map((team) => (
                                            <SelectItem
                                                key={team.id}
                                                value={team.id.toString()}
                                            >
                                                <div className="flex items-center gap-2">
                                                    <div
                                                        className="h-3 w-3 rounded-full"
                                                        style={{
                                                            backgroundColor:
                                                                team.color,
                                                        }}
                                                    />
                                                    {team.name}
                                                </div>
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.team_id && (
                                    <p className="text-sm text-destructive">
                                        {errors.team_id}
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="project-manager">
                                    Manajer Proyek{' '}
                                    <span className="text-destructive">*</span>
                                </Label>
                                <Select
                                    value={data.project_manager_id}
                                    onValueChange={(value) =>
                                        setData('project_manager_id', value)
                                    }
                                >
                                    <SelectTrigger
                                        id="project-manager"
                                        className={
                                            errors.project_manager_id
                                                ? 'border-destructive'
                                                : ''
                                        }
                                    >
                                        <SelectValue placeholder="Pilih manajer" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {projectManagers.length > 0 ? (
                                            projectManagers.map((user) => (
                                                <SelectItem
                                                    key={user.id}
                                                    value={user.id.toString()}
                                                >
                                                    {user.name}
                                                </SelectItem>
                                            ))
                                        ) : (
                                            <div className="p-2 text-sm text-muted-foreground">
                                                Tidak ada manajer proyek tersedia
                                            </div>
                                        )}
                                    </SelectContent>
                                </Select>
                                {errors.project_manager_id && (
                                    <p className="text-sm text-destructive">
                                        {errors.project_manager_id}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="status">Status</Label>
                            <Select
                                value={data.status}
                                onValueChange={(value) =>
                                    setData('status', value)
                                }
                            >
                                <SelectTrigger id="status">
                                    <SelectValue placeholder="Pilih status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="planning">
                                        Perencanaan
                                    </SelectItem>
                                    <SelectItem value="in_progress">
                                        Sedang Berjalan
                                    </SelectItem>
                                    <SelectItem value="on_hold">
                                        Ditunda
                                    </SelectItem>
                                    <SelectItem value="completed">
                                        Selesai
                                    </SelectItem>
                                    <SelectItem value="cancelled">
                                        Dibatalkan
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="start-date">Tanggal Mulai</Label>
                                <Input
                                    id="start-date"
                                    type="date"
                                    value={data.start_date}
                                    onChange={(e) =>
                                        setData('start_date', e.target.value)
                                    }
                                    className={
                                        errors.start_date
                                            ? 'border-destructive'
                                            : ''
                                    }
                                />
                                {errors.start_date && (
                                    <p className="text-sm text-destructive">
                                        {errors.start_date}
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="end-date">
                                    Tanggal Target Selesai
                                </Label>
                                <Input
                                    id="end-date"
                                    type="date"
                                    value={data.end_date}
                                    onChange={(e) =>
                                        setData('end_date', e.target.value)
                                    }
                                    className={
                                        errors.end_date
                                            ? 'border-destructive'
                                            : ''
                                    }
                                />
                                {errors.end_date && (
                                    <p className="text-sm text-destructive">
                                        {errors.end_date}
                                    </p>
                                )}
                            </div>
                        </div>

                        {/* File Upload */}
                        <div className="grid gap-2">
                            <Label htmlFor="file">
                                Dokumen (Opsional)
                            </Label>
                            <div className="flex items-center gap-2">
                                <Input
                                    id="file"
                                    type="file"
                                    accept=".doc,.docx,.pdf"
                                    onChange={(e) => {
                                        const file = e.target.files?.[0];
                                        if (file) {
                                            setSelectedFile(file);
                                            setData('file', file);
                                        }
                                    }}
                                    className={
                                        errors.file ? 'border-destructive' : ''
                                    }
                                />
                            </div>
                            {selectedFile && (
                                <div className="flex items-center gap-2 rounded-lg border p-2 text-sm">
                                    <IconFile className="h-4 w-4" />
                                    <span className="flex-1 truncate">
                                        {selectedFile.name}
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {(selectedFile.size / 1024).toFixed(1)}{' '}
                                        KB
                                    </span>
                                </div>
                            )}
                            {errors.file && (
                                <p className="text-sm text-destructive">
                                    {errors.file}
                                </p>
                            )}
                            <p className="text-xs text-muted-foreground">
                                Format: .doc, .docx, .pdf (maks 20MB)
                            </p>
                        </div>

                        {/* Image Upload */}
                        <div className="grid gap-2">
                            <Label htmlFor="image">
                                Gambar Proyek (Opsional)
                            </Label>
                            <div className="flex items-center gap-2">
                                <Input
                                    id="image"
                                    type="file"
                                    accept=".jpg,.jpeg,.png"
                                    onChange={(e) => {
                                        const file = e.target.files?.[0];
                                        if (file) {
                                            setSelectedImage(file);
                                            setData('image', file);
                                        }
                                    }}
                                    className={
                                        errors.image ? 'border-destructive' : ''
                                    }
                                />
                            </div>
                            {selectedImage && (
                                <div className="rounded-lg border p-2">
                                    <img
                                        src={URL.createObjectURL(selectedImage)}
                                        alt="Preview"
                                        className="h-32 w-full rounded object-cover"
                                    />
                                    <p className="mt-2 text-sm">
                                        {selectedImage.name} -{' '}
                                        {(selectedImage.size / 1024).toFixed(1)}{' '}
                                        KB
                                    </p>
                                </div>
                            )}
                            {errors.image && (
                                <p className="text-sm text-destructive">
                                    {errors.image}
                                </p>
                            )}
                            <p className="text-xs text-muted-foreground">
                                Format: .jpg, .jpeg, .png (maks 10MB)
                            </p>
                        </div>
                    </div>

                    <div className="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                reset();
                                onOpenChange(false);
                            }}
                            disabled={processing}
                        >
                            Batal
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Membuat...' : 'Buat Proyek'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

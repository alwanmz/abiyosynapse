import { ConfirmDialog } from '@/components/confirm-dialog';
import { ListHeader } from '@/components/list-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { IconPencil, IconPlus, IconTrash } from '@tabler/icons-react';
import { useState } from 'react';

interface TaskType {
    id: number;
    nama: string;
}

interface Props {
    taskTypes: TaskType[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Master', href: '#' },
    { title: 'Task Types', href: '/master/task-types' },
];

export default function TaskTypesPage({ taskTypes }: Props) {
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<TaskType | null>(null);
    const [deleting, setDeleting] = useState<TaskType | null>(null);
    const { data, setData, post, put, processing, reset, errors, clearErrors } = useForm({ nama: '' });

    const startCreate = () => {
        setEditing(null);
        clearErrors();
        reset();
        setOpen(true);
    };

    const startEdit = (t: TaskType) => {
        setEditing(t);
        clearErrors();
        setData('nama', t.nama);
        setOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editing) {
            put(`/master/task-types/${editing.id}`, { preserveScroll: true, onSuccess: () => setOpen(false) });
        } else {
            post('/master/task-types', { preserveScroll: true, onSuccess: () => setOpen(false) });
        }
    };

    const handleDelete = () => {
        if (!deleting) return;
        router.delete(`/master/task-types/${deleting.id}`, {
            preserveScroll: true,
            onFinish: () => setDeleting(null),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Master Tipe Tugas" />

            <div className="flex flex-col gap-6 p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Master Tipe Tugas</h1>
                    <p className="text-muted-foreground">Kelola daftar jenis task / pekerjaan.</p>
                </div>

                <Card className="overflow-hidden p-0">
                    <ListHeader
                        title="Daftar Task Type"
                        description={`${taskTypes.length} jenis task tersimpan`}
                        action={
                            <Button size="sm" variant="secondary" onClick={startCreate}>
                                <IconPlus className="mr-1 h-4 w-4" /> Tambah
                            </Button>
                        }
                    />
                    <CardContent className="p-0">
                        <div className="overflow-hidden">
                            <table className="w-full text-sm">
                                <thead
                                    className="text-white shadow-[0_4px_16px_-8px_rgba(59,130,246,0.55)]"
                                    style={{
                                        backgroundImage:
                                            'linear-gradient(110deg, #1e3a8a 0%, #2563eb 45%, #0ea5e9 100%)',
                                    }}
                                >
                                    <tr>
                                        <th className="w-16 px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider">No.</th>
                                        <th className="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider">Nama Task Type</th>
                                        <th className="w-32 px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wider">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {taskTypes.length === 0 ? (
                                        <tr>
                                            <td colSpan={3} className="px-4 py-12 text-center text-muted-foreground">
                                                Belum ada task type.
                                            </td>
                                        </tr>
                                    ) : (
                                        taskTypes.map((t, idx) => (
                                            <tr key={t.id} className="transition-colors hover:bg-muted/40">
                                                <td className="px-4 py-3 font-mono text-muted-foreground">{idx + 1}</td>
                                                <td className="px-4 py-3 font-medium">{t.nama}</td>
                                                <td className="px-4 py-3">
                                                    <div className="flex justify-end gap-1">
                                                        <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => startEdit(t)}>
                                                            <IconPencil size={16} />
                                                        </Button>
                                                        <Button variant="ghost" size="icon" className="h-8 w-8 text-destructive" onClick={() => setDeleting(t)}>
                                                            <IconTrash size={16} />
                                                        </Button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>{editing ? 'Edit Tipe Tugas' : 'Tambah Tipe Tugas'}</DialogTitle>
                        <DialogDescription>Masukkan nama jenis task / pekerjaan.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="nama">Nama Task</Label>
                            <Input id="nama" value={data.nama} onChange={(e) => setData('nama', e.target.value)} required autoFocus />
                            {errors.nama && <p className="text-sm text-destructive">{errors.nama}</p>}
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setOpen(false)} disabled={processing}>
                                Batal
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing ? 'Menyimpan...' : 'Simpan'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={!!deleting}
                onOpenChange={(o) => !o && setDeleting(null)}
                title="Hapus task type?"
                description={
                    <>
                        Task type <strong className="text-foreground">"{deleting?.nama}"</strong> akan dihapus permanen. Aksi ini tidak bisa dibatalkan.
                    </>
                }
                confirmLabel="Ya, Hapus"
                onConfirm={handleDelete}
            />
        </AppLayout>
    );
}

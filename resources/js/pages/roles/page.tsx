import { ConfirmDialog } from '@/components/confirm-dialog';
import { ListHeader } from '@/components/list-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { ShieldCheck, Trash2 } from 'lucide-react';
import { FormEventHandler, useMemo, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Hak Akses',
        href: '/roles',
    },
];

interface Permission {
    id: number;
    name: string;
    display_name: string;
    description: string | null;
}

interface Role {
    id: number;
    name: string;
    display_name: string;
    description: string | null;
    permissions: Pick<Permission, 'id' | 'name'>[];
}

/**
 * Human-readable label for each module slug used as a permission prefix.
 * Permissions whose slug doesn't match any prefix below fall back to
 * the "Lainnya" group so we never silently drop one.
 */
const MODULE_LABELS: Record<string, string> = {
    companies: 'Perusahaan',
    clients: 'Klien',
    'task-types': 'Jenis Tugas',
    users: 'Pengguna',
    roles: 'Hak Akses',
    teams: 'Tim',
    projects: 'Proyek',
    timelines: 'Linimasa',
    tickets: 'Tiket',
    'daily-logs': 'Catatan Harian',
    minutes: 'Notulensi',
    analytics: 'Analitik',
    reports: 'Laporan',
};

/** Visual order — must include every key in MODULE_LABELS. */
const MODULE_ORDER = [
    'companies',
    'clients',
    'task-types',
    'users',
    'roles',
    'teams',
    'projects',
    'timelines',
    'tickets',
    'daily-logs',
    'minutes',
    'analytics',
    'reports',
] as const;

/**
 * Group permissions by their module prefix, hiding legacy flat slugs
 * (e.g. `manage-clients`) so admins only see the dotted catalog. Legacy
 * aliases are still seeded server-side for backwards-compat with
 * existing route middleware — they get synced automatically when an
 * admin saves a role.
 */
function groupPermissions(permissions: Permission[]) {
    const grouped: Record<string, Permission[]> = {};
    const others: Permission[] = [];

    for (const p of permissions) {
        // Only show the modern dotted slugs in the picker; legacy slugs
        // contain a hyphen but no dot.
        if (!p.name.includes('.')) continue;
        const [module] = p.name.split('.');
        if (MODULE_LABELS[module]) {
            (grouped[module] ||= []).push(p);
        } else {
            others.push(p);
        }
    }

    const ordered: { slug: string; label: string; permissions: Permission[] }[] = MODULE_ORDER
        .filter((m) => grouped[m]?.length)
        .map((m) => ({
            slug: m,
            label: MODULE_LABELS[m],
            permissions: grouped[m].sort((a, b) =>
                a.display_name.localeCompare(b.display_name),
            ),
        }));

    if (others.length) {
        ordered.push({ slug: 'other', label: 'Lainnya', permissions: others });
    }

    return ordered;
}

export default function RolesPage({
    roles,
    permissions,
}: {
    roles: Role[];
    permissions: Permission[];
}) {
    const [openDialog, setOpenDialog] = useState(false);
    const [editingRole, setEditingRole] = useState<Role | null>(null);
    const [deletingRole, setDeletingRole] = useState<Role | null>(null);

    const groups = useMemo(() => groupPermissions(permissions), [permissions]);
    const allModernPermissions = useMemo(
        () => permissions.filter((p) => p.name.includes('.')),
        [permissions],
    );

    const { data, setData, post, put, processing, errors, reset } = useForm({
        name: '',
        display_name: '',
        description: '',
        // Stores only modern permission IDs. The backend adds legacy aliases
        // automatically so older policies/routes stay compatible.
        permissions: [] as number[],
    });

    const handleOpenDialog = (role?: Role) => {
        if (role) {
            setEditingRole(role);
            const modernPermissionIds = new Set(allModernPermissions.map((p) => p.id));
            setData({
                name: role.name,
                display_name: role.display_name,
                description: role.description || '',
                permissions: role.permissions
                    .filter((p) => modernPermissionIds.has(p.id))
                    .map((p) => p.id),
            });
        } else {
            setEditingRole(null);
            reset();
        }
        setOpenDialog(true);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (editingRole) {
            put(`/roles/${editingRole.id}`, {
                preserveScroll: true,
                preserveState: false,
                onSuccess: () => {
                    setOpenDialog(false);
                    setEditingRole(null);
                    reset();
                },
            });
        } else {
            post('/roles', {
                preserveScroll: true,
                preserveState: false,
                onSuccess: () => {
                    setOpenDialog(false);
                    reset();
                },
            });
        }
    };

    const handleDelete = () => {
        if (!deletingRole) return;
        router.delete(`/roles/${deletingRole.id}`, {
            onSuccess: () => setDeletingRole(null),
            onError: () => setDeletingRole(null),
        });
    };

    const togglePermission = (permissionId: number) => {
        const has = data.permissions.includes(permissionId);
        setData(
            'permissions',
            has
                ? data.permissions.filter((id) => id !== permissionId)
                : [...data.permissions, permissionId],
        );
    };

    const toggleGroup = (group: { permissions: Permission[] }, on: boolean) => {
        const ids = group.permissions.map((p) => p.id);
        if (on) {
            const next = new Set([...data.permissions, ...ids]);
            setData('permissions', Array.from(next));
        } else {
            setData(
                'permissions',
                data.permissions.filter((id) => !ids.includes(id)),
            );
        }
    };

    const isLockedRole = (slug: string) =>
        slug === 'super_admin' || slug === 'admin';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Hak Akses" />

            <div className="flex h-full flex-col p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Hak Akses
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Kelola role dan izin akses sesuai modul yang aktif saat ini.
                        </p>
                    </div>

                    <Dialog open={openDialog} onOpenChange={setOpenDialog}>
                        <DialogTrigger asChild>
                            <Button onClick={() => handleOpenDialog()}>
                                <ShieldCheck className="mr-2 h-4 w-4" />
                                Tambah Role
                            </Button>
                        </DialogTrigger>
                        <DialogContent className="flex max-h-[90vh] max-w-3xl flex-col overflow-hidden">
                            <DialogHeader>
                                <DialogTitle>
                                    {editingRole ? 'Edit Role' : 'Tambah Role Baru'}
                                </DialogTitle>
                                <DialogDescription>
                                    {editingRole
                                        ? 'Perbarui detail role dan izinnya.'
                                        : 'Buat role baru lalu tentukan modul mana yang boleh diakses.'}
                                </DialogDescription>
                            </DialogHeader>

                            <form
                                id="role-form"
                                onSubmit={submit}
                                className="flex-1 space-y-6 overflow-y-auto pr-2"
                            >
                                <div className="space-y-4">
                                    {!editingRole && (
                                        <div className="space-y-2">
                                            <Label htmlFor="name">
                                                Nama Sistem (unik, tanpa spasi)
                                            </Label>
                                            <Input
                                                id="name"
                                                placeholder="mis. support_staff"
                                                value={data.name}
                                                onChange={(e) =>
                                                    setData('name', e.target.value)
                                                }
                                            />
                                            {errors.name && (
                                                <p className="text-sm text-red-500">
                                                    {errors.name}
                                                </p>
                                            )}
                                        </div>
                                    )}

                                    <div className="space-y-2">
                                        <Label htmlFor="display_name">Nama Tampilan</Label>
                                        <Input
                                            id="display_name"
                                            placeholder="mis. Support Staff"
                                            value={data.display_name}
                                            onChange={(e) =>
                                                setData('display_name', e.target.value)
                                            }
                                        />
                                        {errors.display_name && (
                                            <p className="text-sm text-red-500">
                                                {errors.display_name}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="description">
                                            Deskripsi (opsional)
                                        </Label>
                                        <Textarea
                                            id="description"
                                            placeholder="Deskripsi singkat fungsi role ini..."
                                            value={data.description}
                                            onChange={(e) =>
                                                setData('description', e.target.value)
                                            }
                                        />
                                        {errors.description && (
                                            <p className="text-sm text-red-500">
                                                {errors.description}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                <div className="space-y-4">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <Label className="text-base">
                                                Izin Akses
                                            </Label>
                                            <p className="text-xs text-muted-foreground">
                                                Centang izin yang ingin diberikan ke role ini.
                                            </p>
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {data.permissions.filter((id) =>
                                                allModernPermissions.some(
                                                    (p) => p.id === id,
                                                ),
                                            ).length}{' '}
                                            / {allModernPermissions.length} dipilih
                                        </div>
                                    </div>

                                    {groups.map((group) => {
                                        const ids = group.permissions.map((p) => p.id);
                                        const checkedCount = ids.filter((id) =>
                                            data.permissions.includes(id),
                                        ).length;
                                        const allChecked = checkedCount === ids.length;

                                        return (
                                            <div
                                                key={group.slug}
                                                className="rounded-lg border bg-card"
                                            >
                                                <div className="flex items-center justify-between border-b bg-muted/30 px-4 py-2">
                                                    <div className="flex items-center gap-2">
                                                        <Checkbox
                                                            checked={
                                                                allChecked
                                                                    ? true
                                                                    : checkedCount > 0
                                                                      ? 'indeterminate'
                                                                      : false
                                                            }
                                                            onCheckedChange={(v) =>
                                                                toggleGroup(group, !!v)
                                                            }
                                                        />
                                                        <span className="text-sm font-semibold">
                                                            {group.label}
                                                        </span>
                                                        <Badge
                                                            variant="outline"
                                                            className="text-[10px]"
                                                        >
                                                            {checkedCount}/{ids.length}
                                                        </Badge>
                                                    </div>
                                                </div>
                                                <div className="grid grid-cols-1 gap-2 p-3 md:grid-cols-2">
                                                    {group.permissions.map(
                                                        (permission) => {
                                                            const checked =
                                                                data.permissions.includes(
                                                                    permission.id,
                                                                );
                                                            return (
                                                                <label
                                                                    key={permission.id}
                                                                    className={`flex cursor-pointer items-start gap-2 rounded-md border p-2.5 transition-colors ${
                                                                        checked
                                                                            ? 'border-primary/40 bg-primary/5'
                                                                            : 'hover:bg-muted/50'
                                                                    }`}
                                                                >
                                                                    <Checkbox
                                                                        id={`perm-${permission.id}`}
                                                                        checked={checked}
                                                                        onCheckedChange={() =>
                                                                            togglePermission(
                                                                                permission.id,
                                                                            )
                                                                        }
                                                                    />
                                                                    <div className="flex-1 space-y-0.5">
                                                                        <div className="text-sm font-medium leading-tight">
                                                                            {
                                                                                permission.display_name
                                                                            }
                                                                        </div>
                                                                        {permission.description && (
                                                                            <p className="text-xs leading-snug text-muted-foreground">
                                                                                {
                                                                                    permission.description
                                                                                }
                                                                            </p>
                                                                        )}
                                                                        <code className="text-[10px] text-muted-foreground/70">
                                                                            {permission.name}
                                                                        </code>
                                                                    </div>
                                                                </label>
                                                            );
                                                        },
                                                    )}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </form>

                            <DialogFooter className="mt-4 border-t pt-4">
                                <Button
                                    variant="outline"
                                    type="button"
                                    onClick={() => setOpenDialog(false)}
                                >
                                    Batal
                                </Button>
                                <Button
                                    type="submit"
                                    form="role-form"
                                    disabled={processing}
                                >
                                    Simpan Role
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </div>

                <div className="overflow-hidden rounded-xl border bg-card">
                    <ListHeader title="Daftar Role" />
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Peran</TableHead>
                                <TableHead>Pengidentifikasi Sistem</TableHead>
                                <TableHead>Izin</TableHead>
                                <TableHead className="w-[120px] text-right">
                                    Aksi
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {roles.map((role) => {
                                const modernPerms = role.permissions.filter((p) =>
                                    p.name.includes('.'),
                                );
                                const locked = isLockedRole(role.name);
                                return (
                                    <TableRow key={role.id}>
                                        <TableCell>
                                            <div className="font-medium">
                                                {role.display_name}
                                            </div>
                                            {role.description && (
                                                <div className="mt-1 line-clamp-1 text-xs text-muted-foreground">
                                                    {role.description}
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="secondary"
                                                className="font-mono font-normal"
                                            >
                                                {role.name}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap gap-1">
                                                {locked ? (
                                                    <Badge className="bg-indigo-500/10 text-indigo-500 hover:bg-indigo-500/20">
                                                        Semua Izin (Admin)
                                                    </Badge>
                                                ) : modernPerms.length === 0 ? (
                                                    <span className="text-xs italic text-muted-foreground">
                                                        Tidak ada izin
                                                    </span>
                                                ) : (
                                                    <>
                                                        {modernPerms
                                                            .slice(0, 4)
                                                            .map((p) => (
                                                                <Badge
                                                                    key={p.id}
                                                                    variant="outline"
                                                                    className="bg-muted/50 text-xs"
                                                                >
                                                                    {p.name}
                                                                </Badge>
                                                            ))}
                                                        {modernPerms.length > 4 && (
                                                            <Badge
                                                                variant="secondary"
                                                                className="text-xs"
                                                            >
                                                                +
                                                                {modernPerms.length - 4} lainnya
                                                            </Badge>
                                                        )}
                                                    </>
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => handleOpenDialog(role)}
                                                >
                                                    Kelola
                                                </Button>
                                                {!locked && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                        onClick={() =>
                                                            setDeletingRole(role)
                                                        }
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                            {roles.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={4}
                                        className="h-24 text-center"
                                    >
                                        Belum ada role.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>

            <ConfirmDialog
                open={!!deletingRole}
                onOpenChange={(o) => !o && setDeletingRole(null)}
                title="Hapus role?"
                description={
                    <>
                        Role{' '}
                        <strong className="text-foreground">
                            "{deletingRole?.display_name}"
                        </strong>{' '}
                        akan dihapus permanen. Role hanya bisa dihapus jika belum
                        digunakan oleh user manapun.
                    </>
                }
                confirmLabel="Ya, Hapus Role"
                onConfirm={handleDelete}
            />
        </AppLayout>
    );
}

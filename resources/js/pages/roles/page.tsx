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
import { useBreadcrumbs } from '@/hooks/use-breadcrumbs';
import { Head, router, useForm } from '@inertiajs/react';
import { ShieldCheck, Trash2 } from 'lucide-react';
import { FormEventHandler, type ReactElement, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

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

/** Visual order — must include every key in the `roles:modules` namespace. */
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
function groupPermissions(
    permissions: Permission[],
    moduleLabels: Record<string, string>,
    otherLabel: string,
) {
    const grouped: Record<string, Permission[]> = {};
    const others: Permission[] = [];

    for (const p of permissions) {
        // Only show the modern dotted slugs in the picker; legacy slugs
        // contain a hyphen but no dot.
        if (!p.name.includes('.')) continue;
        const [module] = p.name.split('.');
        if (moduleLabels[module]) {
            (grouped[module] ||= []).push(p);
        } else {
            others.push(p);
        }
    }

    const ordered: { slug: string; label: string; permissions: Permission[] }[] = MODULE_ORDER
        .filter((m) => grouped[m]?.length)
        .map((m) => ({
            slug: m,
            label: moduleLabels[m],
            permissions: grouped[m].sort((a, b) =>
                a.display_name.localeCompare(b.display_name),
            ),
        }));

    if (others.length) {
        ordered.push({ slug: 'other', label: otherLabel, permissions: others });
    }

    return ordered;
}

function RolesPage({
    roles,
    permissions,
}: {
    roles: Role[];
    permissions: Permission[];
}) {
    const { t } = useTranslation('roles');

    useBreadcrumbs([
        {
            title: t('breadcrumb'),
            href: '/roles',
        },
    ]);

    const moduleLabels: Record<string, string> = MODULE_ORDER.reduce(
        (acc, slug) => ({ ...acc, [slug]: t(`modules.${slug}`) }),
        {},
    );

    const [openDialog, setOpenDialog] = useState(false);
    const [editingRole, setEditingRole] = useState<Role | null>(null);
    const [deletingRole, setDeletingRole] = useState<Role | null>(null);

    const groups = useMemo(
        () => groupPermissions(permissions, moduleLabels, t('modules.other')),
        [permissions, moduleLabels, t],
    );
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
        <>
            <Head title={t('head_title')} />

            <div className="flex h-full flex-col p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {t('page_title')}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {t('page_description')}
                        </p>
                    </div>

                    <Dialog open={openDialog} onOpenChange={setOpenDialog}>
                        <DialogTrigger asChild>
                            <Button onClick={() => handleOpenDialog()}>
                                <ShieldCheck className="mr-2 h-4 w-4" />
                                {t('add_role')}
                            </Button>
                        </DialogTrigger>
                        <DialogContent className="flex max-h-[90vh] max-w-3xl flex-col overflow-hidden">
                            <DialogHeader>
                                <DialogTitle>
                                    {editingRole ? t('dialog.edit_title') : t('dialog.create_title')}
                                </DialogTitle>
                                <DialogDescription>
                                    {editingRole
                                        ? t('dialog.edit_description')
                                        : t('dialog.create_description')}
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
                                                {t('dialog.system_name')}
                                            </Label>
                                            <Input
                                                id="name"
                                                placeholder={t('dialog.system_name_placeholder')}
                                                value={data.name}
                                                onChange={(e) =>
                                                    setData('name', e.target.value)
                                                }
                                            />
                                            {errors.name && (
                                                <p className="text-sm text-destructive">
                                                    {errors.name}
                                                </p>
                                            )}
                                        </div>
                                    )}

                                    <div className="space-y-2">
                                        <Label htmlFor="display_name">{t('dialog.display_name')}</Label>
                                        <Input
                                            id="display_name"
                                            placeholder={t('dialog.display_name_placeholder')}
                                            value={data.display_name}
                                            onChange={(e) =>
                                                setData('display_name', e.target.value)
                                            }
                                        />
                                        {errors.display_name && (
                                            <p className="text-sm text-destructive">
                                                {errors.display_name}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="description">
                                            {t('dialog.description')}
                                        </Label>
                                        <Textarea
                                            id="description"
                                            placeholder={t('dialog.description_placeholder')}
                                            value={data.description}
                                            onChange={(e) =>
                                                setData('description', e.target.value)
                                            }
                                        />
                                        {errors.description && (
                                            <p className="text-sm text-destructive">
                                                {errors.description}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                <div className="space-y-4">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <Label className="text-base">
                                                {t('dialog.permissions')}
                                            </Label>
                                            <p className="text-xs text-muted-foreground">
                                                {t('dialog.permissions_hint')}
                                            </p>
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {data.permissions.filter((id) =>
                                                allModernPermissions.some(
                                                    (p) => p.id === id,
                                                ),
                                            ).length}{' '}
                                            / {allModernPermissions.length} {t('dialog.permissions_selected')}
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
                                    {t('dialog.cancel')}
                                </Button>
                                <Button
                                    type="submit"
                                    form="role-form"
                                    disabled={processing}
                                >
                                    {t('dialog.submit')}
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </div>

                <div className="overflow-hidden rounded-xl border bg-card">
                    <ListHeader title={t('list_title')} />
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('table.role')}</TableHead>
                                <TableHead>{t('table.system_identifier')}</TableHead>
                                <TableHead>{t('table.permissions')}</TableHead>
                                <TableHead className="w-[120px] text-right">
                                    {t('table.actions')}
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
                                                    <Badge className="bg-nx-navy-50 text-nx-navy-700 hover:bg-nx-navy-100 dark:bg-nx-navy-50 dark:text-nx-navy-700">
                                                        {t('table.all_permissions_admin')}
                                                    </Badge>
                                                ) : modernPerms.length === 0 ? (
                                                    <span className="text-xs italic text-muted-foreground">
                                                        {t('table.no_permissions')}
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
                                                                {modernPerms.length - 4} {t('table.more_others')}
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
                                                    {t('table.manage')}
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
                                        {t('table.empty')}
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
                title={t('delete.title')}
                description={
                    <>
                        {t('delete.description_prefix')}{' '}
                        <strong className="text-foreground">
                            "{deletingRole?.display_name}"
                        </strong>{' '}
                        {t('delete.description_suffix')}
                    </>
                }
                confirmLabel={t('delete.confirm')}
                onConfirm={handleDelete}
            />
        </>
    );
}

RolesPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default RolesPage;

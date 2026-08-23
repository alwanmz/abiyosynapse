import { ConfirmDialog } from '@/components/confirm-dialog';
import { ListHeader } from '@/components/list-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Textarea } from '@/components/ui/textarea';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { useBreadcrumbs } from '@/hooks/use-breadcrumbs';
import { Head, useForm } from '@inertiajs/react';
import { IconPlus } from '@tabler/icons-react';
import { Pencil, Trash2, X } from 'lucide-react';
import { type ReactElement, useState } from 'react';
import { useTranslation } from 'react-i18next';

interface Operation {
    id: number;
    sequence: number;
    name: string;
    work_center_id: number;
    setup_minutes: string;
    run_minutes_per_unit: string;
    is_inspection_point: boolean;
    work_center: { id: number; code: string; name: string };
}

interface Routing {
    id: number;
    product_id: number;
    code: string;
    version: number;
    status: 'draft' | 'approved' | 'active' | 'obsolete';
    notes: string | null;
    product: { id: number; code: string; name: string };
    operations: Operation[];
}

interface Option {
    id: number;
    code: string;
    name: string;
}

interface PageProps {
    routings: Routing[];
    products: Option[];
    workCenters: Option[];
}

interface OperationForm {
    name: string;
    work_center_id: string | number;
    setup_minutes: string;
    run_minutes_per_unit: string;
    is_inspection_point: boolean;
}

const emptyOperation: OperationForm = {
    name: '',
    work_center_id: '',
    setup_minutes: '0',
    run_minutes_per_unit: '',
    is_inspection_point: false,
};

const STATUS_VARIANT: Record<Routing['status'], 'default' | 'outline' | 'secondary'> = {
    draft: 'outline',
    approved: 'secondary',
    active: 'default',
    obsolete: 'outline',
};

function RoutingsPage({ routings, products, workCenters }: PageProps) {
    const { t } = useTranslation('manufacturing');
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<Routing | null>(null);
    const [deleting, setDeleting] = useState<Routing | null>(null);

    useBreadcrumbs([
        { title: t('nav.manufacturing'), href: '#' },
        { title: t('routing.breadcrumb'), href: '/manufacturing/routings' },
    ]);

    const { data, setData, post, put, processing, errors, reset } = useForm<{
        product_id: string | number;
        code: string;
        status: Routing['status'];
        notes: string;
        operations: OperationForm[];
    }>({
        product_id: '',
        code: '',
        status: 'draft',
        notes: '',
        operations: [{ ...emptyOperation }],
    });

    const { delete: destroy, processing: deleting_ } = useForm({});

    const openCreate = () => {
        setEditing(null);
        reset();
        setData('operations', [{ ...emptyOperation }]);
        setDialogOpen(true);
    };

    const openEdit = (routing: Routing) => {
        setEditing(routing);
        setData({
            product_id: routing.product_id,
            code: routing.code,
            status: routing.status,
            notes: routing.notes ?? '',
            operations: routing.operations.map((op) => ({
                name: op.name,
                work_center_id: op.work_center_id,
                setup_minutes: op.setup_minutes,
                run_minutes_per_unit: op.run_minutes_per_unit,
                is_inspection_point: op.is_inspection_point,
            })),
        });
        setDialogOpen(true);
    };

    const addOperation = () => setData('operations', [...data.operations, { ...emptyOperation }]);
    const removeOperation = (index: number) => setData('operations', data.operations.filter((_, i) => i !== index));
    const updateOperation = (index: number, patch: Partial<OperationForm>) =>
        setData('operations', data.operations.map((op, i) => (i === index ? { ...op, ...patch } : op)));

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => {
                setDialogOpen(false);
                reset();
            },
        };

        if (editing) {
            put(`/manufacturing/routings/${editing.id}`, options);
        } else {
            post('/manufacturing/routings', options);
        }
    };

    const handleDelete = () => {
        if (!deleting) return;
        destroy(`/manufacturing/routings/${deleting.id}`, {
            preserveScroll: true,
            onSuccess: () => setDeleting(null),
            onError: () => setDeleting(null),
        });
    };

    return (
        <>
            <Head title={t('routing.title')} />

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">{t('routing.title')}</h1>
                        <p className="text-sm text-muted-foreground">{t('routing.description')}</p>
                    </div>
                    <Button onClick={openCreate}>
                        <IconPlus className="mr-2 h-4 w-4" />
                        {t('routing.add')}
                    </Button>
                </div>

                <Card className="overflow-hidden p-0">
                    <ListHeader title={t('routing.list_title')} />
                    <CardContent className="p-5">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('routing.table.product')}</TableHead>
                                    <TableHead>{t('routing.table.code')}</TableHead>
                                    <TableHead>{t('routing.table.version')}</TableHead>
                                    <TableHead>{t('routing.table.operations')}</TableHead>
                                    <TableHead>{t('routing.table.status')}</TableHead>
                                    <TableHead className="text-right">{t('routing.table.actions')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {routings.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={6} className="h-24 text-center text-muted-foreground">
                                            {t('routing.empty')}
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    routings.map((routing) => (
                                        <TableRow key={routing.id}>
                                            <TableCell>
                                                <div className="font-medium">{routing.product.name}</div>
                                                <div className="font-mono text-xs text-muted-foreground">{routing.product.code}</div>
                                            </TableCell>
                                            <TableCell className="font-mono text-sm">{routing.code}</TableCell>
                                            <TableCell className="tabular-nums">v{routing.version}</TableCell>
                                            <TableCell className="tabular-nums">{routing.operations.length}</TableCell>
                                            <TableCell>
                                                <Badge variant={STATUS_VARIANT[routing.status]}>
                                                    {t(`bom.status_${routing.status}`)}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex items-center justify-end gap-1">
                                                    <Button variant="ghost" size="icon" onClick={() => openEdit(routing)}>
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="text-destructive hover:text-destructive"
                                                        onClick={() => setDeleting(routing)}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="flex max-h-[90vh] max-w-3xl flex-col overflow-hidden">
                    <DialogHeader>
                        <DialogTitle>{editing ? t('routing.edit_title') : t('routing.add_title')}</DialogTitle>
                        <DialogDescription>{t('routing.description')}</DialogDescription>
                    </DialogHeader>

                    <form id="routing-form" onSubmit={handleSubmit} className="flex-1 space-y-4 overflow-y-auto pr-2">
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="product_id">{t('routing.product')}</Label>
                                <Select
                                    value={data.product_id.toString()}
                                    onValueChange={(value) => setData('product_id', value)}
                                    disabled={!!editing}
                                >
                                    <SelectTrigger id="product_id">
                                        <SelectValue placeholder={t('routing.product_placeholder')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {products.map((p) => (
                                            <SelectItem key={p.id} value={p.id.toString()}>
                                                {p.code} — {p.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.product_id && <p className="text-sm text-destructive">{errors.product_id}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="code">Kode</Label>
                                <Input
                                    id="code"
                                    placeholder={t('routing.code_placeholder')}
                                    value={data.code}
                                    onChange={(e) => setData('code', e.target.value)}
                                />
                                {errors.code && <p className="text-sm text-destructive">{errors.code}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="status">{t('routing.status')}</Label>
                                <Select value={data.status} onValueChange={(value) => setData('status', value as Routing['status'])}>
                                    <SelectTrigger id="status">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="draft">{t('bom.status_draft')}</SelectItem>
                                        <SelectItem value="approved">{t('bom.status_approved')}</SelectItem>
                                        <SelectItem value="active">{t('bom.status_active')}</SelectItem>
                                        <SelectItem value="obsolete">{t('bom.status_obsolete')}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="notes">{t('routing.notes')}</Label>
                            <Textarea id="notes" value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                        </div>

                        <div className="space-y-3">
                            <div className="flex items-center justify-between">
                                <Label className="text-base">{t('routing.operations')}</Label>
                                <Button type="button" variant="default" size="sm" onClick={addOperation}>
                                    <IconPlus className="mr-1 h-3.5 w-3.5" />
                                    {t('routing.add_operation')}
                                </Button>
                            </div>

                            {data.operations.map((op, index) => (
                                <div key={index} className="space-y-2 rounded-lg border p-3">
                                    <div className="grid grid-cols-12 items-start gap-2">
                                        <div className="col-span-4">
                                            <Input
                                                placeholder={t('routing.operation_name_placeholder')}
                                                value={op.name}
                                                onChange={(e) => updateOperation(index, { name: e.target.value })}
                                            />
                                        </div>
                                        <div className="col-span-4">
                                            <Select
                                                value={op.work_center_id.toString()}
                                                onValueChange={(value) => updateOperation(index, { work_center_id: value })}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue placeholder={t('routing.work_center_placeholder')} />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {workCenters.map((wc) => (
                                                        <SelectItem key={wc.id} value={wc.id.toString()}>
                                                            {wc.code} — {wc.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="col-span-1">
                                            <Input
                                                type="number"
                                                step="0.01"
                                                placeholder={t('routing.setup_minutes')}
                                                value={op.setup_minutes}
                                                onChange={(e) => updateOperation(index, { setup_minutes: e.target.value })}
                                            />
                                        </div>
                                        <div className="col-span-2">
                                            <Input
                                                type="number"
                                                step="0.0001"
                                                placeholder={t('routing.run_minutes')}
                                                value={op.run_minutes_per_unit}
                                                onChange={(e) => updateOperation(index, { run_minutes_per_unit: e.target.value })}
                                            />
                                        </div>
                                        <div className="col-span-1 flex justify-end">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => removeOperation(index)}
                                                disabled={data.operations.length === 1}
                                            >
                                                <X className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                    <label className="flex items-center gap-2 text-xs text-muted-foreground">
                                        <Checkbox
                                            checked={op.is_inspection_point}
                                            onCheckedChange={(checked) =>
                                                updateOperation(index, { is_inspection_point: checked === true })
                                            }
                                        />
                                        {t('routing.inspection_point')}
                                    </label>
                                </div>
                            ))}
                            {errors.operations && <p className="text-sm text-destructive">{errors.operations}</p>}
                        </div>
                    </form>

                    <DialogFooter className="mt-2 border-t pt-4">
                        <Button type="button" variant="cancel" onClick={() => setDialogOpen(false)} disabled={processing}>
                            Batal
                        </Button>
                        <Button type="submit" form="routing-form" variant="save" disabled={processing}>
                            {processing ? 'Menyimpan...' : 'Simpan'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={!!deleting}
                onOpenChange={(open) => !open && setDeleting(null)}
                title={t('routing.delete_title')}
                description={
                    <>
                        {t('routing.delete_confirm_prefix')}{' '}
                        <strong className="text-foreground">"{deleting?.code}"</strong>?
                    </>
                }
                confirmLabel="Hapus"
                loading={deleting_}
                onConfirm={handleDelete}
            />
        </>
    );
}

RoutingsPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default RoutingsPage;

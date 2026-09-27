import { ConfirmDialog } from '@/components/confirm-dialog';
import { PrintDocumentButton } from '@/components/print-document-button';
import { ListHeader } from '@/components/list-header';
import { Badge } from '@/components/ui/badge';
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
import { Head, router, useForm } from '@inertiajs/react';
import { IconPlus } from '@tabler/icons-react';
import { CopyPlus, Pencil, Trash2, X } from 'lucide-react';
import { type ReactElement, useState } from 'react';
import { useTranslation } from 'react-i18next';

interface BomLine {
    id: number;
    component_id: number;
    quantity_per_batch: string;
    uom_id: number;
    scrap_percentage: string;
    component: { id: number; code: string; name: string };
}

interface Bom {
    id: number;
    product_id: number;
    code: string;
    version: number;
    batch_quantity: string;
    status: 'draft' | 'approved' | 'active' | 'obsolete';
    notes: string | null;
    effective_from: string | null;
    effective_until: string | null;
    revision_reason: string | null;
    product: { id: number; code: string; name: string };
    lines: BomLine[];
}

interface Option {
    id: number;
    code: string;
    name: string;
}

interface PageProps {
    boms: Bom[];
    products: Option[];
    unitOfMeasures: Option[];
}

interface LineForm {
    component_id: string | number;
    quantity_per_batch: string;
    uom_id: string | number;
    scrap_percentage: string;
}

const emptyLine: LineForm = { component_id: '', quantity_per_batch: '', uom_id: '', scrap_percentage: '0' };

const STATUS_VARIANT: Record<Bom['status'], 'default' | 'outline' | 'secondary'> = {
    draft: 'outline',
    approved: 'secondary',
    active: 'default',
    obsolete: 'outline',
};

function BomsPage({ boms, products, unitOfMeasures }: PageProps) {
    const { t } = useTranslation('manufacturing');
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<Bom | null>(null);
    const [deleting, setDeleting] = useState<Bom | null>(null);

    useBreadcrumbs([
        { title: t('nav.manufacturing'), href: '#' },
        { title: t('bom.breadcrumb'), href: '/manufacturing/boms' },
    ]);

    const { data, setData, post, put, processing, errors, reset } = useForm<{
        product_id: string | number;
        code: string;
        batch_quantity: string;
        status: Bom['status'];
        effective_from: string;
        effective_until: string;
        revision_reason: string;
        notes: string;
        lines: LineForm[];
    }>({
        product_id: '',
        code: '',
        batch_quantity: '1',
        status: 'draft',
        effective_from: '',
        effective_until: '',
        revision_reason: '',
        notes: '',
        lines: [{ ...emptyLine }],
    });

    const { delete: destroy, processing: deleting_ } = useForm({});

    const openCreate = () => {
        setEditing(null);
        reset();
        setData('lines', [{ ...emptyLine }]);
        setDialogOpen(true);
    };

    const openEdit = (bom: Bom) => {
        setEditing(bom);
        setData({
            product_id: bom.product_id,
            code: bom.code,
            batch_quantity: bom.batch_quantity,
            status: bom.status,
            effective_from: bom.effective_from?.slice(0, 10) ?? '',
            effective_until: bom.effective_until?.slice(0, 10) ?? '',
            revision_reason: bom.revision_reason ?? '',
            notes: bom.notes ?? '',
            lines: bom.lines.map((l) => ({
                component_id: l.component_id,
                quantity_per_batch: l.quantity_per_batch,
                uom_id: l.uom_id,
                scrap_percentage: l.scrap_percentage,
            })),
        });
        setDialogOpen(true);
    };

    const addLine = () => setData('lines', [...data.lines, { ...emptyLine }]);
    const removeLine = (index: number) => setData('lines', data.lines.filter((_, i) => i !== index));
    const updateLine = (index: number, patch: Partial<LineForm>) =>
        setData('lines', data.lines.map((l, i) => (i === index ? { ...l, ...patch } : l)));

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
            put(`/manufacturing/boms/${editing.id}`, options);
        } else {
            post('/manufacturing/boms', options);
        }
    };

    const handleDelete = () => {
        if (!deleting) return;
        destroy(`/manufacturing/boms/${deleting.id}`, {
            preserveScroll: true,
            onSuccess: () => setDeleting(null),
            onError: () => setDeleting(null),
        });
    };

    const handleNewVersion = (bom: Bom) => {
        router.post(`/manufacturing/boms/${bom.id}/new-version`, {}, { preserveScroll: true });
    };

    return (
        <>
            <Head title={t('bom.title')} />

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">{t('bom.title')}</h1>
                        <p className="text-sm text-muted-foreground">{t('bom.description')}</p>
                    </div>
                    <Button onClick={openCreate}>
                        <IconPlus className="mr-2 h-4 w-4" />
                        {t('bom.add')}
                    </Button>
                </div>

                <Card className="overflow-hidden p-0">
                    <ListHeader title={t('bom.list_title')} />
                    <CardContent className="p-5">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('bom.table.product')}</TableHead>
                                    <TableHead>{t('bom.table.code')}</TableHead>
                                    <TableHead>{t('bom.table.version')}</TableHead>
                                    <TableHead>{t('bom.table.components')}</TableHead>
                                    <TableHead>{t('bom.table.status')}</TableHead>
                                    <TableHead className="text-right">{t('bom.table.actions')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {boms.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={6} className="h-24 text-center text-muted-foreground">
                                            {t('bom.empty')}
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    boms.map((bom) => (
                                        <TableRow key={bom.id}>
                                            <TableCell>
                                                <div className="font-medium">{bom.product.name}</div>
                                                <div className="font-mono text-xs text-muted-foreground">{bom.product.code}</div>
                                            </TableCell>
                                            <TableCell>
                                                <div className="font-mono text-sm">{bom.code}</div>
                                                {bom.revision_reason && (
                                                    <div className="mt-1 max-w-56 truncate text-xs text-muted-foreground" title={bom.revision_reason}>
                                                        {bom.revision_reason}
                                                    </div>
                                                )}
                                            </TableCell>
                                            <TableCell className="tabular-nums">v{bom.version}</TableCell>
                                            <TableCell className="tabular-nums">{bom.lines.length}</TableCell>
                                            <TableCell>
                                                <Badge variant={STATUS_VARIANT[bom.status]}>
                                                    {t(`bom.status_${bom.status}`)}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex items-center justify-end gap-1">
                                                    <PrintDocumentButton type="bom" documentId={bom.id} compact />
                                                    {bom.status === 'draft' ? (
                                                        <>
                                                            <Button variant="ghost" size="icon" onClick={() => openEdit(bom)} title={t('bom.edit_title')}>
                                                                <Pencil className="h-4 w-4" />
                                                            </Button>
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="text-destructive hover:text-destructive"
                                                                onClick={() => setDeleting(bom)}
                                                                title={t('bom.delete_title')}
                                                            >
                                                                <Trash2 className="h-4 w-4" />
                                                            </Button>
                                                        </>
                                                    ) : (
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() => handleNewVersion(bom)}
                                                            title={t('bom.new_version')}
                                                            aria-label={t('bom.new_version')}
                                                        >
                                                            <CopyPlus className="h-4 w-4" />
                                                        </Button>
                                                    )}
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
                <DialogContent className="flex max-h-[90vh] max-w-2xl flex-col overflow-hidden">
                    <DialogHeader>
                        <DialogTitle>{editing ? t('bom.edit_title') : t('bom.add_title')}</DialogTitle>
                        <DialogDescription>{t('bom.description')}</DialogDescription>
                    </DialogHeader>

                    <form id="bom-form" onSubmit={handleSubmit} className="flex-1 space-y-4 overflow-y-auto pr-2">
                            <div className="grid gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="product_id">{t('bom.product')}</Label>
                                <Select
                                    value={data.product_id.toString()}
                                    onValueChange={(value) => setData('product_id', value)}
                                    disabled={!!editing}
                                >
                                    <SelectTrigger id="product_id">
                                        <SelectValue placeholder={t('bom.product_placeholder')} />
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

                        <div className="grid gap-4 md:grid-cols-3">
                            <div className="grid gap-2">
                                <Label htmlFor="effective_from">{t('bom.effective_from')}</Label>
                                <Input
                                    id="effective_from"
                                    type="date"
                                    value={data.effective_from}
                                    onChange={(e) => setData('effective_from', e.target.value)}
                                />
                                {errors.effective_from && <p className="text-sm text-destructive">{errors.effective_from}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="effective_until">{t('bom.effective_until')}</Label>
                                <Input
                                    id="effective_until"
                                    type="date"
                                    value={data.effective_until}
                                    onChange={(e) => setData('effective_until', e.target.value)}
                                />
                                {errors.effective_until && <p className="text-sm text-destructive">{errors.effective_until}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="revision_reason">{t('bom.revision_reason')}</Label>
                                <Input
                                    id="revision_reason"
                                    placeholder={t('bom.revision_reason_placeholder')}
                                    value={data.revision_reason}
                                    onChange={(e) => setData('revision_reason', e.target.value)}
                                />
                                {errors.revision_reason && <p className="text-sm text-destructive">{errors.revision_reason}</p>}
                            </div>
                        </div>

                            <div className="grid gap-2">
                                <Label htmlFor="code">Kode</Label>
                                <Input
                                    id="code"
                                    placeholder={t('bom.code_placeholder')}
                                    value={data.code}
                                    onChange={(e) => setData('code', e.target.value)}
                                />
                                {errors.code && <p className="text-sm text-destructive">{errors.code}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="batch_quantity">{t('bom.batch_quantity')}</Label>
                                <Input
                                    id="batch_quantity"
                                    type="number"
                                    step="0.0001"
                                    min={0.0001}
                                    value={data.batch_quantity}
                                    onChange={(e) => setData('batch_quantity', e.target.value)}
                                />
                                <p className="text-xs text-muted-foreground">{t('bom.batch_quantity_hint')}</p>
                                {errors.batch_quantity && <p className="text-sm text-destructive">{errors.batch_quantity}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="status">{t('bom.status')}</Label>
                                <Select value={data.status} onValueChange={(value) => setData('status', value as Bom['status'])}>
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
                            <Label htmlFor="notes">{t('bom.notes')}</Label>
                            <Textarea id="notes" value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                        </div>

                        <div className="space-y-3">
                            <div className="flex items-center justify-between">
                                <Label className="text-base">{t('bom.components')}</Label>
                                <Button type="button" variant="default" size="sm" onClick={addLine}>
                                    <IconPlus className="mr-1 h-3.5 w-3.5" />
                                    {t('bom.add_component')}
                                </Button>
                            </div>

                            {data.lines.map((line, index) => (
                                <div key={index} className="grid grid-cols-12 items-start gap-2 rounded-lg border p-3">
                                    <div className="col-span-4">
                                        <Select
                                            value={line.component_id.toString()}
                                            onValueChange={(value) => updateLine(index, { component_id: value })}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder={t('bom.component_placeholder')} />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {products.map((p) => (
                                                    <SelectItem key={p.id} value={p.id.toString()}>
                                                        {p.code}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="col-span-3">
                                        <Input
                                            type="number"
                                            step="0.0001"
                                            placeholder={t('bom.quantity_per_batch')}
                                            value={line.quantity_per_batch}
                                            onChange={(e) => updateLine(index, { quantity_per_batch: e.target.value })}
                                        />
                                    </div>
                                    <div className="col-span-3">
                                        <Select
                                            value={line.uom_id.toString()}
                                            onValueChange={(value) => updateLine(index, { uom_id: value })}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder={t('bom.uom')} />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {unitOfMeasures.map((u) => (
                                                    <SelectItem key={u.id} value={u.id.toString()}>
                                                        {u.code}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="col-span-1">
                                        <Input
                                            type="number"
                                            step="0.01"
                                            placeholder="0%"
                                            value={line.scrap_percentage}
                                            onChange={(e) => updateLine(index, { scrap_percentage: e.target.value })}
                                        />
                                    </div>
                                    <div className="col-span-1 flex justify-end">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => removeLine(index)}
                                            disabled={data.lines.length === 1}
                                        >
                                            <X className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            ))}
                            {errors.lines && <p className="text-sm text-destructive">{errors.lines}</p>}
                        </div>
                    </form>

                    <DialogFooter className="mt-2 border-t pt-4">
                        <Button type="button" variant="cancel" onClick={() => setDialogOpen(false)} disabled={processing}>
                            Batal
                        </Button>
                        <Button type="submit" form="bom-form" variant="save" disabled={processing}>
                            {processing ? 'Menyimpan...' : 'Simpan'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={!!deleting}
                onOpenChange={(open) => !open && setDeleting(null)}
                title={t('bom.delete_title')}
                description={
                    <>
                        {t('bom.delete_confirm_prefix')}{' '}
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

BomsPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default BomsPage;

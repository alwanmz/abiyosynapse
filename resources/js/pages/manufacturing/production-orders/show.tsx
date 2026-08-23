import { ConfirmDialog } from '@/components/confirm-dialog';
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
import { type ReactElement, useState } from 'react';
import { useTranslation } from 'react-i18next';

interface Component {
    id: number;
    required_quantity: string;
    issued_quantity: string;
    component: { id: number; code: string; name: string; base_unit_of_measure: { code: string } };
}

interface Operation {
    id: number;
    sequence: number;
    name: string;
    planned_minutes: string;
    actual_minutes: string | null;
    planned_cost: string;
    actual_cost: string;
    output_quantity: string | null;
    status: 'pending' | 'in_progress' | 'complete';
    work_center: { id: number; code: string; name: string };
}

interface Order {
    id: number;
    number: string;
    planned_quantity: string;
    produced_quantity: string;
    rejected_quantity: string;
    standard_material_cost: string | null;
    actual_material_cost: string | null;
    standard_conversion_cost: string | null;
    actual_conversion_cost: string | null;
    standard_total_cost: string | null;
    actual_total_cost: string | null;
    variance_amount: string | null;
    variance_percentage: string | null;
    costed_at: string | null;
    start_date: string;
    due_date: string;
    status: 'planned' | 'released' | 'in_production' | 'qc' | 'completed' | 'closed';
    product: { id: number; code: string; name: string };
    warehouse: { id: number; code: string; name: string };
    bom: { id: number; code: string };
    routing: { id: number; code: string };
    components: Component[];
    operations: Operation[];
}

interface PageProps {
    order: Order;
}

const STATUS_VARIANT: Record<Order['status'], 'default' | 'outline' | 'secondary'> = {
    planned: 'outline',
    released: 'secondary',
    in_production: 'secondary',
    qc: 'secondary',
    completed: 'default',
    closed: 'outline',
};

function ProductionOrderShowPage({ order }: PageProps) {
    const { t } = useTranslation('manufacturing');
    const [completeDialogOpen, setCompleteDialogOpen] = useState(false);
    const [operationDialog, setOperationDialog] = useState<Operation | null>(null);

    useBreadcrumbs([
        { title: t('nav.manufacturing'), href: '#' },
        { title: t('production_order.breadcrumb'), href: '/manufacturing/production-orders' },
        { title: order.number, href: '#' },
    ]);

    const { processing: releasing } = useForm({});
    const { processing: issuing } = useForm({});
    const { processing: submittingQc } = useForm({});
    const { processing: costing } = useForm({});
    const completeForm = useForm({ produced_quantity: order.planned_quantity });
    const operationForm = useForm({ actual_minutes: '', output_quantity: '' });
    const finalInspectionForm = useForm({ quantity_passed: order.planned_quantity, notes: '' });

    const handleRelease = () => {
        router.post(`/manufacturing/production-orders/${order.id}/release`, {}, { preserveScroll: true });
    };

    const handleIssueMaterials = () => {
        router.post(`/manufacturing/production-orders/${order.id}/issue-materials`, {}, { preserveScroll: true });
    };

    const handleSubmitForQc = () => {
        router.post(`/manufacturing/production-orders/${order.id}/submit-for-qc`, {}, { preserveScroll: true });
    };

    const handleCost = () => {
        router.post(`/manufacturing/production-orders/${order.id}/cost`, {}, { preserveScroll: true });
    };

    const handleComplete = () => {
        completeForm.post(`/manufacturing/production-orders/${order.id}/complete`, {
            preserveScroll: true,
            onSuccess: () => setCompleteDialogOpen(false),
        });
    };

    const handleFinalInspection = (e: React.FormEvent) => {
        e.preventDefault();
        finalInspectionForm.post(`/manufacturing/production-orders/${order.id}/final-inspection`, {
            preserveScroll: true,
        });
    };

    const handleStartOperation = (operationId: number) => {
        router.post(`/manufacturing/operations/${operationId}/start`, {}, { preserveScroll: true });
    };

    const openCompleteOperationDialog = (operation: Operation) => {
        operationForm.setData({
            actual_minutes: operation.planned_minutes,
            output_quantity: order.planned_quantity,
        });
        setOperationDialog(operation);
    };

    const handleCompleteOperation = () => {
        if (!operationDialog) return;
        operationForm.post(`/manufacturing/operations/${operationDialog.id}/complete`, {
            preserveScroll: true,
            onSuccess: () => setOperationDialog(null),
        });
    };

    const canRelease = order.status === 'planned';
    const canIssueMaterials = order.status === 'released' || order.status === 'in_production';
    const canComplete = order.status === 'in_production';
    const canSubmitForQc = order.status === 'in_production';
    const isPendingQc = order.status === 'qc';
    const isCosted = order.costed_at !== null;
    const formatCost = (value: string | null) =>
        value === null ? '—' : Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    return (
        <>
            <Head title={t('production_order.detail_title')} />

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-semibold tracking-tight">{order.number}</h1>
                            <Badge variant={STATUS_VARIANT[order.status]}>
                                {t(`production_order.status_${order.status}`)}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {order.product.name} ({order.product.code}) · {order.warehouse.code} · {order.bom.code} /{' '}
                            {order.routing.code}
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        {canRelease && (
                            <Button onClick={handleRelease} disabled={releasing}>
                                {t('production_order.release')}
                            </Button>
                        )}
                        {canIssueMaterials && (
                            <Button variant="default" onClick={handleIssueMaterials} disabled={issuing}>
                                {t('production_order.issue_materials')}
                            </Button>
                        )}
                        {canSubmitForQc && (
                            <Button variant="default" onClick={handleSubmitForQc} disabled={submittingQc}>
                                {t('production_order.submit_for_qc')}
                            </Button>
                        )}
                        {canComplete && (
                            <Button onClick={() => setCompleteDialogOpen(true)}>{t('production_order.complete_order')}</Button>
                        )}
                    </div>
                </div>

                {isPendingQc && (
                    <Card className="mb-6 overflow-hidden p-0">
                        <ListHeader title={t('quality:final_inspection.title')} />
                        <CardContent className="p-5">
                            <p className="mb-4 text-sm text-muted-foreground">{t('quality:final_inspection.description')}</p>
                            <form onSubmit={handleFinalInspection} className="grid gap-4 sm:grid-cols-3 sm:items-end">
                                <div className="grid gap-2">
                                    <Label htmlFor="quantity_passed">{t('quality:final_inspection.quantity_passed')}</Label>
                                    <Input
                                        id="quantity_passed"
                                        type="number"
                                        step="0.0001"
                                        min={0}
                                        max={order.planned_quantity}
                                        value={finalInspectionForm.data.quantity_passed}
                                        onChange={(e) => finalInspectionForm.setData('quantity_passed', e.target.value)}
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        {t('quality:final_inspection.total_to_inspect')}: {order.planned_quantity}
                                    </p>
                                    {finalInspectionForm.errors.quantity_passed && (
                                        <p className="text-sm text-destructive">{finalInspectionForm.errors.quantity_passed}</p>
                                    )}
                                </div>
                                <div className="grid gap-2 sm:col-span-1">
                                    <Label htmlFor="notes">{t('quality:final_inspection.notes')}</Label>
                                    <Input
                                        id="notes"
                                        placeholder={t('quality:final_inspection.notes_placeholder')}
                                        value={finalInspectionForm.data.notes}
                                        onChange={(e) => finalInspectionForm.setData('notes', e.target.value)}
                                    />
                                </div>
                                <div>
                                    <Button type="submit" variant="save" disabled={finalInspectionForm.processing} className="w-full">
                                        {t('quality:final_inspection.submit')}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card className="overflow-hidden p-0">
                        <ListHeader title={t('production_order.components_title')} />
                        <CardContent className="p-5">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{t('production_order.components_table.component')}</TableHead>
                                        <TableHead className="text-right">
                                            {t('production_order.components_table.required')}
                                        </TableHead>
                                        <TableHead className="text-right">
                                            {t('production_order.components_table.issued')}
                                        </TableHead>
                                        <TableHead className="text-right">
                                            {t('production_order.components_table.remaining')}
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {order.components.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={4} className="h-16 text-center text-muted-foreground">
                                                —
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        order.components.map((c) => {
                                            const remaining = parseFloat(c.required_quantity) - parseFloat(c.issued_quantity);
                                            return (
                                                <TableRow key={c.id}>
                                                    <TableCell>
                                                        <div className="font-medium">{c.component.name}</div>
                                                        <div className="font-mono text-xs text-muted-foreground">
                                                            {c.component.code}
                                                        </div>
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {c.required_quantity} {c.component.base_unit_of_measure.code}
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {c.issued_quantity}
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {remaining.toFixed(4)}
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })
                                    )}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>

                    <Card className="overflow-hidden p-0">
                        <ListHeader title={t('production_order.operations_title')} />
                        <CardContent className="p-5">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{t('production_order.operations_table.operation')}</TableHead>
                                        <TableHead>{t('production_order.operations_table.work_center')}</TableHead>
                                        <TableHead className="text-right">
                                            {t('production_order.operations_table.planned_minutes')}
                                        </TableHead>
                                        <TableHead>{t('production_order.operations_table.status')}</TableHead>
                                        <TableHead className="text-right">
                                            {t('production_order.operations_table.actions')}
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {order.operations.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={5} className="h-16 text-center text-muted-foreground">
                                                —
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        order.operations.map((op) => (
                                            <TableRow key={op.id}>
                                                <TableCell>{op.name}</TableCell>
                                                <TableCell className="text-sm text-muted-foreground">
                                                    {op.work_center.code}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {op.planned_minutes}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant={op.status === 'complete' ? 'default' : 'outline'}>
                                                        {t(`production_order.operation_status_${op.status}`)}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    {op.status === 'pending' && (
                                                        <Button
                                                            variant="default"
                                                            size="sm"
                                                            onClick={() => handleStartOperation(op.id)}
                                                        >
                                                            {t('production_order.start_operation')}
                                                        </Button>
                                                    )}
                                                    {op.status === 'in_progress' && (
                                                        <Button
                                                            variant="default"
                                                            size="sm"
                                                            onClick={() => openCompleteOperationDialog(op)}
                                                        >
                                                            {t('production_order.complete_operation')}
                                                        </Button>
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                </div>

                {(order.status === 'completed' || order.status === 'closed') && (
                    <Card className="mt-6 overflow-hidden p-0">
                        <ListHeader title={t('production_order.costing_title')} />
                        <CardContent className="p-5">
                            <div className="mb-4 flex items-center justify-between gap-4">
                                <p className="text-sm text-muted-foreground">
                                    {isCosted
                                        ? `${t('production_order.costed_at')}: ${new Date(order.costed_at as string).toLocaleDateString()}`
                                        : t('production_order.not_costed')}
                                </p>
                                <Button variant={isCosted ? 'outline' : 'default'} onClick={handleCost} disabled={costing}>
                                    {costing ? t('production_order.costing') : t('production_order.costing_action')}
                                </Button>
                            </div>
                            <div className="grid gap-6 md:grid-cols-2">
                                <div className="space-y-3">
                                    <div className="flex items-center justify-between gap-4 text-sm">
                                        <span className="text-muted-foreground">{t('production_order.standard_material')}</span>
                                        <span className="tabular-nums">{formatCost(order.standard_material_cost)}</span>
                                    </div>
                                    <div className="flex items-center justify-between gap-4 text-sm">
                                        <span className="text-muted-foreground">{t('production_order.actual_material')}</span>
                                        <span className="tabular-nums">{formatCost(order.actual_material_cost)}</span>
                                    </div>
                                    <div className="flex items-center justify-between gap-4 border-t pt-3 text-sm">
                                        <span className="font-medium">{t('production_order.material_variance')}</span>
                                        <span className="tabular-nums">{formatCost(order.actual_material_cost !== null && order.standard_material_cost !== null ? String(Number(order.actual_material_cost) - Number(order.standard_material_cost)) : null)}</span>
                                    </div>
                                </div>
                                <div className="space-y-3">
                                    <div className="flex items-center justify-between gap-4 text-sm">
                                        <span className="text-muted-foreground">{t('production_order.standard_conversion')}</span>
                                        <span className="tabular-nums">{formatCost(order.standard_conversion_cost)}</span>
                                    </div>
                                    <div className="flex items-center justify-between gap-4 text-sm">
                                        <span className="text-muted-foreground">{t('production_order.actual_conversion')}</span>
                                        <span className="tabular-nums">{formatCost(order.actual_conversion_cost)}</span>
                                    </div>
                                    <div className="flex items-center justify-between gap-4 border-t pt-3 text-sm">
                                        <span className="font-medium">{t('production_order.conversion_variance')}</span>
                                        <span className="tabular-nums">{formatCost(order.actual_conversion_cost !== null && order.standard_conversion_cost !== null ? String(Number(order.actual_conversion_cost) - Number(order.standard_conversion_cost)) : null)}</span>
                                    </div>
                                </div>
                            </div>
                            <div className="mt-5 grid gap-3 border-t pt-4 md:grid-cols-3">
                                <div>
                                    <p className="text-xs text-muted-foreground">{t('production_order.standard_total')}</p>
                                    <p className="mt-1 text-lg font-semibold tabular-nums">{formatCost(order.standard_total_cost)}</p>
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground">{t('production_order.actual_total')}</p>
                                    <p className="mt-1 text-lg font-semibold tabular-nums">{formatCost(order.actual_total_cost)}</p>
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground">{t('production_order.variance')}</p>
                                    <p className={`mt-1 text-lg font-semibold tabular-nums ${Number(order.variance_amount ?? 0) > 0 ? 'text-destructive' : 'text-nx-andon-run'}`}>
                                        {formatCost(order.variance_amount)} {order.variance_percentage !== null ? `(${Number(order.variance_percentage).toFixed(2)}%)` : ''}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>

            <ConfirmDialog
                open={completeDialogOpen}
                onOpenChange={setCompleteDialogOpen}
                title={t('production_order.complete_order_dialog_title')}
                description={
                    <div className="space-y-3">
                        <p>{t('production_order.complete_order_dialog_description')}</p>
                        <div className="grid gap-2 text-left">
                            <Label htmlFor="produced_quantity">{t('production_order.produced_quantity_label')}</Label>
                            <Input
                                id="produced_quantity"
                                type="number"
                                step="0.0001"
                                min={0.0001}
                                value={completeForm.data.produced_quantity}
                                onChange={(e) => completeForm.setData('produced_quantity', e.target.value)}
                            />
                        </div>
                    </div>
                }
                confirmLabel={t('production_order.complete_order')}
                variant="default"
                loading={completeForm.processing}
                onConfirm={handleComplete}
            />

            <Dialog open={!!operationDialog} onOpenChange={(open) => !open && setOperationDialog(null)}>
                <DialogContent className="sm:max-w-[400px]">
                    <DialogHeader>
                        <DialogTitle>{t('production_order.complete_operation_dialog_title')}</DialogTitle>
                        <DialogDescription>{operationDialog?.name}</DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-4">
                        <div className="grid gap-2">
                            <Label htmlFor="actual_minutes">{t('production_order.actual_minutes_label')}</Label>
                            <Input
                                id="actual_minutes"
                                type="number"
                                step="0.01"
                                min={0}
                                value={operationForm.data.actual_minutes}
                                onChange={(e) => operationForm.setData('actual_minutes', e.target.value)}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="output_quantity">{t('production_order.output_quantity_label')}</Label>
                            <Input
                                id="output_quantity"
                                type="number"
                                step="0.0001"
                                min={0}
                                value={operationForm.data.output_quantity}
                                onChange={(e) => operationForm.setData('output_quantity', e.target.value)}
                            />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="cancel"
                            onClick={() => setOperationDialog(null)}
                            disabled={operationForm.processing}
                        >
                            Batal
                        </Button>
                        <Button onClick={handleCompleteOperation} disabled={operationForm.processing}>
                            {t('production_order.complete_operation')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

ProductionOrderShowPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default ProductionOrderShowPage;

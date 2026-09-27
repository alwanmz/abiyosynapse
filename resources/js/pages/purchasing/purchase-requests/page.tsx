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
import { X } from 'lucide-react';
import { type ReactElement, useState } from 'react';
import { useTranslation } from 'react-i18next';

interface PurchaseRequestLine {
    id: number;
    quantity: string;
    converted_quantity: string;
    product: { id: number; code: string; name: string };
}

interface PurchaseRequest {
    id: number;
    number: string;
    notes: string | null;
    status: 'draft' | 'submitted' | 'approved' | 'rejected' | 'converted';
    approved_at: string | null;
    rejection_reason?: string | null;
    warehouse: { id: number; code: string; name: string };
    requester: { id: number; name: string } | null;
    lines: PurchaseRequestLine[];
}

interface Option {
    id: number;
    code: string;
    name: string;
}

interface PageProps {
    requests: PurchaseRequest[];
    products: Option[];
    warehouses: Option[];
}

interface LineForm {
    product_id: string | number;
    quantity: string;
}

const emptyLine: LineForm = { product_id: '', quantity: '' };

const STATUS_VARIANT: Record<PurchaseRequest['status'], 'default' | 'outline' | 'secondary' | 'destructive'> = {
    draft: 'outline',
    submitted: 'secondary',
    approved: 'default',
    rejected: 'destructive',
    converted: 'default',
};

function PurchaseRequestsPage({ requests, products, warehouses }: PageProps) {
    const { t } = useTranslation('purchasing');
    const [dialogOpen, setDialogOpen] = useState(false);
    const [rejectingRequest, setRejectingRequest] = useState<PurchaseRequest | null>(null);

    useBreadcrumbs([
        { title: t('nav.purchasing'), href: '#' },
        { title: t('purchase_request.breadcrumb'), href: '/purchasing/purchase-requests' },
    ]);

    const { data, setData, post, processing, errors, clearErrors, reset } = useForm<{
        warehouse_id: string | number;
        notes: string;
        lines: LineForm[];
    }>({
        warehouse_id: '',
        notes: '',
        lines: [{ ...emptyLine }],
    });

    const { processing: actionProcessing } = useForm({});
    const rejectionForm = useForm({ rejection_reason: '' });

    const openCreate = () => {
        reset();
        setData('lines', [{ ...emptyLine }]);
        setDialogOpen(true);
    };

    const addLine = () => setData('lines', [...data.lines, { ...emptyLine }]);
    const removeLine = (index: number) => setData('lines', data.lines.filter((_, i) => i !== index));
    const updateLine = (index: number, patch: Partial<LineForm>) =>
        setData('lines', data.lines.map((l, i) => (i === index ? { ...l, ...patch } : l)));

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/purchasing/purchase-requests', {
            preserveScroll: true,
            onSuccess: () => {
                setDialogOpen(false);
                reset();
            },
        });
    };

    const handleSubmitForApproval = (id: number) => {
        router.post(`/purchasing/purchase-requests/${id}/submit`, {}, { preserveScroll: true });
    };

    const handleApprove = (id: number) => {
        router.post(`/purchasing/purchase-requests/${id}/approve`, {}, { preserveScroll: true });
    };

    const handleReject = (request: PurchaseRequest) => {
        rejectionForm.reset();
        setRejectingRequest(request);
    };

    const submitRejection = (event: React.FormEvent) => {
        event.preventDefault();

        if (!rejectingRequest) return;

        rejectionForm.post(`/purchasing/purchase-requests/${rejectingRequest.id}/reject`, {
            preserveScroll: true,
            onSuccess: () => setRejectingRequest(null),
        });
    };

    return (
        <>
            <Head title={t('purchase_request.title')} />

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">{t('purchase_request.title')}</h1>
                        <p className="text-sm text-muted-foreground">{t('purchase_request.description')}</p>
                    </div>
                    <Button onClick={openCreate}>
                        <IconPlus className="mr-2 h-4 w-4" />
                        {t('purchase_request.add')}
                    </Button>
                </div>

                <Card className="overflow-hidden p-0">
                    <ListHeader title={t('purchase_request.list_title')} />
                    <CardContent className="p-5">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('purchase_request.table.number')}</TableHead>
                                    <TableHead>{t('purchase_request.table.warehouse')}</TableHead>
                                    <TableHead>{t('purchase_request.table.requester')}</TableHead>
                                    <TableHead className="text-right">{t('purchase_request.table.lines')}</TableHead>
                                    <TableHead>{t('purchase_request.table.status')}</TableHead>
                                    <TableHead className="text-right">{t('purchase_request.table.actions')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {requests.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={6} className="h-24 text-center text-muted-foreground">
                                            {t('purchase_request.empty')}
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    requests.map((pr) => (
                                        <TableRow key={pr.id}>
                                            <TableCell className="font-mono text-sm">{pr.number}</TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {pr.warehouse.code}
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {pr.requester?.name ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">{pr.lines.length}</TableCell>
                                            <TableCell>
                                                <Badge variant={STATUS_VARIANT[pr.status]}>
                                                    {t(`purchase_request.status_${pr.status}`)}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex flex-wrap justify-end gap-2">
                                                    <PrintDocumentButton type="purchase_request" documentId={pr.id} compact />
                                                    {pr.status === 'draft' && (
                                                        <Button
                                                            variant="default"
                                                            size="sm"
                                                            disabled={actionProcessing}
                                                            onClick={() => handleSubmitForApproval(pr.id)}
                                                        >
                                                            {t('purchase_request.submit')}
                                                        </Button>
                                                    )}
                                                    {pr.status === 'submitted' && (
                                                        <>
                                                            <Button
                                                                variant="default"
                                                                size="sm"
                                                                disabled={actionProcessing}
                                                                onClick={() => handleApprove(pr.id)}
                                                            >
                                                                {t('purchase_request.approve')}
                                                            </Button>
                                                            <Button
                                                                variant="destructive"
                                                                size="sm"
                                                                className="text-destructive hover:text-destructive"
                                                                disabled={actionProcessing}
                                                                onClick={() => handleReject(pr)}
                                                            >
                                                                {t('purchase_request.reject')}
                                                            </Button>
                                                        </>
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
                        <DialogTitle>{t('purchase_request.add_title')}</DialogTitle>
                        <DialogDescription>{t('purchase_request.description')}</DialogDescription>
                    </DialogHeader>

                    <form id="pr-form" onSubmit={handleSubmit} className="flex-1 space-y-4 overflow-y-auto pr-2">
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="warehouse_id">{t('purchase_request.warehouse')}</Label>
                                <Select
                                    value={data.warehouse_id.toString()}
                                    onValueChange={(value) => {
                                        setData('warehouse_id', value);
                                        clearErrors('warehouse_id');
                                    }}
                                >
                                    <SelectTrigger id="warehouse_id">
                                        <SelectValue placeholder={t('purchase_request.warehouse_placeholder')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {warehouses.map((w) => (
                                            <SelectItem key={w.id} value={w.id.toString()}>
                                                {w.code} — {w.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.warehouse_id && <p className="text-sm text-destructive">{errors.warehouse_id}</p>}
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="notes">{t('purchase_request.notes')}</Label>
                            <Textarea
                                id="notes"
                                placeholder={t('purchase_request.notes_placeholder')}
                                value={data.notes}
                                onChange={(e) => setData('notes', e.target.value)}
                            />
                        </div>

                        <div className="space-y-3">
                            <div className="flex items-center justify-between">
                                <Label className="text-base">{t('purchase_request.lines')}</Label>
                                <Button type="button" variant="default" size="sm" onClick={addLine}>
                                    <IconPlus className="mr-1 h-3.5 w-3.5" />
                                    {t('purchase_request.add_line')}
                                </Button>
                            </div>

                            {data.lines.map((line, index) => (
                                <div key={index} className="grid grid-cols-12 items-start gap-2 rounded-lg border p-3">
                                    <div className="col-span-8">
                                        <Select
                                            value={line.product_id.toString()}
                                            onValueChange={(value) => updateLine(index, { product_id: value })}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder={t('purchase_request.product_placeholder')} />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {products.map((p) => (
                                                    <SelectItem key={p.id} value={p.id.toString()}>
                                                        {p.code} — {p.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="col-span-3">
                                        <Input
                                            type="number"
                                            step="0.0001"
                                            min={0.0001}
                                            placeholder={t('purchase_request.quantity')}
                                            value={line.quantity}
                                            onChange={(e) => updateLine(index, { quantity: e.target.value })}
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
                        <Button type="submit" form="pr-form" disabled={processing}>
                            {t('purchase_request.add')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={!!rejectingRequest} onOpenChange={(open) => !open && setRejectingRequest(null)}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{t('purchase_request.reject_title')}</DialogTitle>
                        <DialogDescription>
                            {rejectingRequest?.number} · {t('purchase_request.reject_description')}
                        </DialogDescription>
                    </DialogHeader>
                    <form id="reject-pr-form" onSubmit={submitRejection} className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="rejection_reason">{t('purchase_request.rejection_reason')}</Label>
                            <Textarea
                                id="rejection_reason"
                                value={rejectionForm.data.rejection_reason}
                                placeholder={t('purchase_request.rejection_reason_placeholder')}
                                onChange={(event) => rejectionForm.setData('rejection_reason', event.target.value)}
                            />
                            {rejectionForm.errors.rejection_reason && <p className="text-sm text-destructive">{rejectionForm.errors.rejection_reason}</p>}
                        </div>
                    </form>
                    <DialogFooter>
                        <Button type="button" variant="cancel" onClick={() => setRejectingRequest(null)} disabled={rejectionForm.processing}>{t('purchase_request.cancel')}</Button>
                        <Button type="submit" form="reject-pr-form" variant="destructive" disabled={rejectionForm.processing}>{rejectionForm.processing ? t('purchase_request.rejecting') : t('purchase_request.reject')}</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

PurchaseRequestsPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default PurchaseRequestsPage;

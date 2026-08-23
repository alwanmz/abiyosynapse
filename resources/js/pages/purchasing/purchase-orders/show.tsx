import { ListHeader } from '@/components/list-header';
import { PrintDocumentButton } from '@/components/print-document-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { type ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

interface PurchaseOrderLine {
    id: number;
    quantity: string;
    unit_price: string;
    received_quantity: string;
    invoiced_quantity: string;
    product: { id: number; code: string; name: string };
    tax_code: { id: number; code: string; rate: string } | null;
}

interface GoodsReceipt {
    id: number;
    number: string;
    received_date: string;
    status: 'pending_inspection' | 'put_away';
}

interface SupplierInvoice {
    id: number;
    number: string;
    total: string;
    status: 'pending_match' | 'matched' | 'disputed' | 'paid';
}

interface PurchaseOrder {
    id: number;
    number: string;
    order_date: string;
    expected_date: string | null;
    status: 'draft' | 'approval' | 'approved' | 'sent' | 'partial' | 'received' | 'closed';
    subtotal: string;
    tax_total: string;
    total: string;
    approved_at: string | null;
    sent_at: string | null;
    closed_at: string | null;
    supplier: { id: number; code: string; name: string };
    warehouse: { id: number; code: string; name: string };
    lines: PurchaseOrderLine[];
    goods_receipts: GoodsReceipt[];
    supplier_invoices: SupplierInvoice[];
}

interface PageProps {
    order: PurchaseOrder;
}

interface ReceiveLineForm {
    purchase_order_line_id: number;
    quantity_received: string;
}

interface InvoiceLineForm {
    purchase_order_line_id: number;
    quantity: string;
    unit_price: string;
}

const STATUS_VARIANT: Record<PurchaseOrder['status'], 'default' | 'outline' | 'secondary'> = {
    draft: 'outline',
    approval: 'secondary',
    approved: 'secondary',
    sent: 'secondary',
    partial: 'secondary',
    received: 'default',
    closed: 'outline',
};

const GR_STATUS_VARIANT: Record<GoodsReceipt['status'], 'default' | 'outline' | 'secondary'> = {
    pending_inspection: 'secondary',
    put_away: 'default',
};

const SINV_STATUS_VARIANT: Record<SupplierInvoice['status'], 'default' | 'outline' | 'secondary' | 'destructive'> = {
    pending_match: 'secondary',
    matched: 'default',
    disputed: 'destructive',
    paid: 'default',
};

function PurchaseOrderShowPage({ order }: PageProps) {
    const { t } = useTranslation('purchasing');
    const { locale } = usePage().props as { locale?: string };

    useBreadcrumbs([
        { title: t('nav.purchasing'), href: '#' },
        { title: t('purchase_order.breadcrumb'), href: '/purchasing/purchase-orders' },
        { title: order.number, href: '#' },
    ]);

    const formatDate = (value: string) =>
        new Date(value).toLocaleDateString(locale ?? 'id-ID', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });

    const { processing: submitting } = useForm({});
    const { processing: approving } = useForm({});
    const { processing: sending } = useForm({});
    const { processing: closing } = useForm({});

    const receiveForm = useForm<{ lines: ReceiveLineForm[] }>({
        lines: order.lines
            .filter((line) => parseFloat(line.received_quantity) < parseFloat(line.quantity))
            .map((line) => ({
                purchase_order_line_id: line.id,
                quantity_received: (parseFloat(line.quantity) - parseFloat(line.received_quantity)).toString(),
            })),
    });

    const invoiceForm = useForm<{
        supplier_reference: string;
        invoice_date: string;
        due_date: string;
        lines: InvoiceLineForm[];
    }>({
        supplier_reference: '',
        invoice_date: new Date().toISOString().slice(0, 10),
        due_date: '',
        lines: order.lines
            .filter((line) => parseFloat(line.invoiced_quantity) < parseFloat(line.received_quantity))
            .map((line) => ({
                purchase_order_line_id: line.id,
                quantity: (parseFloat(line.received_quantity) - parseFloat(line.invoiced_quantity)).toString(),
                unit_price: line.unit_price,
            })),
    });

    const handleSubmitForApproval = () => {
        router.post(`/purchasing/purchase-orders/${order.id}/submit-for-approval`, {}, { preserveScroll: true });
    };

    const handleApprove = () => {
        router.post(`/purchasing/purchase-orders/${order.id}/approve`, {}, { preserveScroll: true });
    };

    const handleSend = () => {
        router.post(`/purchasing/purchase-orders/${order.id}/send`, {}, { preserveScroll: true });
    };

    const handleClose = () => {
        router.post(`/purchasing/purchase-orders/${order.id}/close`, {}, { preserveScroll: true });
    };

    const updateReceiveLine = (index: number, patch: Partial<ReceiveLineForm>) =>
        receiveForm.setData(
            'lines',
            receiveForm.data.lines.map((l, i) => (i === index ? { ...l, ...patch } : l)),
        );

    const updateInvoiceLine = (index: number, patch: Partial<InvoiceLineForm>) =>
        invoiceForm.setData(
            'lines',
            invoiceForm.data.lines.map((l, i) => (i === index ? { ...l, ...patch } : l)),
        );

    const handleReceive = (e: React.FormEvent) => {
        e.preventDefault();
        receiveForm.post(`/purchasing/purchase-orders/${order.id}/goods-receipts`, {
            preserveScroll: true,
        });
    };

    const handleInvoice = (e: React.FormEvent) => {
        e.preventDefault();
        invoiceForm.post(`/purchasing/purchase-orders/${order.id}/supplier-invoices`, {
            preserveScroll: true,
        });
    };

    const lineById = (id: number) => order.lines.find((l) => l.id === id);

    const canSubmitForApproval = order.status === 'draft';
    const canApprove = order.status === 'approval';
    const canSend = order.status === 'approved';
    const canClose = ['approved', 'sent', 'partial', 'received'].includes(order.status);
    const canReceive = ['approved', 'sent', 'partial'].includes(order.status) && receiveForm.data.lines.length > 0;
    const canInvoice = ['sent', 'partial', 'received'].includes(order.status) && invoiceForm.data.lines.length > 0;

    return (
        <>
            <Head title={t('purchase_order.detail_title')} />

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-semibold tracking-tight">{order.number}</h1>
                            <Badge variant={STATUS_VARIANT[order.status]}>
                                {t(`purchase_order.status_${order.status}`)}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {order.supplier.name} ({order.supplier.code}) · {order.warehouse.code}
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <PrintDocumentButton type="purchase_order" documentId={order.id} />
                        {canSubmitForApproval && (
                            <Button onClick={handleSubmitForApproval} disabled={submitting}>
                                {t('purchase_order.submit_for_approval')}
                            </Button>
                        )}
                        {canApprove && (
                            <Button onClick={handleApprove} disabled={approving}>
                                {t('purchase_order.approve')}
                            </Button>
                        )}
                        {canSend && (
                            <Button onClick={handleSend} disabled={sending}>
                                {t('purchase_order.send')}
                            </Button>
                        )}
                        {canClose && (
                            <Button variant="default" onClick={handleClose} disabled={closing}>
                                {t('purchase_order.close')}
                            </Button>
                        )}
                    </div>
                </div>

                <Card className="mb-6 overflow-hidden p-0">
                    <ListHeader title={t('purchase_order.lines')} />
                    <CardContent className="p-5">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('purchase_order.table.product')}</TableHead>
                                    <TableHead className="text-right">{t('purchase_order.table.quantity')}</TableHead>
                                    <TableHead className="text-right">{t('purchase_order.table.unit_price')}</TableHead>
                                    <TableHead>{t('purchase_order.table.tax_code')}</TableHead>
                                    <TableHead className="text-right">{t('purchase_order.table.received')}</TableHead>
                                    <TableHead className="text-right">{t('purchase_order.table.invoiced')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {order.lines.map((line) => (
                                    <TableRow key={line.id}>
                                        <TableCell>
                                            <div className="font-medium">{line.product.name}</div>
                                            <div className="font-mono text-xs text-muted-foreground">{line.product.code}</div>
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">{line.quantity}</TableCell>
                                        <TableCell className="text-right tabular-nums">{line.unit_price}</TableCell>
                                        <TableCell className="text-sm text-muted-foreground">
                                            {line.tax_code ? `${line.tax_code.code} (${line.tax_code.rate}%)` : '—'}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">{line.received_quantity}</TableCell>
                                        <TableCell className="text-right tabular-nums">{line.invoiced_quantity}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        <div className="mt-4 flex justify-end">
                            <div className="w-64 space-y-1 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">{t('purchase_order.subtotal')}</span>
                                    <span className="tabular-nums">{order.subtotal}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">{t('purchase_order.tax_total')}</span>
                                    <span className="tabular-nums">{order.tax_total}</span>
                                </div>
                                <div className="flex justify-between font-semibold">
                                    <span>{t('purchase_order.total')}</span>
                                    <span className="tabular-nums">{order.total}</span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {canReceive && (
                    <Card className="mb-6 overflow-hidden p-0">
                        <ListHeader title={t('purchase_order.receive_goods_title')} />
                        <CardContent className="p-5">
                            <form onSubmit={handleReceive} className="space-y-3">
                                {receiveForm.data.lines.map((line, index) => {
                                    const meta = lineById(line.purchase_order_line_id);
                                    const max = meta ? parseFloat(meta.quantity) - parseFloat(meta.received_quantity) : undefined;
                                    return (
                                        <div key={line.purchase_order_line_id} className="grid grid-cols-12 items-center gap-2 rounded-lg border p-3">
                                            <div className="col-span-8">
                                                <div className="text-sm font-medium">{meta?.product.name}</div>
                                                <div className="font-mono text-xs text-muted-foreground">{meta?.product.code}</div>
                                            </div>
                                            <div className="col-span-4">
                                                <Label className="mb-1 block text-xs text-muted-foreground">
                                                    {t('purchase_order.quantity_received')} ({t('purchase_order.max_prefix')} {max})
                                                </Label>
                                                <Input
                                                    type="number"
                                                    step="0.0001"
                                                    min={0.0001}
                                                    max={max}
                                                    value={line.quantity_received}
                                                    onChange={(e) => updateReceiveLine(index, { quantity_received: e.target.value })}
                                                />
                                            </div>
                                        </div>
                                    );
                                })}
                                {receiveForm.errors.lines && (
                                    <p className="text-sm text-destructive">{receiveForm.errors.lines}</p>
                                )}
                                <div className="flex justify-end">
                                    <Button type="submit" disabled={receiveForm.processing}>
                                        {t('purchase_order.receive')}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {canInvoice && (
                    <Card className="mb-6 overflow-hidden p-0">
                        <ListHeader title={t('purchase_order.create_invoice_title')} />
                        <CardContent className="p-5">
                            <form onSubmit={handleInvoice} className="space-y-4">
                                <div className="grid gap-4 md:grid-cols-3">
                                    <div className="grid gap-2">
                                        <Label htmlFor="supplier_reference">{t('purchase_order.supplier_reference')}</Label>
                                        <Input
                                            id="supplier_reference"
                                            value={invoiceForm.data.supplier_reference}
                                            onChange={(e) => invoiceForm.setData('supplier_reference', e.target.value)}
                                        />
                                        {invoiceForm.errors.supplier_reference && (
                                            <p className="text-sm text-destructive">{invoiceForm.errors.supplier_reference}</p>
                                        )}
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="invoice_date">{t('purchase_order.invoice_date')}</Label>
                                        <Input
                                            id="invoice_date"
                                            type="date"
                                            value={invoiceForm.data.invoice_date}
                                            onChange={(e) => invoiceForm.setData('invoice_date', e.target.value)}
                                        />
                                        {invoiceForm.errors.invoice_date && (
                                            <p className="text-sm text-destructive">{invoiceForm.errors.invoice_date}</p>
                                        )}
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="due_date">{t('purchase_order.due_date')}</Label>
                                        <Input
                                            id="due_date"
                                            type="date"
                                            value={invoiceForm.data.due_date}
                                            onChange={(e) => invoiceForm.setData('due_date', e.target.value)}
                                        />
                                        {invoiceForm.errors.due_date && (
                                            <p className="text-sm text-destructive">{invoiceForm.errors.due_date}</p>
                                        )}
                                    </div>
                                </div>

                                <div className="space-y-3">
                                    {invoiceForm.data.lines.map((line, index) => {
                                        const meta = lineById(line.purchase_order_line_id);
                                        const max = meta
                                            ? parseFloat(meta.received_quantity) - parseFloat(meta.invoiced_quantity)
                                            : undefined;
                                        return (
                                            <div key={line.purchase_order_line_id} className="grid grid-cols-12 items-center gap-2 rounded-lg border p-3">
                                                <div className="col-span-5">
                                                    <div className="text-sm font-medium">{meta?.product.name}</div>
                                                    <div className="font-mono text-xs text-muted-foreground">{meta?.product.code}</div>
                                                </div>
                                                <div className="col-span-4">
                                                    <Label className="mb-1 block text-xs text-muted-foreground">
                                                        {t('purchase_order.quantity')} ({t('purchase_order.max_prefix')} {max})
                                                    </Label>
                                                    <Input
                                                        type="number"
                                                        step="0.0001"
                                                        min={0.0001}
                                                        max={max}
                                                        value={line.quantity}
                                                        onChange={(e) => updateInvoiceLine(index, { quantity: e.target.value })}
                                                    />
                                                </div>
                                                <div className="col-span-3">
                                                    <Label className="mb-1 block text-xs text-muted-foreground">
                                                        {t('purchase_order.unit_price')}
                                                    </Label>
                                                    <Input
                                                        type="number"
                                                        step="0.01"
                                                        min={0}
                                                        value={line.unit_price}
                                                        onChange={(e) => updateInvoiceLine(index, { unit_price: e.target.value })}
                                                    />
                                                </div>
                                            </div>
                                        );
                                    })}
                                    {invoiceForm.errors.lines && (
                                        <p className="text-sm text-destructive">{invoiceForm.errors.lines}</p>
                                    )}
                                </div>

                                <div className="flex justify-end">
                                    <Button type="submit" disabled={invoiceForm.processing}>
                                        {t('purchase_order.create_invoice')}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card className="overflow-hidden p-0">
                        <ListHeader title={t('purchase_order.goods_receipts_title')} />
                        <CardContent className="p-5">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{t('purchase_order.table.number')}</TableHead>
                                        <TableHead>{t('purchase_order.table.received_date')}</TableHead>
                                        <TableHead>{t('purchase_order.table.status')}</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {order.goods_receipts.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={3} className="h-16 text-center text-muted-foreground">
                                                —
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        order.goods_receipts.map((gr) => (
                                            <TableRow key={gr.id}>
                                                <TableCell className="font-mono text-sm">
                                                    <Link
                                                        href={`/purchasing/goods-receipts/${gr.id}`}
                                                        className="text-primary hover:underline"
                                                    >
                                                        {gr.number}
                                                    </Link>
                                                </TableCell>
                                                <TableCell className="text-sm text-muted-foreground">{formatDate(gr.received_date)}</TableCell>
                                                <TableCell>
                                                    <Badge variant={GR_STATUS_VARIANT[gr.status]}>
                                                        {t(`goods_receipt.status_${gr.status}`)}
                                                    </Badge>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>

                    <Card className="overflow-hidden p-0">
                        <ListHeader title={t('purchase_order.supplier_invoices_title')} />
                        <CardContent className="p-5">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{t('purchase_order.table.number')}</TableHead>
                                        <TableHead className="text-right">{t('purchase_order.table.total')}</TableHead>
                                        <TableHead>{t('purchase_order.table.status')}</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {order.supplier_invoices.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={3} className="h-16 text-center text-muted-foreground">
                                                —
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        order.supplier_invoices.map((inv) => (
                                            <TableRow key={inv.id}>
                                                <TableCell className="font-mono text-sm">
                                                    <Link
                                                        href={`/purchasing/supplier-invoices/${inv.id}`}
                                                        className="text-primary hover:underline"
                                                    >
                                                        {inv.number}
                                                    </Link>
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">{inv.total}</TableCell>
                                                <TableCell>
                                                    <Badge variant={SINV_STATUS_VARIANT[inv.status]}>
                                                        {t(`supplier_invoice.status_${inv.status}`)}
                                                    </Badge>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

PurchaseOrderShowPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default PurchaseOrderShowPage;

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

interface SalesOrderLine {
    id: number;
    quantity: string;
    unit_price: string;
    delivered_quantity: string;
    invoiced_quantity: string;
    product: { id: number; code: string; name: string };
    tax_code: { id: number; code: string; rate: string } | null;
}

interface DeliveryOrder {
    id: number;
    number: string;
    delivery_date: string;
    status: 'draft' | 'shipped';
}

interface SalesInvoice {
    id: number;
    number: string;
    total: string;
    status: 'posted' | 'paid';
}

interface SalesOrder {
    id: number;
    number: string;
    order_date: string;
    requested_delivery_date: string | null;
    status: 'draft' | 'approval' | 'approved' | 'partial' | 'fulfilled' | 'closed';
    subtotal: string;
    tax_total: string;
    total: string;
    approved_at: string | null;
    closed_at: string | null;
    customer: { id: number; code: string; name: string };
    warehouse: { id: number; code: string; name: string };
    lines: SalesOrderLine[];
    delivery_orders: DeliveryOrder[];
    sales_invoices: SalesInvoice[];
}

interface PageProps {
    order: SalesOrder;
}

interface DeliveryLineForm {
    sales_order_line_id: number;
    quantity: string;
}

interface InvoiceLineForm {
    sales_order_line_id: number;
    quantity: string;
}

const STATUS_VARIANT: Record<SalesOrder['status'], 'default' | 'outline' | 'secondary'> = {
    draft: 'outline',
    approval: 'secondary',
    approved: 'secondary',
    partial: 'secondary',
    fulfilled: 'default',
    closed: 'outline',
};

const DO_STATUS_VARIANT: Record<DeliveryOrder['status'], 'default' | 'outline' | 'secondary'> = {
    draft: 'secondary',
    shipped: 'default',
};

const SINV_STATUS_VARIANT: Record<SalesInvoice['status'], 'default' | 'outline' | 'secondary'> = {
    posted: 'secondary',
    paid: 'default',
};

function SalesOrderShowPage({ order }: PageProps) {
    const { t } = useTranslation('sales');
    const { locale } = usePage().props as { locale?: string };

    useBreadcrumbs([
        { title: t('nav.sales'), href: '#' },
        { title: t('sales_order.breadcrumb'), href: '/sales/sales-orders' },
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
    const { processing: closing } = useForm({});

    const deliveryForm = useForm<{ lines: DeliveryLineForm[] }>({
        lines: order.lines
            .filter((line) => parseFloat(line.delivered_quantity) < parseFloat(line.quantity))
            .map((line) => ({
                sales_order_line_id: line.id,
                quantity: (parseFloat(line.quantity) - parseFloat(line.delivered_quantity)).toString(),
            })),
    });

    const invoiceForm = useForm<{
        invoice_date: string;
        due_date: string;
        lines: InvoiceLineForm[];
    }>({
        invoice_date: new Date().toISOString().slice(0, 10),
        due_date: '',
        lines: order.lines
            .filter((line) => parseFloat(line.invoiced_quantity) < parseFloat(line.delivered_quantity))
            .map((line) => ({
                sales_order_line_id: line.id,
                quantity: (parseFloat(line.delivered_quantity) - parseFloat(line.invoiced_quantity)).toString(),
            })),
    });

    const handleSubmitForApproval = () => {
        router.post(`/sales/sales-orders/${order.id}/submit-for-approval`, {}, { preserveScroll: true });
    };

    const handleApprove = () => {
        router.post(`/sales/sales-orders/${order.id}/approve`, {}, { preserveScroll: true });
    };

    const handleClose = () => {
        router.post(`/sales/sales-orders/${order.id}/close`, {}, { preserveScroll: true });
    };

    const updateDeliveryLine = (index: number, patch: Partial<DeliveryLineForm>) =>
        deliveryForm.setData(
            'lines',
            deliveryForm.data.lines.map((l, i) => (i === index ? { ...l, ...patch } : l)),
        );

    const updateInvoiceLine = (index: number, patch: Partial<InvoiceLineForm>) =>
        invoiceForm.setData(
            'lines',
            invoiceForm.data.lines.map((l, i) => (i === index ? { ...l, ...patch } : l)),
        );

    const handleCreateDelivery = (e: React.FormEvent) => {
        e.preventDefault();
        deliveryForm.post(`/sales/sales-orders/${order.id}/delivery-orders`, {
            preserveScroll: true,
        });
    };

    const handleCreateInvoice = (e: React.FormEvent) => {
        e.preventDefault();
        invoiceForm.post(`/sales/sales-orders/${order.id}/sales-invoices`, {
            preserveScroll: true,
        });
    };

    const lineById = (id: number) => order.lines.find((l) => l.id === id);

    const canSubmitForApproval = order.status === 'draft';
    const canApprove = order.status === 'approval';
    const canClose = ['approved', 'partial', 'fulfilled'].includes(order.status);
    const canDeliver = ['approved', 'partial'].includes(order.status) && deliveryForm.data.lines.length > 0;
    const canInvoice = ['approved', 'partial', 'fulfilled'].includes(order.status) && invoiceForm.data.lines.length > 0;

    return (
        <>
            <Head title={t('sales_order.detail_title')} />

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-semibold tracking-tight">{order.number}</h1>
                            <Badge variant={STATUS_VARIANT[order.status]}>
                                {t(`sales_order.status_${order.status}`)}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {order.customer.name} ({order.customer.code}) · {order.warehouse.code}
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <PrintDocumentButton type="sales_order" documentId={order.id} />
                        {canSubmitForApproval && (
                            <Button onClick={handleSubmitForApproval} disabled={submitting}>
                                {t('sales_order.submit_for_approval')}
                            </Button>
                        )}
                        {canApprove && (
                            <Button onClick={handleApprove} disabled={approving}>
                                {t('sales_order.approve')}
                            </Button>
                        )}
                        {canClose && (
                            <Button variant="default" onClick={handleClose} disabled={closing}>
                                {t('sales_order.close')}
                            </Button>
                        )}
                    </div>
                </div>

                <Card className="mb-6 overflow-hidden p-0">
                    <ListHeader title={t('sales_order.lines')} />
                    <CardContent className="p-5">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('sales_order.table.product')}</TableHead>
                                    <TableHead className="text-right">{t('sales_order.table.quantity')}</TableHead>
                                    <TableHead className="text-right">{t('sales_order.table.unit_price')}</TableHead>
                                    <TableHead>{t('sales_order.table.tax_code')}</TableHead>
                                    <TableHead className="text-right">{t('sales_order.table.delivered')}</TableHead>
                                    <TableHead className="text-right">{t('sales_order.table.invoiced')}</TableHead>
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
                                        <TableCell className="text-right tabular-nums">{line.delivered_quantity}</TableCell>
                                        <TableCell className="text-right tabular-nums">{line.invoiced_quantity}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        <div className="mt-4 flex justify-end">
                            <div className="w-64 space-y-1 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">{t('sales_order.subtotal')}</span>
                                    <span className="tabular-nums">{order.subtotal}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">{t('sales_order.tax_total')}</span>
                                    <span className="tabular-nums">{order.tax_total}</span>
                                </div>
                                <div className="flex justify-between font-semibold">
                                    <span>{t('sales_order.total')}</span>
                                    <span className="tabular-nums">{order.total}</span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {canDeliver && (
                    <Card className="mb-6 overflow-hidden p-0">
                        <ListHeader title={t('sales_order.create_delivery_title')} />
                        <CardContent className="p-5">
                            <form onSubmit={handleCreateDelivery} className="space-y-3">
                                {deliveryForm.data.lines.map((line, index) => {
                                    const meta = lineById(line.sales_order_line_id);
                                    const max = meta ? parseFloat(meta.quantity) - parseFloat(meta.delivered_quantity) : undefined;
                                    return (
                                        <div key={line.sales_order_line_id} className="grid grid-cols-12 items-center gap-2 rounded-lg border p-3">
                                            <div className="col-span-8">
                                                <div className="text-sm font-medium">{meta?.product.name}</div>
                                                <div className="font-mono text-xs text-muted-foreground">{meta?.product.code}</div>
                                            </div>
                                            <div className="col-span-4">
                                                <Label className="mb-1 block text-xs text-muted-foreground">
                                                    {t('sales_order.quantity_to_deliver')} ({t('sales_order.max_prefix')} {max})
                                                </Label>
                                                <Input
                                                    type="number"
                                                    step="0.0001"
                                                    min={0.0001}
                                                    max={max}
                                                    value={line.quantity}
                                                    onChange={(e) => updateDeliveryLine(index, { quantity: e.target.value })}
                                                />
                                            </div>
                                        </div>
                                    );
                                })}
                                {deliveryForm.errors.lines && (
                                    <p className="text-sm text-destructive">{deliveryForm.errors.lines}</p>
                                )}
                                <div className="flex justify-end">
                                    <Button type="submit" disabled={deliveryForm.processing}>
                                        {t('sales_order.create_delivery')}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {canInvoice && (
                    <Card className="mb-6 overflow-hidden p-0">
                        <ListHeader title={t('sales_order.create_invoice_title')} />
                        <CardContent className="p-5">
                            <form onSubmit={handleCreateInvoice} className="space-y-4">
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="invoice_date">{t('sales_order.invoice_date')}</Label>
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
                                        <Label htmlFor="due_date">{t('sales_order.due_date')}</Label>
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
                                        const meta = lineById(line.sales_order_line_id);
                                        const max = meta
                                            ? parseFloat(meta.delivered_quantity) - parseFloat(meta.invoiced_quantity)
                                            : undefined;
                                        return (
                                            <div key={line.sales_order_line_id} className="grid grid-cols-12 items-center gap-2 rounded-lg border p-3">
                                                <div className="col-span-8">
                                                    <div className="text-sm font-medium">{meta?.product.name}</div>
                                                    <div className="font-mono text-xs text-muted-foreground">{meta?.product.code}</div>
                                                </div>
                                                <div className="col-span-4">
                                                    <Label className="mb-1 block text-xs text-muted-foreground">
                                                        {t('sales_order.quantity_to_invoice')} ({t('sales_order.max_prefix')} {max})
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
                                            </div>
                                        );
                                    })}
                                    {invoiceForm.errors.lines && (
                                        <p className="text-sm text-destructive">{invoiceForm.errors.lines}</p>
                                    )}
                                </div>

                                <div className="flex justify-end">
                                    <Button type="submit" disabled={invoiceForm.processing}>
                                        {t('sales_order.create_invoice')}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card className="overflow-hidden p-0">
                        <ListHeader title={t('sales_order.delivery_orders_title')} />
                        <CardContent className="p-5">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{t('sales_order.table.number')}</TableHead>
                                        <TableHead>{t('delivery_order.table.delivery_date')}</TableHead>
                                        <TableHead>{t('sales_order.table.status')}</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {order.delivery_orders.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={3} className="h-16 text-center text-muted-foreground">
                                                —
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        order.delivery_orders.map((doo) => (
                                            <TableRow key={doo.id}>
                                                <TableCell className="font-mono text-sm">
                                                    <Link
                                                        href={`/sales/delivery-orders/${doo.id}`}
                                                        className="text-primary hover:underline"
                                                    >
                                                        {doo.number}
                                                    </Link>
                                                </TableCell>
                                                <TableCell className="text-sm text-muted-foreground">{formatDate(doo.delivery_date)}</TableCell>
                                                <TableCell>
                                                    <Badge variant={DO_STATUS_VARIANT[doo.status]}>
                                                        {t(`delivery_order.status_${doo.status}`)}
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
                        <ListHeader title={t('sales_order.sales_invoices_title')} />
                        <CardContent className="p-5">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{t('sales_order.table.number')}</TableHead>
                                        <TableHead className="text-right">{t('sales_order.table.total')}</TableHead>
                                        <TableHead>{t('sales_order.table.status')}</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {order.sales_invoices.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={3} className="h-16 text-center text-muted-foreground">
                                                —
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        order.sales_invoices.map((inv) => (
                                            <TableRow key={inv.id}>
                                                <TableCell className="font-mono text-sm">
                                                    <Link
                                                        href={`/sales/sales-invoices/${inv.id}`}
                                                        className="text-primary hover:underline"
                                                    >
                                                        {inv.number}
                                                    </Link>
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">{inv.total}</TableCell>
                                                <TableCell>
                                                    <Badge variant={SINV_STATUS_VARIANT[inv.status]}>
                                                        {t(`sales_invoice.status_${inv.status}`)}
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

SalesOrderShowPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default SalesOrderShowPage;

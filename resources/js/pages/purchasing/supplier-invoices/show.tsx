import { ListHeader } from '@/components/list-header';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
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
import { Head, Link } from '@inertiajs/react';
import { type ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

interface SupplierInvoiceLine {
    id: number;
    quantity: string;
    unit_price: string;
    tax_amount: string;
    product: { id: number; code: string; name: string };
}

interface SupplierInvoice {
    id: number;
    number: string;
    supplier_reference: string | null;
    invoice_date: string;
    due_date: string;
    subtotal: string;
    tax_total: string;
    total: string;
    status: 'pending_match' | 'matched' | 'disputed' | 'paid';
    dispute_notes: string | null;
    purchase_order: { id: number; number: string };
    supplier: { id: number; code: string; name: string };
    lines: SupplierInvoiceLine[];
}

interface PageProps {
    invoice: SupplierInvoice;
}

const STATUS_VARIANT: Record<SupplierInvoice['status'], 'default' | 'outline' | 'secondary' | 'destructive'> = {
    pending_match: 'secondary',
    matched: 'default',
    disputed: 'destructive',
    paid: 'default',
};

function SupplierInvoiceShowPage({ invoice }: PageProps) {
    const { t } = useTranslation('purchasing');

    useBreadcrumbs([
        { title: t('nav.purchasing'), href: '#' },
        { title: t('supplier_invoice.breadcrumb'), href: '/purchasing/supplier-invoices' },
        { title: invoice.number, href: '#' },
    ]);

    return (
        <>
            <Head title={t('supplier_invoice.detail_title')} />

            <div className="p-6">
                <div className="mb-6">
                    <div className="flex items-center gap-3">
                        <h1 className="text-2xl font-semibold tracking-tight">{invoice.number}</h1>
                        <Badge variant={STATUS_VARIANT[invoice.status]}>
                            {t(`supplier_invoice.status_${invoice.status}`)}
                        </Badge>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {invoice.supplier.name} ({invoice.supplier.code}) ·{' '}
                        <Link href={`/purchasing/purchase-orders/${invoice.purchase_order.id}`} className="text-primary hover:underline">
                            {invoice.purchase_order.number}
                        </Link>
                        {invoice.supplier_reference && <> · {t('supplier_invoice.supplier_reference')}: {invoice.supplier_reference}</>}
                    </p>
                    {invoice.status === 'disputed' && invoice.dispute_notes && (
                        <p className="mt-2 text-sm text-destructive">{invoice.dispute_notes}</p>
                    )}
                </div>

                <Card className="overflow-hidden p-0">
                    <ListHeader title={t('supplier_invoice.lines')} />
                    <CardContent className="p-5">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('supplier_invoice.table.product')}</TableHead>
                                    <TableHead className="text-right">{t('supplier_invoice.table.quantity')}</TableHead>
                                    <TableHead className="text-right">{t('supplier_invoice.table.unit_price')}</TableHead>
                                    <TableHead className="text-right">{t('supplier_invoice.table.tax_amount')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {invoice.lines.map((line) => (
                                    <TableRow key={line.id}>
                                        <TableCell>
                                            <div className="font-medium">{line.product.name}</div>
                                            <div className="font-mono text-xs text-muted-foreground">{line.product.code}</div>
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">{line.quantity}</TableCell>
                                        <TableCell className="text-right tabular-nums">{line.unit_price}</TableCell>
                                        <TableCell className="text-right tabular-nums">{line.tax_amount}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        <div className="mt-4 flex justify-end">
                            <div className="w-64 space-y-1 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">{t('supplier_invoice.subtotal')}</span>
                                    <span className="tabular-nums">{invoice.subtotal}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">{t('supplier_invoice.tax_total')}</span>
                                    <span className="tabular-nums">{invoice.tax_total}</span>
                                </div>
                                <div className="flex justify-between font-semibold">
                                    <span>{t('supplier_invoice.total')}</span>
                                    <span className="tabular-nums">{invoice.total}</span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

SupplierInvoiceShowPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default SupplierInvoiceShowPage;

import { ListHeader } from '@/components/list-header';
import { PrintDocumentButton } from '@/components/print-document-button';
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
import { Head, Link, usePage } from '@inertiajs/react';
import { type ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

interface SalesInvoiceLine {
    id: number;
    quantity: string;
    unit_price: string;
    tax_amount: string;
    product: { id: number; code: string; name: string };
}

interface SalesReturn {
    id: number;
    number: string;
    return_date: string;
    total: string;
}

interface SalesInvoice {
    id: number;
    number: string;
    invoice_date: string;
    due_date: string;
    subtotal: string;
    tax_total: string;
    total: string;
    status: 'posted' | 'paid';
    sales_order: { id: number; number: string };
    customer: { id: number; code: string; name: string };
    lines: SalesInvoiceLine[];
    sales_returns: SalesReturn[];
}

interface PageProps {
    invoice: SalesInvoice;
}

const STATUS_VARIANT: Record<SalesInvoice['status'], 'default' | 'outline' | 'secondary'> = {
    posted: 'secondary',
    paid: 'default',
};

function SalesInvoiceShowPage({ invoice }: PageProps) {
    const { t } = useTranslation('sales');
    const { locale } = usePage().props as { locale?: string };

    useBreadcrumbs([
        { title: t('nav.sales'), href: '#' },
        { title: t('sales_invoice.breadcrumb'), href: '/sales/sales-invoices' },
        { title: invoice.number, href: '#' },
    ]);

    const formatDate = (value: string) =>
        new Date(value).toLocaleDateString(locale ?? 'id-ID', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });

    return (
        <>
            <Head title={t('sales_invoice.detail_title')} />

            <div className="p-6">
                <div className="mb-6">
                    <div className="flex items-center gap-3">
                        <h1 className="text-2xl font-semibold tracking-tight">{invoice.number}</h1>
                        <Badge variant={STATUS_VARIANT[invoice.status]}>
                            {t(`sales_invoice.status_${invoice.status}`)}
                        </Badge>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {invoice.customer.name} ({invoice.customer.code}) ·{' '}
                        <Link href={`/sales/sales-orders/${invoice.sales_order.id}`} className="text-primary hover:underline">
                            {invoice.sales_order.number}
                        </Link>
                    </p>
                    <div className="mt-3"><PrintDocumentButton type="sales_invoice" documentId={invoice.id} /></div>
                </div>

                <Card className="mb-6 overflow-hidden p-0">
                    <ListHeader title={t('sales_invoice.lines')} />
                    <CardContent className="p-5">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('sales_invoice.table.product')}</TableHead>
                                    <TableHead className="text-right">{t('sales_invoice.table.quantity')}</TableHead>
                                    <TableHead className="text-right">{t('sales_invoice.table.unit_price')}</TableHead>
                                    <TableHead className="text-right">{t('sales_invoice.table.tax_amount')}</TableHead>
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
                                    <span className="text-muted-foreground">{t('sales_invoice.subtotal')}</span>
                                    <span className="tabular-nums">{invoice.subtotal}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">{t('sales_invoice.tax_total')}</span>
                                    <span className="tabular-nums">{invoice.tax_total}</span>
                                </div>
                                <div className="flex justify-between font-semibold">
                                    <span>{t('sales_invoice.total')}</span>
                                    <span className="tabular-nums">{invoice.total}</span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card className="overflow-hidden p-0">
                    <ListHeader title={t('sales_invoice.sales_returns_title')} />
                    <CardContent className="p-5">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('sales_return.table.number')}</TableHead>
                                    <TableHead>{t('sales_return.table.return_date')}</TableHead>
                                    <TableHead className="text-right">{t('sales_return.total')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {invoice.sales_returns.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={3} className="h-16 text-center text-muted-foreground">
                                            —
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    invoice.sales_returns.map((ret) => (
                                        <TableRow key={ret.id}>
                                            <TableCell className="font-mono text-sm">
                                                <Link
                                                    href={`/sales/sales-returns/${ret.id}`}
                                                    className="text-primary hover:underline"
                                                >
                                                    {ret.number}
                                                </Link>
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">{formatDate(ret.return_date)}</TableCell>
                                            <TableCell className="text-right tabular-nums">{ret.total}</TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

SalesInvoiceShowPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default SalesInvoiceShowPage;

import { ListHeader } from '@/components/list-header';
import { PrintDocumentButton } from '@/components/print-document-button';
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

interface SalesReturnLine {
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
    reason: string | null;
    subtotal: string;
    tax_total: string;
    total: string;
    sales_invoice: { id: number; number: string };
    warehouse: { id: number; code: string; name: string };
    lines: SalesReturnLine[];
}

interface PageProps {
    return: SalesReturn;
}

function SalesReturnShowPage({ return: salesReturn }: PageProps) {
    const { t } = useTranslation('sales');
    const { locale } = usePage().props as { locale?: string };

    useBreadcrumbs([
        { title: t('nav.sales'), href: '#' },
        { title: t('sales_return.breadcrumb'), href: '/sales/sales-returns' },
        { title: salesReturn.number, href: '#' },
    ]);

    const formatDate = (value: string) =>
        new Date(value).toLocaleDateString(locale ?? 'id-ID', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });

    return (
        <>
            <Head title={t('sales_return.detail_title')} />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold tracking-tight">{salesReturn.number}</h1>
                    <p className="text-sm text-muted-foreground">
                        <Link href={`/sales/sales-invoices/${salesReturn.sales_invoice.id}`} className="text-primary hover:underline">
                            {salesReturn.sales_invoice.number}
                        </Link>
                        {' · '}
                        {salesReturn.warehouse.code} · {formatDate(salesReturn.return_date)}
                    </p>
                    {salesReturn.reason && (
                        <p className="mt-2 text-sm text-muted-foreground">
                            <span className="font-medium text-foreground">{t('sales_return.reason_label')}:</span> {salesReturn.reason}
                        </p>
                    )}
                    <div className="mt-3"><PrintDocumentButton type="sales_return" documentId={salesReturn.id} /></div>
                </div>

                <Card className="overflow-hidden p-0">
                    <ListHeader title={t('sales_return.lines')} />
                    <CardContent className="p-5">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('sales_return.table.product')}</TableHead>
                                    <TableHead className="text-right">{t('sales_return.table.quantity')}</TableHead>
                                    <TableHead className="text-right">{t('sales_return.table.unit_price')}</TableHead>
                                    <TableHead className="text-right">{t('sales_return.table.tax_amount')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {salesReturn.lines.map((line) => (
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
                                    <span className="text-muted-foreground">{t('sales_return.subtotal')}</span>
                                    <span className="tabular-nums">{salesReturn.subtotal}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">{t('sales_return.tax_total')}</span>
                                    <span className="tabular-nums">{salesReturn.tax_total}</span>
                                </div>
                                <div className="flex justify-between font-semibold">
                                    <span>{t('sales_return.total')}</span>
                                    <span className="tabular-nums">{salesReturn.total}</span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

SalesReturnShowPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default SalesReturnShowPage;

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
import { Head, Link, usePage } from '@inertiajs/react';
import { type ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

interface SalesInvoice {
    id: number;
    number: string;
    invoice_date: string;
    due_date: string;
    total: string;
    status: 'posted' | 'paid';
    sales_order: { id: number; number: string };
    customer: { id: number; code: string; name: string };
}

interface PageProps {
    invoices: SalesInvoice[];
}

const STATUS_VARIANT: Record<SalesInvoice['status'], 'default' | 'outline' | 'secondary'> = {
    posted: 'secondary',
    paid: 'default',
};

function SalesInvoicesPage({ invoices }: PageProps) {
    const { t } = useTranslation('sales');
    const { locale } = usePage().props as { locale?: string };

    useBreadcrumbs([
        { title: t('nav.sales'), href: '#' },
        { title: t('sales_invoice.breadcrumb'), href: '/sales/sales-invoices' },
    ]);

    const formatDate = (value: string) =>
        new Date(value).toLocaleDateString(locale ?? 'id-ID', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });

    return (
        <>
            <Head title={t('sales_invoice.title')} />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold tracking-tight">{t('sales_invoice.title')}</h1>
                    <p className="text-sm text-muted-foreground">{t('sales_invoice.description')}</p>
                </div>

                <Card className="overflow-hidden p-0">
                    <ListHeader title={t('sales_invoice.list_title')} />
                    <CardContent className="p-5">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('sales_invoice.table.number')}</TableHead>
                                    <TableHead>{t('sales_invoice.table.sales_order')}</TableHead>
                                    <TableHead>{t('sales_invoice.table.customer')}</TableHead>
                                    <TableHead>{t('sales_invoice.table.invoice_date')}</TableHead>
                                    <TableHead>{t('sales_invoice.table.due_date')}</TableHead>
                                    <TableHead className="text-right">{t('sales_invoice.table.total')}</TableHead>
                                    <TableHead>{t('sales_invoice.table.status')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {invoices.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={7} className="h-24 text-center text-muted-foreground">
                                            {t('sales_invoice.empty')}
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    invoices.map((inv) => (
                                        <TableRow key={inv.id}>
                                            <TableCell className="font-mono text-sm">
                                                <Link
                                                    href={`/sales/sales-invoices/${inv.id}`}
                                                    className="text-primary hover:underline"
                                                >
                                                    {inv.number}
                                                </Link>
                                            </TableCell>
                                            <TableCell className="font-mono text-sm text-muted-foreground">
                                                {inv.sales_order.number}
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">{inv.customer.name}</TableCell>
                                            <TableCell className="text-sm text-muted-foreground">{formatDate(inv.invoice_date)}</TableCell>
                                            <TableCell className="text-sm text-muted-foreground">{formatDate(inv.due_date)}</TableCell>
                                            <TableCell className="text-right tabular-nums">{inv.total}</TableCell>
                                            <TableCell>
                                                <Badge variant={STATUS_VARIANT[inv.status]}>
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
        </>
    );
}

SalesInvoicesPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default SalesInvoicesPage;

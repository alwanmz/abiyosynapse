import { ListHeader } from '@/components/list-header';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
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

interface SupplierInvoice {
    id: number;
    number: string;
    invoice_date: string;
    due_date: string;
    total: string;
    status: 'pending_match' | 'matched' | 'disputed' | 'paid';
    dispute_notes: string | null;
    purchase_order: { id: number; number: string };
    supplier: { id: number; code: string; name: string };
}

interface PageProps {
    invoices: SupplierInvoice[];
}

const STATUS_VARIANT: Record<SupplierInvoice['status'], 'default' | 'outline' | 'secondary' | 'destructive'> = {
    pending_match: 'secondary',
    matched: 'default',
    disputed: 'destructive',
    paid: 'default',
};

function SupplierInvoicesPage({ invoices }: PageProps) {
    const { t } = useTranslation('purchasing');
    const { locale } = usePage().props as { locale?: string };

    useBreadcrumbs([
        { title: t('nav.purchasing'), href: '#' },
        { title: t('supplier_invoice.breadcrumb'), href: '/purchasing/supplier-invoices' },
    ]);

    const formatDate = (value: string) =>
        new Date(value).toLocaleDateString(locale ?? 'id-ID', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });

    return (
        <>
            <Head title={t('supplier_invoice.title')} />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold tracking-tight">{t('supplier_invoice.title')}</h1>
                    <p className="text-sm text-muted-foreground">{t('supplier_invoice.description')}</p>
                </div>

                <Card className="overflow-hidden p-0">
                    <ListHeader title={t('supplier_invoice.list_title')} />
                    <CardContent className="p-5">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('supplier_invoice.table.number')}</TableHead>
                                    <TableHead>{t('supplier_invoice.table.purchase_order')}</TableHead>
                                    <TableHead>{t('supplier_invoice.table.supplier')}</TableHead>
                                    <TableHead>{t('supplier_invoice.table.invoice_date')}</TableHead>
                                    <TableHead>{t('supplier_invoice.table.due_date')}</TableHead>
                                    <TableHead className="text-right">{t('supplier_invoice.table.total')}</TableHead>
                                    <TableHead>{t('supplier_invoice.table.status')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {invoices.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={7} className="h-24 text-center text-muted-foreground">
                                            {t('supplier_invoice.empty')}
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    invoices.map((inv) => (
                                        <TableRow key={inv.id}>
                                            <TableCell className="font-mono text-sm">
                                                <Link
                                                    href={`/purchasing/supplier-invoices/${inv.id}`}
                                                    className="text-primary hover:underline"
                                                >
                                                    {inv.number}
                                                </Link>
                                            </TableCell>
                                            <TableCell className="font-mono text-sm text-muted-foreground">
                                                {inv.purchase_order.number}
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">{inv.supplier.name}</TableCell>
                                            <TableCell className="text-sm text-muted-foreground">{formatDate(inv.invoice_date)}</TableCell>
                                            <TableCell className="text-sm text-muted-foreground">{formatDate(inv.due_date)}</TableCell>
                                            <TableCell className="text-right tabular-nums">{inv.total}</TableCell>
                                            <TableCell>
                                                {inv.status === 'disputed' && inv.dispute_notes ? (
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <Badge variant={STATUS_VARIANT[inv.status]} className="cursor-help">
                                                                {t(`supplier_invoice.status_${inv.status}`)}
                                                            </Badge>
                                                        </TooltipTrigger>
                                                        <TooltipContent>{inv.dispute_notes}</TooltipContent>
                                                    </Tooltip>
                                                ) : (
                                                    <Badge variant={STATUS_VARIANT[inv.status]}>
                                                        {t(`supplier_invoice.status_${inv.status}`)}
                                                    </Badge>
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
        </>
    );
}

SupplierInvoicesPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default SupplierInvoicesPage;

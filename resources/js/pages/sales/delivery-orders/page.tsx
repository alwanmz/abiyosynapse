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

interface DeliveryOrder {
    id: number;
    number: string;
    delivery_date: string;
    status: 'draft' | 'shipped' | 'cancelled';
    sales_order: { id: number; number: string; customer: { id: number; name: string } };
    warehouse: { id: number; code: string; name: string };
}

interface PageProps {
    deliveries: DeliveryOrder[];
}

const STATUS_VARIANT: Record<DeliveryOrder['status'], 'default' | 'outline' | 'secondary'> = {
    draft: 'secondary',
    shipped: 'default',
    cancelled: 'outline',
};

function DeliveryOrdersPage({ deliveries }: PageProps) {
    const { t } = useTranslation('sales');
    const { locale } = usePage().props as { locale?: string };

    useBreadcrumbs([
        { title: t('nav.sales'), href: '#' },
        { title: t('delivery_order.breadcrumb'), href: '/sales/delivery-orders' },
    ]);

    const formatDate = (value: string) =>
        new Date(value).toLocaleDateString(locale ?? 'id-ID', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });

    return (
        <>
            <Head title={t('delivery_order.title')} />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold tracking-tight">{t('delivery_order.title')}</h1>
                    <p className="text-sm text-muted-foreground">{t('delivery_order.description')}</p>
                </div>

                <Card className="overflow-hidden p-0">
                    <ListHeader title={t('delivery_order.list_title')} />
                    <CardContent className="p-5">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('delivery_order.table.number')}</TableHead>
                                    <TableHead>{t('delivery_order.table.sales_order')}</TableHead>
                                    <TableHead>{t('delivery_order.table.customer')}</TableHead>
                                    <TableHead>{t('delivery_order.table.warehouse')}</TableHead>
                                    <TableHead>{t('delivery_order.table.delivery_date')}</TableHead>
                                    <TableHead>{t('delivery_order.table.status')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {deliveries.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={6} className="h-24 text-center text-muted-foreground">
                                            {t('delivery_order.empty')}
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    deliveries.map((doo) => (
                                        <TableRow key={doo.id}>
                                            <TableCell className="font-mono text-sm">
                                                <Link
                                                    href={`/sales/delivery-orders/${doo.id}`}
                                                    className="text-primary hover:underline"
                                                >
                                                    {doo.number}
                                                </Link>
                                            </TableCell>
                                            <TableCell className="font-mono text-sm text-muted-foreground">
                                                {doo.sales_order.number}
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {doo.sales_order.customer.name}
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">{doo.warehouse.code}</TableCell>
                                            <TableCell className="text-sm text-muted-foreground">{formatDate(doo.delivery_date)}</TableCell>
                                            <TableCell>
                                                <Badge variant={STATUS_VARIANT[doo.status]}>
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
            </div>
        </>
    );
}

DeliveryOrdersPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default DeliveryOrdersPage;

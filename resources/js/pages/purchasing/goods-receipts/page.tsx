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

interface GoodsReceipt {
    id: number;
    number: string;
    received_date: string;
    status: 'pending_inspection' | 'put_away';
    purchase_order: { id: number; number: string; supplier: { id: number; name: string } };
    warehouse: { id: number; code: string; name: string };
}

interface PageProps {
    receipts: GoodsReceipt[];
}

const STATUS_VARIANT: Record<GoodsReceipt['status'], 'default' | 'outline' | 'secondary'> = {
    pending_inspection: 'secondary',
    put_away: 'default',
};

function GoodsReceiptsPage({ receipts }: PageProps) {
    const { t } = useTranslation('purchasing');
    const { locale } = usePage().props as { locale?: string };

    useBreadcrumbs([
        { title: t('nav.purchasing'), href: '#' },
        { title: t('goods_receipt.breadcrumb'), href: '/purchasing/goods-receipts' },
    ]);

    const formatDate = (value: string) =>
        new Date(value).toLocaleDateString(locale ?? 'id-ID', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });

    return (
        <>
            <Head title={t('goods_receipt.title')} />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold tracking-tight">{t('goods_receipt.title')}</h1>
                    <p className="text-sm text-muted-foreground">{t('goods_receipt.description')}</p>
                </div>

                <Card className="overflow-hidden p-0">
                    <ListHeader title={t('goods_receipt.list_title')} />
                    <CardContent className="p-5">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('goods_receipt.table.number')}</TableHead>
                                    <TableHead>{t('goods_receipt.table.purchase_order')}</TableHead>
                                    <TableHead>{t('goods_receipt.table.supplier')}</TableHead>
                                    <TableHead>{t('goods_receipt.table.warehouse')}</TableHead>
                                    <TableHead>{t('goods_receipt.table.received_date')}</TableHead>
                                    <TableHead>{t('goods_receipt.table.status')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {receipts.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={6} className="h-24 text-center text-muted-foreground">
                                            {t('goods_receipt.empty')}
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    receipts.map((gr) => (
                                        <TableRow key={gr.id}>
                                            <TableCell className="font-mono text-sm">
                                                <Link
                                                    href={`/purchasing/goods-receipts/${gr.id}`}
                                                    className="text-primary hover:underline"
                                                >
                                                    {gr.number}
                                                </Link>
                                            </TableCell>
                                            <TableCell className="font-mono text-sm text-muted-foreground">
                                                {gr.purchase_order.number}
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {gr.purchase_order.supplier.name}
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">{gr.warehouse.code}</TableCell>
                                            <TableCell className="text-sm text-muted-foreground">{formatDate(gr.received_date)}</TableCell>
                                            <TableCell>
                                                <Badge variant={STATUS_VARIANT[gr.status]}>
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
            </div>
        </>
    );
}

GoodsReceiptsPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default GoodsReceiptsPage;

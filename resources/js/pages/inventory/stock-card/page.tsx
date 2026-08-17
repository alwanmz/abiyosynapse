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
import { ArrowLeft } from 'lucide-react';

interface Movement {
    id: number;
    type: 'in' | 'out' | 'transfer_in' | 'transfer_out';
    quantity: string;
    unit_cost: string;
    total_cost: string;
    balance_quantity: string;
    created_at: string;
    warehouse: { id: number; code: string; name: string };
}

interface PaginatedMovements {
    data: Movement[];
    current_page: number;
    last_page: number;
    total: number;
}

interface PageProps {
    product: { id: number; code: string; name: string; base_unit_of_measure: { code: string } };
    movements: PaginatedMovements;
}

const TYPE_VARIANT: Record<Movement['type'], 'default' | 'outline' | 'secondary'> = {
    in: 'default',
    out: 'outline',
    transfer_in: 'secondary',
    transfer_out: 'secondary',
};

function StockCardPage({ product, movements }: PageProps) {
    const { t } = useTranslation('inventory');
    const { locale } = usePage().props as { locale?: string };

    useBreadcrumbs([
        { title: t('nav.inventory'), href: '#' },
        { title: t('stock_overview.breadcrumb'), href: '/inventory/stock-overview' },
        { title: product.code, href: '#' },
    ]);

    const formatDate = (value: string) =>
        new Date(value).toLocaleDateString(locale ?? 'id-ID', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });

    return (
        <>
            <Head title={t('stock_card.title')} />

            <div className="p-6">
                <div className="mb-6">
                    <Link
                        href="/inventory/stock-overview"
                        className="mb-2 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft className="h-3.5 w-3.5" />
                        {t('stock_card.back')}
                    </Link>
                    <h1 className="text-2xl font-semibold tracking-tight">{product.name}</h1>
                    <p className="text-sm text-muted-foreground">
                        {product.code} — {t('stock_card.description')}
                    </p>
                </div>

                <Card className="overflow-hidden p-0">
                    <ListHeader title={t('stock_card.title')} />
                    <CardContent className="p-5">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('stock_card.table.date')}</TableHead>
                                    <TableHead>{t('stock_card.table.warehouse')}</TableHead>
                                    <TableHead>{t('stock_card.table.type')}</TableHead>
                                    <TableHead className="text-right">{t('stock_card.table.quantity')}</TableHead>
                                    <TableHead className="text-right">{t('stock_card.table.unit_cost')}</TableHead>
                                    <TableHead className="text-right">{t('stock_card.table.total_cost')}</TableHead>
                                    <TableHead className="text-right">{t('stock_card.table.balance')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {movements.data.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={7} className="h-24 text-center text-muted-foreground">
                                            {t('stock_card.empty')}
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    movements.data.map((movement) => (
                                        <TableRow key={movement.id}>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {formatDate(movement.created_at)}
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {movement.warehouse.code}
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant={TYPE_VARIANT[movement.type]}>
                                                    {t(`stock_card.type_${movement.type}`)}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {movement.quantity} {product.base_unit_of_measure.code}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">{movement.unit_cost}</TableCell>
                                            <TableCell className="text-right tabular-nums">{movement.total_cost}</TableCell>
                                            <TableCell className="text-right font-medium tabular-nums">
                                                {movement.balance_quantity}
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

StockCardPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default StockCardPage;

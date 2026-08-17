import { ListHeader } from '@/components/list-header';
import { Card, CardContent } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
import { Head, Link, router } from '@inertiajs/react';
import { type ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

interface StockLevel {
    id: number;
    quantity_on_hand: string;
    average_unit_cost: string;
    product: { id: number; code: string; name: string; base_uom_id: number; base_unit_of_measure: { code: string } };
    warehouse: { id: number; code: string; name: string };
}

interface WarehouseOption {
    id: number;
    code: string;
    name: string;
}

interface PageProps {
    stockLevels: StockLevel[];
    warehouses: WarehouseOption[];
    filters: { warehouse_id?: string };
}

function StockOverviewPage({ stockLevels, warehouses, filters }: PageProps) {
    const { t } = useTranslation('inventory');

    useBreadcrumbs([
        { title: t('nav.inventory'), href: '#' },
        { title: t('stock_overview.breadcrumb'), href: '/inventory/stock-overview' },
    ]);

    const handleWarehouseChange = (value: string) => {
        router.get(
            '/inventory/stock-overview',
            value === 'all' ? {} : { warehouse_id: value },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <>
            <Head title={t('stock_overview.title')} />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold tracking-tight">{t('stock_overview.title')}</h1>
                    <p className="text-sm text-muted-foreground">{t('stock_overview.description')}</p>
                </div>

                <Card className="overflow-hidden p-0">
                    <ListHeader
                        title={t('stock_overview.list_title')}
                        action={
                            <Select value={filters.warehouse_id ?? 'all'} onValueChange={handleWarehouseChange}>
                                <SelectTrigger className="w-[220px]">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">{t('stock_overview.all_warehouses')}</SelectItem>
                                    {warehouses.map((warehouse) => (
                                        <SelectItem key={warehouse.id} value={warehouse.id.toString()}>
                                            {warehouse.code} — {warehouse.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        }
                    />
                    <CardContent className="p-5">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('stock_overview.table.product')}</TableHead>
                                    <TableHead>{t('stock_overview.table.warehouse')}</TableHead>
                                    <TableHead className="text-right">{t('stock_overview.table.quantity_on_hand')}</TableHead>
                                    <TableHead className="text-right">{t('stock_overview.table.average_cost')}</TableHead>
                                    <TableHead className="text-right">{t('stock_overview.table.total_value')}</TableHead>
                                    <TableHead className="text-right" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {stockLevels.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={6} className="h-24 text-center text-muted-foreground">
                                            {t('stock_overview.empty')}
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    stockLevels.map((level) => (
                                        <TableRow key={level.id}>
                                            <TableCell>
                                                <div className="font-medium">{level.product.name}</div>
                                                <div className="font-mono text-xs text-muted-foreground">{level.product.code}</div>
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {level.warehouse.code} — {level.warehouse.name}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {level.quantity_on_hand} {level.product.base_unit_of_measure.code}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">{level.average_unit_cost}</TableCell>
                                            <TableCell className="text-right tabular-nums font-medium">
                                                {(parseFloat(level.quantity_on_hand) * parseFloat(level.average_unit_cost)).toFixed(2)}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Link
                                                    href={`/inventory/stock-overview/${level.product.id}/card`}
                                                    className="text-sm text-primary hover:underline"
                                                >
                                                    {t('stock_overview.view_card')}
                                                </Link>
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

StockOverviewPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default StockOverviewPage;

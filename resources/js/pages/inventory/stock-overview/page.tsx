import { ListHeader } from '@/components/list-header';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
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
    quality_quantities: Record<'pending' | 'approved' | 'hold' | 'rejected' | 'rework', number>;
    reserved_quantity: number;
    available_for_sale: number;
    readiness: { status: ReadinessStatus; reasons: string[] };
}

type ReadinessStatus = 'not_configured' | 'pending_qc' | 'quality_hold' | 'rework_required' | 'out_of_stock' | 'ready_for_sale';

interface WarehouseOption {
    id: number;
    code: string;
    name: string;
}

interface PageProps {
    stockLevels: StockLevel[];
    warehouses: WarehouseOption[];
    filters: { warehouse_id?: string; category?: string; quality_state?: string; ready_for_sale?: string };
}

const readinessVariant: Record<ReadinessStatus, 'default' | 'secondary' | 'outline' | 'destructive'> = {
    ready_for_sale: 'default', pending_qc: 'secondary', quality_hold: 'secondary', rework_required: 'destructive', out_of_stock: 'outline', not_configured: 'destructive',
};

function StockOverviewPage({ stockLevels, warehouses, filters }: PageProps) {
    const { t } = useTranslation('inventory');

    useBreadcrumbs([
        { title: t('nav.inventory'), href: '#' },
        { title: t('stock_overview.breadcrumb'), href: '/inventory/stock-overview' },
    ]);

    const applyFilters = (patch: Record<string, string>) => {
        const next = { ...filters, ...patch };
        Object.entries(next).forEach(([key, value]) => {
            if (value === 'all' || value === '' || value === undefined) delete next[key as keyof typeof next];
        });
        router.get(
            '/inventory/stock-overview',
            next,
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
                            <div className="flex flex-wrap gap-2">
                                <Select value={filters.warehouse_id ?? 'all'} onValueChange={(value) => applyFilters({ warehouse_id: value })}>
                                    <SelectTrigger className="w-[190px]"><SelectValue /></SelectTrigger>
                                    <SelectContent><SelectItem value="all">{t('stock_overview.all_warehouses')}</SelectItem>{warehouses.map((warehouse) => <SelectItem key={warehouse.id} value={warehouse.id.toString()}>{warehouse.code} — {warehouse.name}</SelectItem>)}</SelectContent>
                                </Select>
                                <Select value={filters.category ?? 'all'} onValueChange={(value) => applyFilters({ category: value })}>
                                    <SelectTrigger className="w-[155px]"><SelectValue /></SelectTrigger>
                                    <SelectContent><SelectItem value="all">{t('stock_overview.all_categories')}</SelectItem><SelectItem value="raw_material">{t('stock_overview.category_raw_material')}</SelectItem><SelectItem value="wip">{t('stock_overview.category_wip')}</SelectItem><SelectItem value="finished_goods">{t('stock_overview.category_finished_goods')}</SelectItem></SelectContent>
                                </Select>
                                <Select value={filters.quality_state ?? 'all'} onValueChange={(value) => applyFilters({ quality_state: value })}>
                                    <SelectTrigger className="w-[150px]"><SelectValue /></SelectTrigger>
                                    <SelectContent><SelectItem value="all">{t('stock_overview.all_quality')}</SelectItem>{(['approved', 'hold', 'rejected', 'rework', 'pending'] as const).map((state) => <SelectItem key={state} value={state}>{t(`stock_overview.quality_${state}`)}</SelectItem>)}</SelectContent>
                                </Select>
                                <Select value={filters.ready_for_sale ?? 'all'} onValueChange={(value) => applyFilters({ ready_for_sale: value })}>
                                    <SelectTrigger className="w-[150px]"><SelectValue /></SelectTrigger>
                                    <SelectContent><SelectItem value="all">{t('stock_overview.all_readiness')}</SelectItem><SelectItem value="1">{t('stock_overview.ready_for_sale')}</SelectItem></SelectContent>
                                </Select>
                            </div>
                        }
                    />
                    <CardContent className="p-5">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('stock_overview.table.product')}</TableHead>
                                    <TableHead>{t('stock_overview.table.warehouse')}</TableHead>
                                    <TableHead className="text-right">{t('stock_overview.table.quantity_on_hand')}</TableHead>
                                    <TableHead className="text-right">{t('stock_overview.table.approved')}</TableHead>
                                    <TableHead className="text-right">{t('stock_overview.table.hold')}</TableHead>
                                    <TableHead className="text-right">{t('stock_overview.table.rejected_rework')}</TableHead>
                                    <TableHead className="text-right">{t('stock_overview.table.reserved')}</TableHead>
                                    <TableHead className="text-right">{t('stock_overview.table.available')}</TableHead>
                                    <TableHead>{t('stock_overview.table.readiness')}</TableHead>
                                    <TableHead className="text-right">{t('stock_overview.table.average_cost')}</TableHead>
                                    <TableHead className="text-right">{t('stock_overview.table.total_value')}</TableHead>
                                    <TableHead className="text-right" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {stockLevels.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={12} className="h-24 text-center text-muted-foreground">
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
                                            <TableCell className="text-right tabular-nums text-nx-andon-run">{level.quality_quantities.approved.toFixed(4)}</TableCell>
                                            <TableCell className="text-right tabular-nums text-nx-andon-caution">{level.quality_quantities.hold.toFixed(4)}</TableCell>
                                            <TableCell className="text-right tabular-nums text-destructive">{(level.quality_quantities.rejected + level.quality_quantities.rework).toFixed(4)}</TableCell>
                                            <TableCell className="text-right tabular-nums">{level.reserved_quantity.toFixed(4)}</TableCell>
                                            <TableCell className="text-right font-semibold tabular-nums">{level.available_for_sale.toFixed(4)}</TableCell>
                                            <TableCell><Badge variant={readinessVariant[level.readiness.status]}>{t(`stock_overview.readiness_${level.readiness.status}`)}</Badge></TableCell>
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

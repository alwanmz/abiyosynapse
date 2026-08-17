import { ListHeader } from '@/components/list-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import { Head, router } from '@inertiajs/react';
import { type ReactElement, useState } from 'react';
import { useTranslation } from 'react-i18next';

interface Option {
    id: number;
    code: string;
    name: string;
}

interface MrpResult {
    product_id: number;
    code: string;
    name: string;
    uom_code: string;
    requirement: number;
    stock: number;
    open_po: number;
    shortage: number;
    action: string;
}

interface PageProps {
    products: Option[];
    warehouses: Option[];
    results: MrpResult[];
    filters: { product_id?: number; warehouse_id?: number; demand_quantity?: number };
}

function MrpPage({ products, warehouses, results, filters }: PageProps) {
    const { t } = useTranslation('manufacturing');

    useBreadcrumbs([
        { title: t('nav.manufacturing'), href: '#' },
        { title: t('mrp.breadcrumb'), href: '/manufacturing/mrp' },
    ]);

    const [productId, setProductId] = useState(filters.product_id?.toString() ?? '');
    const [warehouseId, setWarehouseId] = useState(filters.warehouse_id?.toString() ?? '');
    const [demandQuantity, setDemandQuantity] = useState(filters.demand_quantity?.toString() ?? '');

    const handleRun = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/manufacturing/mrp',
            { product_id: productId, warehouse_id: warehouseId, demand_quantity: demandQuantity },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <>
            <Head title={t('mrp.title')} />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold tracking-tight">{t('mrp.title')}</h1>
                    <p className="text-sm text-muted-foreground">{t('mrp.description')}</p>
                </div>

                <Card className="mb-6">
                    <CardContent className="p-5">
                        <form onSubmit={handleRun} className="grid gap-4 md:grid-cols-4">
                            <div className="grid gap-2">
                                <Label>{t('mrp.product')}</Label>
                                <Select value={productId} onValueChange={setProductId}>
                                    <SelectTrigger>
                                        <SelectValue placeholder={t('mrp.product_placeholder')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {products.map((p) => (
                                            <SelectItem key={p.id} value={p.id.toString()}>
                                                {p.code} — {p.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="grid gap-2">
                                <Label>{t('mrp.warehouse')}</Label>
                                <Select value={warehouseId} onValueChange={setWarehouseId}>
                                    <SelectTrigger>
                                        <SelectValue placeholder={t('mrp.warehouse_placeholder')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {warehouses.map((w) => (
                                            <SelectItem key={w.id} value={w.id.toString()}>
                                                {w.code} — {w.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="grid gap-2">
                                <Label>{t('mrp.demand_quantity')}</Label>
                                <Input
                                    type="number"
                                    step="0.0001"
                                    min={0.0001}
                                    value={demandQuantity}
                                    onChange={(e) => setDemandQuantity(e.target.value)}
                                />
                            </div>

                            <div className="flex items-end">
                                <Button type="submit" className="w-full">
                                    {t('mrp.run')}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card className="overflow-hidden p-0">
                    <ListHeader title={t('mrp.results_title')} />
                    <CardContent className="p-5">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('mrp.table.material')}</TableHead>
                                    <TableHead className="text-right">{t('mrp.table.requirement')}</TableHead>
                                    <TableHead className="text-right">{t('mrp.table.stock')}</TableHead>
                                    <TableHead className="text-right">{t('mrp.table.open_po')}</TableHead>
                                    <TableHead className="text-right">{t('mrp.table.shortage')}</TableHead>
                                    <TableHead>{t('mrp.table.action')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {results.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={6} className="h-24 text-center text-muted-foreground">
                                            {t('mrp.empty')}
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    results.map((r) => (
                                        <TableRow key={r.product_id}>
                                            <TableCell>
                                                <div className="font-medium">{r.name}</div>
                                                <div className="font-mono text-xs text-muted-foreground">{r.code}</div>
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {r.requirement} {r.uom_code}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {r.stock} {r.uom_code}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">{r.open_po}</TableCell>
                                            <TableCell
                                                className={`text-right font-medium tabular-nums ${
                                                    r.shortage > 0 ? 'text-destructive' : 'text-nx-andon-run'
                                                }`}
                                            >
                                                {r.shortage} {r.uom_code}
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant={r.shortage > 0 ? 'outline' : 'default'}>
                                                    {r.shortage > 0
                                                        ? `${t('mrp.action_purchase')} ${r.shortage}`
                                                        : t('mrp.action_use_stock')}
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

MrpPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default MrpPage;

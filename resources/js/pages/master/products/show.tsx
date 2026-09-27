import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { useBreadcrumbs } from '@/hooks/use-breadcrumbs';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, CircleCheck, CircleX, PackageCheck, ShieldAlert } from 'lucide-react';
import { type ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

type ReadinessStatus = 'not_configured' | 'pending_qc' | 'quality_hold' | 'rework_required' | 'out_of_stock' | 'ready_for_sale';

interface Warehouse {
    id: number;
    code: string;
    name: string;
}

interface Product {
    id: number;
    code: string;
    name: string;
    type: 'manufactured' | 'purchased' | 'service';
    status: 'draft' | 'pending_approval' | 'active' | 'inactive';
    selling_price: string;
    base_unit_of_measure: { code: string };
    bom: { code: string; status: string } | null;
    routing: { code: string; status: string } | null;
}

interface Readiness {
    status: ReadinessStatus;
    quantities: Record<'pending' | 'approved' | 'hold' | 'rejected' | 'rework', number>;
    reserved_quantity: number;
    available_for_sale: number;
    reasons: string[];
    configuration: Record<string, boolean>;
}

interface PageProps {
    product: Product;
    warehouses: Warehouse[];
    selectedWarehouseId: number | null;
    readiness: Readiness | null;
}

const readinessTone: Record<ReadinessStatus, 'default' | 'secondary' | 'outline' | 'destructive'> = {
    ready_for_sale: 'default',
    pending_qc: 'secondary',
    quality_hold: 'secondary',
    rework_required: 'destructive',
    out_of_stock: 'outline',
    not_configured: 'destructive',
};

function ProductReadinessPage({ product, warehouses, selectedWarehouseId, readiness }: PageProps) {
    const { t } = useTranslation('master');

    useBreadcrumbs([
        { title: t('nav.master_data'), href: '#' },
        { title: t('product.title'), href: '/master/products' },
        { title: product.code, href: '#' },
    ]);

    const switchWarehouse = (value: string) => {
        router.get(`/master/products/${product.id}`, { warehouse_id: value }, { preserveScroll: true, preserveState: false });
    };

    const quantities = readiness?.quantities ?? { pending: 0, approved: 0, hold: 0, rejected: 0, rework: 0 };
    const total = Object.values(quantities).reduce((sum, quantity) => sum + quantity, 0);
    const approvedRatio = total > 0 ? (quantities.approved / total) * 100 : 0;

    return (
        <>
            <Head title={`${product.code} · ${t('product.readiness.title')}`} />
            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <Button asChild variant="ghost" size="sm" className="mb-2 -ml-2">
                            <Link href="/master/products"><ArrowLeft />{t('product.readiness.back')}</Link>
                        </Button>
                        <div className="flex flex-wrap items-center gap-3">
                            <h1 className="text-2xl font-semibold tracking-tight">{product.name}</h1>
                            {readiness && <Badge variant={readinessTone[readiness.status]}>{t(`product.readiness.status_${readiness.status}`)}</Badge>}
                        </div>
                        <p className="font-mono text-sm text-muted-foreground">{product.code} · {product.base_unit_of_measure.code}</p>
                    </div>
                    <div className="w-full sm:w-64">
                        <Label className="mb-2 block">{t('product.readiness.warehouse')}</Label>
                        <Select value={selectedWarehouseId?.toString()} onValueChange={switchWarehouse}>
                            <SelectTrigger><SelectValue placeholder={t('product.readiness.warehouse')} /></SelectTrigger>
                            <SelectContent>
                                {warehouses.map((warehouse) => <SelectItem key={warehouse.id} value={warehouse.id.toString()}>{warehouse.code} - {warehouse.name}</SelectItem>)}
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                {!readiness ? (
                    <Card><CardContent className="p-6 text-sm text-muted-foreground">{t('product.readiness.no_warehouse')}</CardContent></Card>
                ) : (
                    <div className="grid gap-6 xl:grid-cols-3">
                        <Card className="xl:col-span-2">
                            <CardContent className="p-5">
                                <div className="mb-5 flex items-center justify-between gap-4">
                                    <div>
                                        <p className="text-sm font-medium">{t('product.readiness.available_for_sale')}</p>
                                        <p className="mt-1 text-3xl font-semibold tabular-nums">{readiness.available_for_sale.toFixed(4)} <span className="text-base font-medium text-muted-foreground">{product.base_unit_of_measure.code}</span></p>
                                    </div>
                                    <PackageCheck className="size-9 text-nx-cyan-500" aria-hidden="true" />
                                </div>
                                <Progress value={approvedRatio} tone={readiness.status === 'ready_for_sale' ? 'run' : readiness.status === 'rework_required' ? 'stop' : 'caution'} />
                                <div className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                                    {(['approved', 'hold', 'rejected', 'rework', 'pending'] as const).map((state) => (
                                        <div key={state} className="border-l-2 border-border pl-3">
                                            <p className="text-xs text-muted-foreground">{t(`product.readiness.quantity_${state}`)}</p>
                                            <p className="mt-1 font-semibold tabular-nums">{(quantities[state] ?? 0).toFixed(4)}</p>
                                        </div>
                                    ))}
                                </div>
                                <div className="mt-5 border-t pt-4 text-sm text-muted-foreground">
                                    {t('product.readiness.reserved')}: <span className="font-medium tabular-nums text-foreground">{readiness.reserved_quantity.toFixed(4)}</span>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent className="p-5">
                                <div className="mb-4 flex items-center gap-2"><ShieldAlert className="size-4 text-nx-andon-caution" /><h2 className="font-semibold">{t('product.readiness.requirements')}</h2></div>
                                <div className="space-y-3 text-sm">
                                    {Object.entries(readiness.configuration).map(([key, valid]) => (
                                        <div key={key} className="flex items-center justify-between gap-3">
                                            <span className="text-muted-foreground">{t(`product.readiness.config_${key}`)}</span>
                                            {valid ? <CircleCheck className="size-4 text-nx-andon-run" /> : <CircleX className="size-4 text-destructive" />}
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="xl:col-span-3">
                            <CardContent className="p-5">
                                <h2 className="font-semibold">{t('product.readiness.reasons')}</h2>
                                {readiness.reasons.length === 0 ? (
                                    <p className="mt-3 text-sm text-nx-andon-run">{t('product.readiness.no_reasons')}</p>
                                ) : (
                                    <ul className="mt-3 grid gap-2 text-sm text-muted-foreground md:grid-cols-2">
                                        {readiness.reasons.map((reason) => <li key={reason} className="rounded-md bg-nx-andon-caution-bg px-3 py-2 text-nx-andon-caution">{t(`product.readiness.reason_${reason}`)}</li>)}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                )}
            </div>
        </>
    );
}

ProductReadinessPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default ProductReadinessPage;

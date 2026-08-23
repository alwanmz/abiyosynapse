import { ListHeader } from '@/components/list-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { IconPlus } from '@tabler/icons-react';
import { type ReactElement, useState } from 'react';
import { useTranslation } from 'react-i18next';

interface ProductionOrder {
    id: number;
    number: string;
    planned_quantity: string;
    produced_quantity: string;
    status: 'planned' | 'released' | 'in_production' | 'qc' | 'completed' | 'closed';
    due_date: string;
    product: { id: number; code: string; name: string };
    warehouse: { id: number; code: string; name: string };
}

interface Option {
    id: number;
    code: string;
    name: string;
}

interface PageProps {
    orders: ProductionOrder[];
    products: Option[];
    warehouses: Option[];
}

const STATUS_VARIANT: Record<ProductionOrder['status'], 'default' | 'outline' | 'secondary'> = {
    planned: 'outline',
    released: 'secondary',
    in_production: 'secondary',
    qc: 'secondary',
    completed: 'default',
    closed: 'outline',
};

function ProductionOrdersPage({ orders, products, warehouses }: PageProps) {
    const { t } = useTranslation('manufacturing');
    const { locale } = usePage().props as { locale?: string };
    const [dialogOpen, setDialogOpen] = useState(false);

    useBreadcrumbs([
        { title: t('nav.manufacturing'), href: '#' },
        { title: t('production_order.breadcrumb'), href: '/manufacturing/production-orders' },
    ]);

    const formatDate = (value: string) =>
        new Date(value).toLocaleDateString(locale ?? 'id-ID', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });

    const { data, setData, post, processing, errors, reset } = useForm({
        product_id: '' as string | number,
        warehouse_id: '' as string | number,
        planned_quantity: '',
        start_date: new Date().toISOString().slice(0, 10),
        due_date: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/manufacturing/production-orders', {
            preserveScroll: true,
            onSuccess: () => {
                setDialogOpen(false);
                reset();
            },
        });
    };

    return (
        <>
            <Head title={t('production_order.title')} />

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">{t('production_order.title')}</h1>
                        <p className="text-sm text-muted-foreground">{t('production_order.description')}</p>
                    </div>
                    <Button onClick={() => setDialogOpen(true)}>
                        <IconPlus className="mr-2 h-4 w-4" />
                        {t('production_order.add')}
                    </Button>
                </div>

                <Card className="overflow-hidden p-0">
                    <ListHeader title={t('production_order.list_title')} />
                    <CardContent className="p-5">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('production_order.table.number')}</TableHead>
                                    <TableHead>{t('production_order.table.product')}</TableHead>
                                    <TableHead>{t('production_order.table.warehouse')}</TableHead>
                                    <TableHead className="text-right">{t('production_order.table.planned_quantity')}</TableHead>
                                    <TableHead className="text-right">{t('production_order.table.produced_quantity')}</TableHead>
                                    <TableHead>{t('production_order.table.status')}</TableHead>
                                    <TableHead>{t('production_order.table.due_date')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {orders.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={7} className="h-24 text-center text-muted-foreground">
                                            {t('production_order.empty')}
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    orders.map((order) => (
                                        <TableRow key={order.id}>
                                            <TableCell className="font-mono text-sm">
                                                <Link
                                                    href={`/manufacturing/production-orders/${order.id}`}
                                                    className="text-primary hover:underline"
                                                >
                                                    {order.number}
                                                </Link>
                                            </TableCell>
                                            <TableCell>
                                                <div className="font-medium">{order.product.name}</div>
                                                <div className="font-mono text-xs text-muted-foreground">{order.product.code}</div>
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {order.warehouse.code}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">{order.planned_quantity}</TableCell>
                                            <TableCell className="text-right tabular-nums">{order.produced_quantity}</TableCell>
                                            <TableCell>
                                                <Badge variant={STATUS_VARIANT[order.status]}>
                                                    {t(`production_order.status_${order.status}`)}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">{formatDate(order.due_date)}</TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="sm:max-w-[500px]">
                    <DialogHeader>
                        <DialogTitle>{t('production_order.add_title')}</DialogTitle>
                        <DialogDescription>{t('production_order.description')}</DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleSubmit}>
                        <div className="grid gap-4 py-4">
                            <div className="grid gap-2">
                                <Label htmlFor="product_id">{t('production_order.product')}</Label>
                                <Select
                                    value={data.product_id.toString()}
                                    onValueChange={(value) => setData('product_id', value)}
                                >
                                    <SelectTrigger id="product_id">
                                        <SelectValue placeholder={t('production_order.product_placeholder')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {products.map((p) => (
                                            <SelectItem key={p.id} value={p.id.toString()}>
                                                {p.code} — {p.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.product_id && <p className="text-sm text-destructive">{errors.product_id}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="warehouse_id">{t('production_order.warehouse')}</Label>
                                <Select
                                    value={data.warehouse_id.toString()}
                                    onValueChange={(value) => setData('warehouse_id', value)}
                                >
                                    <SelectTrigger id="warehouse_id">
                                        <SelectValue placeholder={t('production_order.warehouse_placeholder')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {warehouses.map((w) => (
                                            <SelectItem key={w.id} value={w.id.toString()}>
                                                {w.code} — {w.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.warehouse_id && <p className="text-sm text-destructive">{errors.warehouse_id}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="planned_quantity">{t('production_order.planned_quantity')}</Label>
                                <Input
                                    id="planned_quantity"
                                    type="number"
                                    step="0.0001"
                                    min={0.0001}
                                    value={data.planned_quantity}
                                    onChange={(e) => setData('planned_quantity', e.target.value)}
                                />
                                {errors.planned_quantity && <p className="text-sm text-destructive">{errors.planned_quantity}</p>}
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="start_date">{t('production_order.start_date')}</Label>
                                    <Input
                                        id="start_date"
                                        type="date"
                                        value={data.start_date}
                                        onChange={(e) => setData('start_date', e.target.value)}
                                    />
                                    {errors.start_date && <p className="text-sm text-destructive">{errors.start_date}</p>}
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="due_date">{t('production_order.due_date')}</Label>
                                    <Input
                                        id="due_date"
                                        type="date"
                                        value={data.due_date}
                                        onChange={(e) => setData('due_date', e.target.value)}
                                    />
                                    {errors.due_date && <p className="text-sm text-destructive">{errors.due_date}</p>}
                                </div>
                            </div>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="cancel" onClick={() => setDialogOpen(false)} disabled={processing}>
                                Batal
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {t('production_order.add')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

ProductionOrdersPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default ProductionOrdersPage;

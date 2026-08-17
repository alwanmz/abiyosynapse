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
import { X } from 'lucide-react';
import { type ReactElement, useState } from 'react';
import { useTranslation } from 'react-i18next';

interface SalesOrder {
    id: number;
    number: string;
    order_date: string;
    requested_delivery_date: string | null;
    status: 'draft' | 'approval' | 'approved' | 'partial' | 'fulfilled' | 'closed';
    total: string;
    customer: { id: number; code: string; name: string };
    warehouse: { id: number; code: string; name: string };
}

interface Option {
    id: number;
    code: string;
    name: string;
}

interface ProductOption extends Option {
    selling_price: string;
}

interface TaxCodeOption extends Option {
    rate: string;
}

interface PageProps {
    orders: SalesOrder[];
    products: ProductOption[];
    customers: Option[];
    warehouses: Option[];
    taxCodes: TaxCodeOption[];
}

interface LineForm {
    product_id: string | number;
    quantity: string;
    unit_price: string;
    tax_code_id: string | number;
}

const emptyLine: LineForm = { product_id: '', quantity: '', unit_price: '', tax_code_id: '' };

const STATUS_VARIANT: Record<SalesOrder['status'], 'default' | 'outline' | 'secondary'> = {
    draft: 'outline',
    approval: 'secondary',
    approved: 'secondary',
    partial: 'secondary',
    fulfilled: 'default',
    closed: 'outline',
};

function SalesOrdersPage({ orders, products, customers, warehouses, taxCodes }: PageProps) {
    const { t } = useTranslation('sales');
    const { locale } = usePage().props as { locale?: string };
    const [dialogOpen, setDialogOpen] = useState(false);

    useBreadcrumbs([
        { title: t('nav.sales'), href: '#' },
        { title: t('sales_order.breadcrumb'), href: '/sales/sales-orders' },
    ]);

    const formatDate = (value: string) =>
        new Date(value).toLocaleDateString(locale ?? 'id-ID', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });

    const { data, setData, post, processing, errors, clearErrors, reset } = useForm<{
        customer_id: string | number;
        warehouse_id: string | number;
        requested_delivery_date: string;
        lines: LineForm[];
    }>({
        customer_id: '',
        warehouse_id: '',
        requested_delivery_date: '',
        lines: [{ ...emptyLine }],
    });

    const openCreate = () => {
        reset();
        setData('lines', [{ ...emptyLine }]);
        setDialogOpen(true);
    };

    const addLine = () => setData('lines', [...data.lines, { ...emptyLine }]);
    const removeLine = (index: number) => setData('lines', data.lines.filter((_, i) => i !== index));
    const updateLine = (index: number, patch: Partial<LineForm>) =>
        setData('lines', data.lines.map((l, i) => (i === index ? { ...l, ...patch } : l)));

    const handleProductChange = (index: number, productId: string) => {
        const product = products.find((p) => p.id.toString() === productId);
        updateLine(index, {
            product_id: productId,
            unit_price: product ? product.selling_price : data.lines[index].unit_price,
        });
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/sales/sales-orders', {
            preserveScroll: true,
            onSuccess: () => {
                setDialogOpen(false);
                reset();
            },
        });
    };

    return (
        <>
            <Head title={t('sales_order.title')} />

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">{t('sales_order.title')}</h1>
                        <p className="text-sm text-muted-foreground">{t('sales_order.description')}</p>
                    </div>
                    <Button onClick={openCreate}>
                        <IconPlus className="mr-2 h-4 w-4" />
                        {t('sales_order.add')}
                    </Button>
                </div>

                <Card className="overflow-hidden p-0">
                    <ListHeader title={t('sales_order.list_title')} />
                    <CardContent className="p-5">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('sales_order.table.number')}</TableHead>
                                    <TableHead>{t('sales_order.table.customer')}</TableHead>
                                    <TableHead>{t('sales_order.table.warehouse')}</TableHead>
                                    <TableHead className="text-right">{t('sales_order.table.total')}</TableHead>
                                    <TableHead>{t('sales_order.table.status')}</TableHead>
                                    <TableHead>{t('sales_order.table.order_date')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {orders.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={6} className="h-24 text-center text-muted-foreground">
                                            {t('sales_order.empty')}
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    orders.map((so) => (
                                        <TableRow key={so.id}>
                                            <TableCell className="font-mono text-sm">
                                                <Link
                                                    href={`/sales/sales-orders/${so.id}`}
                                                    className="text-primary hover:underline"
                                                >
                                                    {so.number}
                                                </Link>
                                            </TableCell>
                                            <TableCell>
                                                <div className="font-medium">{so.customer.name}</div>
                                                <div className="font-mono text-xs text-muted-foreground">
                                                    {so.customer.code}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {so.warehouse.code}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">{so.total}</TableCell>
                                            <TableCell>
                                                <Badge variant={STATUS_VARIANT[so.status]}>
                                                    {t(`sales_order.status_${so.status}`)}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">{formatDate(so.order_date)}</TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="flex max-h-[90vh] max-w-3xl flex-col overflow-hidden">
                    <DialogHeader>
                        <DialogTitle>{t('sales_order.add_title')}</DialogTitle>
                        <DialogDescription>{t('sales_order.description')}</DialogDescription>
                    </DialogHeader>

                    <form id="so-form" onSubmit={handleSubmit} className="flex-1 space-y-4 overflow-y-auto pr-2">
                        <div className="grid gap-4 md:grid-cols-3">
                            <div className="grid gap-2">
                                <Label htmlFor="customer_id">{t('sales_order.customer')}</Label>
                                <Select
                                    value={data.customer_id.toString()}
                                    onValueChange={(value) => {
                                        setData('customer_id', value);
                                        clearErrors('customer_id');
                                    }}
                                >
                                    <SelectTrigger id="customer_id">
                                        <SelectValue placeholder={t('sales_order.customer_placeholder')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {customers.map((c) => (
                                            <SelectItem key={c.id} value={c.id.toString()}>
                                                {c.code} — {c.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.customer_id && <p className="text-sm text-destructive">{errors.customer_id}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="warehouse_id">{t('sales_order.warehouse')}</Label>
                                <Select
                                    value={data.warehouse_id.toString()}
                                    onValueChange={(value) => {
                                        setData('warehouse_id', value);
                                        clearErrors('warehouse_id');
                                    }}
                                >
                                    <SelectTrigger id="warehouse_id">
                                        <SelectValue placeholder={t('sales_order.warehouse_placeholder')} />
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
                                <Label htmlFor="requested_delivery_date">{t('sales_order.requested_delivery_date')}</Label>
                                <Input
                                    id="requested_delivery_date"
                                    type="date"
                                    value={data.requested_delivery_date}
                                    onChange={(e) => setData('requested_delivery_date', e.target.value)}
                                />
                                {errors.requested_delivery_date && (
                                    <p className="text-sm text-destructive">{errors.requested_delivery_date}</p>
                                )}
                            </div>
                        </div>

                        <div className="space-y-3">
                            <div className="flex items-center justify-between">
                                <Label className="text-base">{t('sales_order.lines')}</Label>
                                <Button type="button" variant="outline" size="sm" onClick={addLine}>
                                    <IconPlus className="mr-1 h-3.5 w-3.5" />
                                    {t('sales_order.add_line')}
                                </Button>
                            </div>

                            {data.lines.map((line, index) => (
                                <div key={index} className="grid grid-cols-12 items-start gap-2 rounded-lg border p-3">
                                    <div className="col-span-4">
                                        <Select
                                            value={line.product_id.toString()}
                                            onValueChange={(value) => handleProductChange(index, value)}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder={t('sales_order.product_placeholder')} />
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
                                    <div className="col-span-2">
                                        <Input
                                            type="number"
                                            step="0.0001"
                                            min={0.0001}
                                            placeholder={t('sales_order.quantity')}
                                            value={line.quantity}
                                            onChange={(e) => updateLine(index, { quantity: e.target.value })}
                                        />
                                    </div>
                                    <div className="col-span-2">
                                        <Input
                                            type="number"
                                            step="0.01"
                                            min={0}
                                            placeholder={t('sales_order.unit_price')}
                                            value={line.unit_price}
                                            onChange={(e) => updateLine(index, { unit_price: e.target.value })}
                                        />
                                    </div>
                                    <div className="col-span-3">
                                        <Select
                                            value={line.tax_code_id.toString()}
                                            onValueChange={(value) => updateLine(index, { tax_code_id: value })}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder={t('sales_order.tax_code_placeholder')} />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {taxCodes.map((tc) => (
                                                    <SelectItem key={tc.id} value={tc.id.toString()}>
                                                        {tc.code} ({tc.rate}%)
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="col-span-1 flex justify-end">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => removeLine(index)}
                                            disabled={data.lines.length === 1}
                                        >
                                            <X className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            ))}
                            {errors.lines && <p className="text-sm text-destructive">{errors.lines}</p>}
                        </div>
                    </form>

                    <DialogFooter className="mt-2 border-t pt-4">
                        <Button type="button" variant="outline" onClick={() => setDialogOpen(false)} disabled={processing}>
                            Batal
                        </Button>
                        <Button type="submit" form="so-form" disabled={processing}>
                            {t('sales_order.add')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

SalesOrdersPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default SalesOrdersPage;
